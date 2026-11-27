<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\HiringCommunicationKind;
use App\Enums\HiringCommunicationStatus;
use App\Mail\HiringCommunicationMail;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\HiringCommunication;
use App\Models\HiringVacancy;
use App\Models\HiringVacancyStage;
use App\Services\Hiring\HiringCommunicationService;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

class HiringCommunicationTest extends CommunicationTestCase
{
    /**
     * @return array{0: HiringVacancy, 1: HiringVacancyStage, 2: CompanyContact}
     */
    private function setUpVacancy(): array
    {
        [, , $vacancy] = $this->candidateWithApplication('Ana Souza');
        $stage = $vacancy->stages()->first();
        $contact = $this->contact($vacancy->company_id);

        return [$vacancy, $stage, $contact];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HiringVacancy $vacancy, $stage, array $contactIds, array $overrides = []): array
    {
        $template = app(HiringCommunicationService::class)->stageTemplate($vacancy, $stage);

        return [
            'stage_id' => $stage->id,
            'channel' => 'email',
            'contact_ids' => $contactIds,
            'subject' => $template['subject'],
            'body' => $template['body'],
            ...$overrides,
        ];
    }

    public function test_email_send_creates_sent_record_and_mailable_without_candidate_data(): void
    {
        Mail::fake();
        $this->enableSmtp();
        $owner = $this->owner();
        [$vacancy, $stage, $contact] = $this->setUpVacancy();

        $template = app(HiringCommunicationService::class)->stageTemplate($vacancy, $stage);
        $this->assertStringContainsString($vacancy->title, $template['body']);
        $this->assertStringContainsString('«'.$stage->name.'»', $template['body']);
        $this->assertStringContainsString('1 candidato nesta etapa', $template['body']);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.communications.store', $vacancy), $this->payload($vacancy, $stage, [$contact->id]))
            ->assertSessionHasNoErrors();

        $communication = HiringCommunication::query()->firstOrFail();
        $this->assertSame(HiringCommunicationStatus::Sent, $communication->status);
        $this->assertSame(HiringCommunicationKind::StageUpdate, $communication->kind);
        $this->assertNotNull($communication->sent_at);
        $this->assertSame('carla.rh@empresa.test', $communication->recipient);
        $this->assertSame($stage->name, $communication->stage_name);

        Mail::assertSent(HiringCommunicationMail::class, function (HiringCommunicationMail $mail) use ($contact) {
            $html = $mail->render();

            return $mail->hasTo($contact->email)
                && ! str_contains($html, 'Ana Souza')
                && ! str_contains($html, 'ana.souza@example.com')
                && $mail->attachments() === [];
        });

        // Status nunca fala em entrega.
        $this->assertSame('Enviado ao servidor de e-mail', $communication->status->label());
    }

