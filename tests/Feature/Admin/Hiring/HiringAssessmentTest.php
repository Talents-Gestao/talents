<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Models\HiringAssessment;
use App\Models\TalentCandidateAttachment;
use App\Services\Hiring\HiringDossierRenderer;
use App\Services\Hiring\HiringVacancyService;
use Inertia\Testing\AssertableInertia;

class HiringAssessmentTest extends InterviewTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'success_profile' => 'Analista organizada, com experiência em recrutamento.',
            'case_description' => 'Planejar um processo seletivo em 2 semanas.',
            'case_result' => 'Plano completo e realista.',
            'criteria' => [
                ['criterio' => 'Experiência em R&S', 'evidencia' => 'Cinco anos em recrutamento (entrevista).', 'origem' => 'Entrevista', 'avaliacao' => 'atende'],
                ['criterio' => 'Inglês', 'evidencia' => '', 'origem' => '', 'avaliacao' => 'sem_evidencia'],
            ],
            'interviewer_summary' => 'Boa aderência ao perfil.',
            'limitations' => 'Não houve teste de inglês.',
            'decision' => 'recommended',
            ...$overrides,
        ];
    }

    public function test_view_edit_and_validate_permissions(): void
    {
        [, $application, $vacancy] = $this->candidateWithApplication();
        $params = [$vacancy, $application];

        $viewer = $this->adminWith(['contratacao_vagas' => ['view']]);
        $this->actingAs($viewer)
            ->get(route('admin.contratacao.vagas.applications.assessment.show', $params))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Hiring/Assessments/Edit')
                ->where('can.edit', false)
                ->where('can.validate', false));
        $this->actingAs($viewer)->put(route('admin.contratacao.vagas.applications.assessment.update', $params), $this->payload())->assertForbidden();

        $editor = $this->adminWith(['contratacao_vagas' => ['view', 'edit']]);
        $this->actingAs($editor)
            ->put(route('admin.contratacao.vagas.applications.assessment.update', $params), $this->payload())
            ->assertSessionHasNoErrors();
        $this->actingAs($editor)->post(route('admin.contratacao.vagas.applications.assessment.approve', $params))->assertForbidden();
        $this->assertNull(HiringAssessment::query()->value('approved_at'));

        $validator = $this->adminWith(['contratacao_vagas' => ['view', 'validate']]);
        $this->actingAs($validator)
            ->post(route('admin.contratacao.vagas.applications.assessment.approve', $params))
            ->assertSessionHasNoErrors();
        $assessment = HiringAssessment::query()->firstOrFail();
        $this->assertNotNull($assessment->approved_at);
        $this->assertSame($validator->id, $assessment->approved_by);

        $nobody = $this->adminWith(['contratacao_candidatos' => ['view']]);
        $this->actingAs($nobody)->get(route('admin.contratacao.vagas.applications.assessment.show', $params))->assertForbidden();
    }

    public function test_validate_action_is_only_granted_on_vacancies_module(): void
    {
        $admin = $this->adminWith(['contratacao_vagas' => ['validate'], 'tarefas' => ['validate', 'view']]);
        $grants = $admin->talentsWorkspace()->adminPermissions()->get()
            ->map(fn ($p) => ($p->module->value ?? $p->module).'|'.($p->action->value ?? $p->action))
            ->sort()->values()->all();

        $this->assertSame(['contratacao_vagas|validate', 'tarefas|view'], $grants);
    }

    public function test_approval_rules_and_editing_reopens_approval(): void
    {
        $owner = $this->owner();
        [, $application, $vacancy] = $this->candidateWithApplication();
        $params = [$vacancy, $application];

        $this->actingAs($owner)->post(route('admin.contratacao.vagas.applications.assessment.approve', $params))->assertSessionHasErrors('approve');

        $this->actingAs($owner)->put(route('admin.contratacao.vagas.applications.assessment.update', $params), $this->payload(['decision' => 'pending']));
        $this->actingAs($owner)->post(route('admin.contratacao.vagas.applications.assessment.approve', $params))->assertSessionHasErrors('approve');

        // Critério avaliado sem evidência é recusado.
        $this->actingAs($owner)
            ->put(route('admin.contratacao.vagas.applications.assessment.update', $params), $this->payload([
                'criteria' => [['criterio' => 'Liderança', 'evidencia' => '', 'origem' => '', 'avaliacao' => 'atende']],
            ]))
            ->assertSessionHasErrors('criteria');

        $this->actingAs($owner)->put(route('admin.contratacao.vagas.applications.assessment.update', $params), $this->payload());
        $this->actingAs($owner)->post(route('admin.contratacao.vagas.applications.assessment.approve', $params))->assertSessionHasNoErrors();
        $this->assertNotNull(HiringAssessment::query()->value('approved_at'));

        // Retirar da vaga uma candidatura com dossiê aprovado é recusado.
        $this->actingAs($owner)
            ->delete(route('admin.contratacao.vagas.applications.destroy', $params))
            ->assertSessionHasErrors('talent_candidate_id');
        $this->assertNotNull($application->fresh());

        $this->actingAs($owner)->put(route('admin.contratacao.vagas.applications.assessment.update', $params), $this->payload(['decision' => 'on_hold']));
        $this->assertNull(HiringAssessment::query()->value('approved_at'));
    }

    public function test_dossier_pdf_only_after_approval_and_without_attachments(): void
    {
        $owner = $this->owner();
        [$candidate, $application, $vacancy] = $this->candidateWithApplication();
        $params = [$vacancy, $application];
        TalentCandidateAttachment::query()->create([
            'talent_candidate_id' => $candidate->id,
            'original_name' => 'Laudo-psicologico-secreto.pdf',
            'path' => 'candidates/'.$candidate->id.'/x.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'sha256' => str_repeat('a', 64),
            'sender_name' => 'Dra. Paula',
            'received_on' => now()->toDateString(),
            'purpose' => 'Protocolo psicológico restrito',
            'privacy_confirmed_at' => now(),
        ]);
        $candidate->update(['notes' => 'Observação interna sobre dívidas']);

        $this->actingAs($owner)->get(route('admin.contratacao.vagas.applications.assessment.dossier', $params))->assertStatus(422);

        $this->actingAs($owner)->put(route('admin.contratacao.vagas.applications.assessment.update', $params), $this->payload());
        $this->actingAs($owner)->get(route('admin.contratacao.vagas.applications.assessment.dossier', $params))->assertStatus(422);

        $this->actingAs($owner)->post(route('admin.contratacao.vagas.applications.assessment.approve', $params));
        $response = $this->actingAs($owner)->get(route('admin.contratacao.vagas.applications.assessment.dossier', $params))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $html = app(HiringDossierRenderer::class)->html($application->fresh());
        $this->assertStringContainsString('Ana Souza', $html);
        $this->assertStringContainsString('Boa aderência ao perfil.', $html);
        $this->assertStringContainsString('A IA não decide', $html);
        $this->assertStringContainsString('decisão registrada neste dossiê é humana', $html);
        $this->assertStringNotContainsString('Laudo-psicologico-secreto', $html);
        $this->assertStringNotContainsString('Protocolo psicológico restrito', $html);
        $this->assertStringNotContainsString('dívidas', $html);
    }

    public function test_assessment_or_interview_of_another_application_returns_404(): void
    {
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [, $applicationA, $vacancyA] = $this->candidateWithApplication('Ana Souza');
        [$candidateB, $applicationB] = $this->candidateWithApplication('Bruno Lima', $vacancyA);
        $otherVacancy = $this->vacancy(null, 'Outra vaga');
        $applicationBElsewhere = app(HiringVacancyService::class)->addCandidate($otherVacancy, $candidateB, null, null);

        // Candidatura de outra vaga.
        $this->actingAs($owner)
            ->get(route('admin.contratacao.vagas.applications.assessment.show', [$vacancyA, $applicationBElsewhere]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->put(route('admin.contratacao.vagas.applications.assessment.update', [$vacancyA, $applicationBElsewhere]), $this->payload())
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.assessment.approve', [$vacancyA, $applicationBElsewhere]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('admin.contratacao.vagas.applications.assessment.dossier', [$vacancyA, $applicationBElsewhere]))
            ->assertNotFound();
        $this->assertSame(0, HiringAssessment::query()->count());

        // Entrevista de outra candidatura.
        $interviewB = $this->interview($questionnaire, ['talent_candidate_id' => $candidateB->id, 'hiring_application_id' => $applicationB->id]);
        $this->actingAs($owner)
            ->get(route('admin.contratacao.vagas.applications.interviews.show', [$vacancyA, $applicationA, $interviewB]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('admin.contratacao.vagas.applications.interviews.show', [$vacancyA, $applicationB, $interviewB]))
            ->assertRedirect(route('admin.entrevistas.show', $interviewB));
    }

    public function test_board_and_candidate_ficha_link_interviews_and_assessment(): void
    {
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate, $application, $vacancy] = $this->candidateWithApplication();
        $interview = $this->interview($questionnaire, ['talent_candidate_id' => $candidate->id, 'hiring_application_id' => $application->id]);

        $this->actingAs($owner)
            ->get(route('admin.contratacao.vagas.show', $vacancy))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('applications.0.interviews_count', 1)
                ->where('applications.0.interviews_url', route('admin.entrevistas.index', ['hiring_application_id' => $application->id]))
                ->where('applications.0.assessment_url', route('admin.contratacao.vagas.applications.assessment.show', [$vacancy, $application])));

        $this->actingAs($owner)
            ->get(route('admin.contratacao.candidatos.show', $candidate))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('interviews', 1)
                ->where('interviews.0.vacancy_title', $vacancy->title)
                ->where('interviews.0.is_reviewed', false)
                ->where('interviews.0.url', route('admin.entrevistas.show', $interview)));
    }
}
