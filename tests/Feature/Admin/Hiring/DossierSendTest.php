<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\HiringCommunicationKind;
use App\Enums\HiringCommunicationStatus;
use App\Enums\HiringProcessStage;
use App\Mail\HiringCommunicationMail;
use App\Models\Company;
use App\Models\HiringAssessment;
use App\Models\HiringCommunication;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

class DossierSendTest extends CommunicationTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(array $contactIds, array $overrides = []): array
    {
        return [
            'contact_ids' => $contactIds,
            'subject' => 'Dossiê da candidatura',
            'body' => 'Segue em anexo o dossiê aprovado.',
            ...$overrides,
        ];
    }

    private function approve($application, ?int $userId = null): void
    {
        HiringAssessment::query()->create([
            'hiring_application_id' => $application->id,
            'interviewer_summary' => 'Boa aderência.',
            'decision' => 'recommended',
            'criteria' => [],
            'approved_at' => now(),
            'approved_by' => $userId,
        ]);
    }

    public function test_dossier_is_sent_only_when_approved_only_with_validate_and_with_pdf(): void
    {
        Mail::fake();
        $this->enableSmtp();
        [, $application, $vacancy] = $this->candidateWithApplication();
        $contact = $this->contact($vacancy->company_id);
        $url = route('admin.contratacao.vagas.applications.assessment.send', [$vacancy, $application]);

        $validator = $this->adminWith(['contratacao_vagas' => ['view', 'validate']]);
        $this->actingAs($validator)->post($url, $this->payload([$contact->id]))->assertSessionHasErrors('dossier');
        Mail::assertNothingSent();

        $this->approve($application);

        $editor = $this->adminWith(['contratacao_vagas' => ['view', 'edit']]);
        $this->actingAs($editor)->post($url, $this->payload([$contact->id]))->assertForbidden();
        Mail::assertNothingSent();

        $this->actingAs($validator)->post($url, $this->payload([$contact->id]))->assertSessionHasNoErrors();

        $communication = HiringCommunication::query()->firstOrFail();
        $this->assertSame(HiringCommunicationKind::Dossier, $communication->kind);
        $this->assertSame(HiringCommunicationStatus::Sent, $communication->status);
        $this->assertSame($application->id, $communication->hiring_application_id);

        Mail::assertSent(HiringCommunicationMail::class, function (HiringCommunicationMail $mail) use ($contact, $application) {
            $attachments = $mail->attachments();

            return $mail->hasTo($contact->email)
                && count($attachments) === 1
                && $attachments[0]->as === 'dossie-candidatura-'.$application->id.'.pdf'
                && str_starts_with((string) $mail->attachmentData, '%PDF');
        });

        // Mesmo texto: bloqueado sem «Enviar novamente».
        $this->actingAs($validator)->post($url, $this->payload([$contact->id]))->assertSessionHasErrors('duplicate');
        $this->actingAs($validator)->post($url, $this->payload([$contact->id], ['resend' => true]))->assertSessionHasNoErrors();
        Mail::assertSentCount(2);
    }

    public function test_dossier_needs_email_channel_and_company_contact(): void
    {
        Mail::fake();
        $this->enableSmtp();
        $owner = $this->owner();
        [, $application, $vacancy] = $this->candidateWithApplication();
        $this->approve($application);
        $whatsOnly = $this->contact($vacancy->company_id, ['channel_email' => false]);
        $otherVacancy = $this->vacancy(null, 'Outra empresa');
        $foreign = $this->contact($otherVacancy->company_id, ['email' => 'fora@outra.test']);
        $url = route('admin.contratacao.vagas.applications.assessment.send', [$vacancy, $application]);

        $this->actingAs($owner)->post($url, $this->payload([$whatsOnly->id]))->assertSessionHasErrors('contact_ids');
        $this->actingAs($owner)->post($url, $this->payload([$foreign->id]))->assertSessionHasErrors('contact_ids');
        Mail::assertNothingSent();

        // Candidatura de outra vaga dá 404.
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.assessment.send', [$otherVacancy, $application]), $this->payload([$foreign->id]))
            ->assertNotFound();

        // A tela de avaliação só oferece contatos elegíveis com e-mail.
        $this->actingAs($owner)
            ->get(route('admin.contratacao.vagas.applications.assessment.show', [$vacancy, $application]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('dossier_send.contacts', 0));
    }

    public function test_portal_never_receives_the_dossier(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Portal', 'is_active' => true, 'acompanhamento_access' => true]);
        $this->subscribeCompanyToNr1($company);
        $process = $this->process(HiringProcessStage::EntrevistaGestor, $company);
        $vacancy = $this->vacancy($process);
        [, $application] = $this->candidateWithApplication('Ana Souza', $vacancy);
        $this->approve($application);
        $companyAdmin = User::factory()->companyAdmin($company->id)->create();

        $html = $this->actingAs($companyAdmin)
            ->get(route('client.acompanhamento.index', ['stage' => HiringProcessStage::EntrevistaGestor->value]))
            ->assertOk()
            ->getContent();
        // Nada do dossiê de contratação (conteúdo, decisão nem candidato) chega ao portal.
        $this->assertStringNotContainsString('Boa aderência', $html);
        $this->assertStringNotContainsString('recommended', $html);
        $this->assertStringNotContainsString('Ana Souza', $html);

        $this->actingAs($companyAdmin)
            ->get(route('admin.contratacao.vagas.applications.assessment.dossier', [$vacancy, $application]))
            ->assertForbidden();
    }
}