    public function test_body_with_candidate_name_is_refused(): void
    {
        Mail::fake();
        $this->enableSmtp();
        $owner = $this->owner();
        [$vacancy, $stage, $contact] = $this->setUpVacancy();

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.communications.store', $vacancy), $this->payload($vacancy, $stage, [$contact->id], [
                'body' => 'A candidata ana souza avançou para a próxima etapa.',
            ]))
            ->assertSessionHasErrors('body');

        $this->assertSame(0, HiringCommunication::query()->count());
        Mail::assertNothingSent();
    }

    public function test_same_text_is_not_sent_twice_unless_resend_is_confirmed(): void
    {
        Mail::fake();
        $this->enableSmtp();
        $owner = $this->owner();
        [$vacancy, $stage, $contact] = $this->setUpVacancy();
        $url = route('admin.contratacao.vagas.communications.store', $vacancy);
        $payload = $this->payload($vacancy, $stage, [$contact->id]);

        $this->actingAs($owner)->post($url, $payload)->assertSessionHasNoErrors();
        $this->actingAs($owner)->post($url, $payload)->assertSessionHasErrors('duplicate');
        $this->assertSame(1, HiringCommunication::query()->count());
        Mail::assertSentCount(1);

        // A revisão avisa que o mesmo texto já saiu.
        $this->actingAs($owner)
            ->postJson(route('admin.contratacao.vagas.communications.review', $vacancy), $payload)
            ->assertOk()
            ->assertJsonPath('rows.0.already_done', true)
            ->assertJsonPath('rows.0.previous_status', 'Enviado ao servidor de e-mail');

        // Mudar o texto libera.
        $this->actingAs($owner)->post($url, [...$payload, 'body' => $payload['body']."\n\nAtualização complementar."])->assertSessionHasNoErrors();
        // «Enviar novamente» com o mesmo texto gera uma chave nova registrada.
        $this->actingAs($owner)->post($url, [...$payload, 'resend' => true])->assertSessionHasNoErrors();

        $this->assertSame(3, HiringCommunication::query()->count());
        $this->assertSame(3, HiringCommunication::query()->distinct()->count('idempotency_key'));
        Mail::assertSentCount(3);
    }

    public function test_daily_limit_of_twenty_emails(): void
    {
        Mail::fake();
        $this->enableSmtp();
        $owner = $this->owner();
        [$vacancy, $stage, $contact] = $this->setUpVacancy();

        for ($i = 0; $i < HiringCommunicationService::DAILY_EMAIL_LIMIT - 1; $i++) {
            HiringCommunication::query()->create([
                'hiring_vacancy_id' => $vacancy->id,
                'company_contact_id' => $contact->id,
                'kind' => HiringCommunicationKind::StageUpdate,
                'channel' => 'email',
                'body' => 'Envio anterior '.$i,
                'idempotency_key' => hash('sha256', 'anterior'.$i),
                'status' => HiringCommunicationStatus::Sent,
                'sent_at' => now(),
            ]);
        }
        $second = $this->contact($vacancy->company_id, ['name' => 'Davi RH', 'email' => 'davi@empresa.test']);
        $url = route('admin.contratacao.vagas.communications.store', $vacancy);

        // Dois destinatários com 1 restante: recusado inteiro.
        $this->actingAs($owner)
            ->post($url, $this->payload($vacancy, $stage, [$contact->id, $second->id]))
            ->assertSessionHasErrors(['limit' => 'Limite de 20 e-mails de comunicação por dia. Hoje restam 1.']);
        Mail::assertNothingSent();

        $this->actingAs($owner)->post($url, $this->payload($vacancy, $stage, [$contact->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, app(HiringCommunicationService::class)->emailsRemainingToday());

        $this->actingAs($owner)->post($url, $this->payload($vacancy, $stage, [$second->id]))->assertSessionHasErrors('limit');
        Mail::assertSentCount(1);
    }

    public function test_smtp_failure_becomes_failed_with_reason(): void
    {
        $this->enableSmtp();
        $owner = $this->owner();
        [$vacancy, $stage, $contact] = $this->setUpVacancy();

        Mail::shouldReceive('to->send')->once()->andThrow(new \RuntimeException('Connection refused'));

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.communications.store', $vacancy), $this->payload($vacancy, $stage, [$contact->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $communication = HiringCommunication::query()->firstOrFail();
        $this->assertSame(HiringCommunicationStatus::Failed, $communication->status);
        $this->assertNull($communication->sent_at);
        $this->assertStringContainsString('Connection refused', $communication->failure_reason);
    }

    public function test_whatsapp_records_copied_and_never_sent(): void
    {
        Mail::fake();
        $owner = $this->owner();
        [$vacancy, $stage, $contact] = $this->setUpVacancy();
        $payload = $this->payload($vacancy, $stage, [$contact->id], ['channel' => 'whatsapp', 'subject' => null]);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.communications.store', $vacancy), $payload)
            ->assertSessionHasNoErrors();

        $communication = HiringCommunication::query()->firstOrFail();
        $this->assertSame(HiringCommunicationStatus::Draft, $communication->status);
        $this->assertSame('+55 11 95555-0000', $communication->recipient);

        $this->actingAs($owner)
            ->postJson(route('admin.contratacao.vagas.communications.copied', [$vacancy, $communication]))
            ->assertOk()
            ->assertJsonPath('status', 'copied')
            ->assertJsonPath('status_label', 'Texto copiado');

        $communication->refresh();
        $this->assertSame(HiringCommunicationStatus::Copied, $communication->status);
        $this->assertNull($communication->sent_at);
        Mail::assertNothingSent();
        $this->assertSame(0, HiringCommunication::query()->where('status', HiringCommunicationStatus::Sent->value)->count());

        // O mesmo texto já copiado não é gerado de novo sem «Enviar novamente».
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.communications.store', $vacancy), $payload)
            ->assertSessionHasErrors('duplicate');

        // Comunicação de outra vaga dá 404.
        $otherVacancy = $this->vacancy(null, 'Outra vaga');
        $this->actingAs($owner)
            ->postJson(route('admin.contratacao.vagas.communications.copied', [$otherVacancy, $communication]))
            ->assertNotFound();
    }

    public function test_contact_of_another_company_or_ineligible_is_refused(): void
    {
        Mail::fake();
        $this->enableSmtp();
        $owner = $this->owner();
        [$vacancy, $stage] = $this->setUpVacancy();
        $foreign = $this->contact(Company::query()->create(['name' => 'Concorrente', 'is_active' => true]), ['email' => 'x@concorrente.test']);
        $notReviewed = $this->contact($vacancy->company_id, ['name' => 'Sem conferência', 'reviewed_at' => null]);
        $url = route('admin.contratacao.vagas.communications.store', $vacancy);

        $this->actingAs($owner)->post($url, $this->payload($vacancy, $stage, [$foreign->id]))
            ->assertSessionHasErrors(['contact_ids' => 'Escolha só contatos da empresa desta vaga.']);
        $this->actingAs($owner)->postJson(route('admin.contratacao.vagas.communications.review', $vacancy), $this->payload($vacancy, $stage, [$foreign->id]))
            ->assertStatus(422);
        $this->actingAs($owner)->post($url, $this->payload($vacancy, $stage, [$notReviewed->id]))
            ->assertSessionHasErrors('contact_ids');

        $this->assertSame(0, HiringCommunication::query()->count());
        Mail::assertNothingSent();
    }

    public function test_stage_communication_needs_vacancies_edit_and_board_payload(): void
    {
        Mail::fake();
        $this->enableSmtp();
        [$vacancy, $stage, $contact] = $this->setUpVacancy();
        $this->contact($vacancy->company_id, ['name' => 'Desabilitado', 'is_enabled' => false]);

        $viewer = $this->adminWith(['contratacao_vagas' => ['view']]);
        $this->actingAs($viewer)
            ->post(route('admin.contratacao.vagas.communications.store', $vacancy), $this->payload($vacancy, $stage, [$contact->id]))
            ->assertForbidden();
        $this->actingAs($viewer)
            ->get(route('admin.contratacao.vagas.show', $vacancy))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('communication', null)->has('communications', 0));

        $editor = $this->adminWith(['contratacao_vagas' => ['view', 'edit']]);
        $this->actingAs($editor)
            ->get(route('admin.contratacao.vagas.show', $vacancy))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('communication.contacts', 1)
                ->where('communication.contacts.0.name', 'Carla RH')
                ->where('communication.ineligible_count', 1)
                ->where('communication.daily_limit', 20)
                ->where('communication.smtp_configured', true));
        $this->actingAs($editor)
            ->post(route('admin.contratacao.vagas.communications.store', $vacancy), $this->payload($vacancy, $stage, [$contact->id]))
            ->assertSessionHasNoErrors();
    }
}
