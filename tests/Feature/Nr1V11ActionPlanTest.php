<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Exceptions\PsychosocialMeasureException;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialMeasureEvent;
use App\Models\PsychosocialMeasureEvidence;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\PsychosocialMeasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Etapa 6: plano de ação (Caderno §11). T16: a Talents sugere sem confirmar. T17: a empresa confirma responsável
 * e prazo, executa, salva prova e eficácia. Tudo com histórico antes/depois e só depois de "Publicado".
 */
class Nr1V11ActionPlanTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private PsychosocialMeasureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PsychosocialMeasureService::class);
        Storage::fake('local');
    }

    private function talents(): User
    {
        return User::factory()->superAdmin()->create(['is_owner' => true]);
    }

    /**
     * Campanha que exige publicação, ainda não publicada, com uma medida proposta pela Talents.
     *
     * @return object{company: mixed, survey: mixed, measure: PsychosocialMeasure, talents: User, admin: User}
     */
    private function plan(bool $published = false, bool $legacy = false): object
    {
        $campaign = $this->v11Campaign();
        $campaign->survey->forceFill([
            'requires_publication' => ! $legacy,
            'published_at' => $published ? now() : null,
        ])->save();

        $talents = $this->talents();
        $measure = $this->service->createManual($campaign->survey->fresh(), [
            'factor_label' => 'Demandas e Sobrecarga',
            'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
            'measure_description' => 'Revisar a distribuição das tarefas do setor.',
            'responsible_text' => 'Gestão de operações',
            'suggested_due_date' => now()->addDays(30)->toDateString(),
        ], $talents);

        return (object) [
            'company' => $campaign->company,
            'survey' => $campaign->survey->fresh(),
            'measure' => $measure,
            'talents' => $talents,
            'admin' => User::factory()->companyAdmin($campaign->company->id)->create(['name' => 'Ana Admin']),
        ];
    }

    /**
     * @param  list<PermissionAction>  $actions  permissões explícitas no módulo Planos de ação
     */
    private function companyUser(object $plan, array $actions): User
    {
        $user = User::factory()->companyUser($plan->company->id)->create();
        foreach ($actions as $action) {
            UserPermission::query()->create([
                'user_workspace_id' => $user->workspaces()->firstOrFail()->id,
                'module' => PermissionModule::PlanosAcao->value,
                'action' => $action->value,
            ]);
        }

        return $user;
    }

    private function url(string $name, object $plan, ?PsychosocialMeasure $measure = null): string
    {
        return route("client.surveys.measures.{$name}", [$plan->survey->id, ($measure ?? $plan->measure)->id]);
    }

    public function test_t16_talents_edits_suggested_responsible_and_due_date_without_confirming(): void
    {
        $plan = $this->plan();
        $newDue = now()->addDays(45)->toDateString();

        $this->actingAs($plan->talents)->patch(
            route('admin.companies.surveys.measures.suggestion', [$plan->company->id, $plan->survey->id, $plan->measure->id]),
            [
                'responsible_text' => 'Gerência de pessoas',
                'suggested_due_date' => $newDue,
                'suggested_due_origin' => 'talents',
                'suggested_due_note' => 'Depende do calendário de turnos.',
                'objective' => 'Reduzir a sobrecarga percebida.',
                'indicator' => 'Índice de Demandas e Sobrecarga',
                'reason' => 'Ajuste após reunião com a diretoria.',
            ],
        )->assertSessionHas('success');

        $measure = $plan->measure->fresh();
        $this->assertSame('Gerência de pessoas', $measure->responsible_text);
        $this->assertSame($newDue, $measure->suggested_due_date->toDateString());
        $this->assertSame('talents', $measure->suggested_due_origin);
        $this->assertSame(PsychosocialMeasure::STATUS_TO_APPROVE, $measure->status);
        $this->assertNull($measure->confirmed_at, 'Editar a sugestão não confirma.');
        $this->assertNull($measure->confirmed_by);
        $this->assertNull($measure->confirmed_due_date);
        $this->assertFalse($measure->hasConfirmedResponsible());

        $event = PsychosocialMeasureEvent::query()->where('measure_id', $measure->id)->where('field', 'responsible_text')->sole();
        $this->assertSame('Gestão de operações', $event->old_value);
        $this->assertSame('Gerência de pessoas', $event->new_value);
        $this->assertSame($plan->talents->id, (int) $event->user_id);
        $this->assertSame('Ajuste após reunião com a diretoria.', $event->reason);
    }

    public function test_talents_must_justify_replacing_an_existing_suggestion(): void
    {
        $plan = $this->plan();

        $this->actingAs($plan->talents)->patch(
            route('admin.companies.surveys.measures.suggestion', [$plan->company->id, $plan->survey->id, $plan->measure->id]),
            ['responsible_text' => 'Outra área'],
        )->assertSessionHas('error');

        $this->assertSame('Gestão de operações', $plan->measure->fresh()->responsible_text);
    }

    public function test_talents_cannot_edit_the_suggestion_after_publication_nor_confirm_for_the_company(): void
    {
        $plan = $this->plan(published: true);

        $this->actingAs($plan->talents)->patch(
            route('admin.companies.surveys.measures.suggestion', [$plan->company->id, $plan->survey->id, $plan->measure->id]),
            ['responsible_text' => 'Nova área', 'reason' => 'x'],
        )->assertSessionHas('error');
        $this->assertSame('Gestão de operações', $plan->measure->fresh()->responsible_text);

        $this->expectException(PsychosocialMeasureException::class);
        $this->service->confirm($plan->measure, $plan->talents, null, 'Talents decidindo pela empresa', now()->addDays(10)->toDateString());
    }

    public function test_company_cannot_act_before_publication(): void
    {
        $plan = $this->plan();

        $this->actingAs($plan->admin)->get(route('client.surveys.measures.index', $plan->survey))->assertNotFound();
        $this->actingAs($plan->admin)->post($this->url('confirm', $plan), [
            'responsible_text' => 'Coordenação',
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertNotFound();
        $this->assertNull($plan->measure->fresh()->confirmed_at);

        $this->expectException(PsychosocialMeasureException::class);
        $this->service->confirm($plan->measure, $plan->admin, null, 'Coordenação', now()->addDays(10)->toDateString());
    }

    public function test_t17_company_confirms_executes_proves_and_measures_with_full_history(): void
    {
        $plan = $this->plan(published: true);
        $due = now()->addDays(20)->toDateString();
        $responsible = User::factory()->companyUser($plan->company->id)->create(['name' => 'Bruno RH']);

        // Confirma responsável (usuário da empresa) e prazo.
        $this->actingAs($plan->admin)->post($this->url('confirm', $plan), [
            'responsible_user_id' => $responsible->id,
            'responsible_text' => 'RH · ramal 2200',
            'due_date' => $due,
        ])->assertSessionHas('success');

        $measure = $plan->measure->fresh();
        $this->assertSame(PsychosocialMeasure::STATUS_PENDING, $measure->status);
        $this->assertSame('Confirmada', $measure->planLabel());
        $this->assertSame($responsible->id, (int) $measure->confirmed_responsible_user_id);
        $this->assertSame('RH · ramal 2200', $measure->confirmed_responsible_text);
        $this->assertSame($due, $measure->confirmed_due_date->toDateString());
        $this->assertNotNull($measure->confirmed_at, 'Data de aprovação pela empresa.');
        $this->assertSame($plan->admin->id, (int) $measure->confirmed_by);
        $this->assertSame('Gestão de operações', $measure->responsible_text, 'O sugerido continua guardado.');

        // Não conclui sem evidência.
        $this->actingAs($plan->admin)->post($this->url('start', $plan))->assertSessionHas('success');
        $this->actingAs($plan->admin)->post($this->url('complete', $plan))->assertSessionHas('error');
        $this->assertSame(PsychosocialMeasure::STATUS_IN_PROGRESS, $measure->fresh()->status);

        // Prova: arquivo com hash.
        $file = UploadedFile::fake()->createWithContent('ata.pdf', '%PDF-1.4 ata da reunião');
        $this->actingAs($plan->admin)->post($this->url('evidences.store', $plan), [
            'description' => 'Ata da reunião de redistribuição de tarefas.',
            'file' => $file,
        ])->assertSessionHas('success');

        $evidence = PsychosocialMeasureEvidence::query()->sole();
        $this->assertSame('file', $evidence->kind);
        $this->assertSame(hash('sha256', '%PDF-1.4 ata da reunião'), $evidence->file_sha256);
        Storage::disk('local')->assertExists($evidence->file_path);

        $this->actingAs($plan->admin)
            ->get(route('client.surveys.measures.evidences.download', [$plan->survey->id, $measure->id, $evidence->id]))
            ->assertOk();

        // Conclui (exige data e usuário) e registra a eficácia.
        $this->actingAs($plan->admin)->post($this->url('complete', $plan))->assertSessionHas('success');
        $measure = $measure->fresh();
        $this->assertSame(PsychosocialMeasure::STATUS_IMPLEMENTED, $measure->status);
        $this->assertSame('Concluída', $measure->planLabel());
        $this->assertNotNull($measure->implemented_at);
        $this->assertSame($plan->admin->id, (int) $measure->implemented_by);

        $this->actingAs($plan->admin)->post($this->url('effectiveness', $plan), [
            'note' => 'O índice de Demandas caiu 0,3 ponto na reaplicação.',
            'result' => 'effective',
            'checked_at' => now()->toDateString(),
        ])->assertSessionHas('success');

        $measure = $measure->fresh();
        $this->assertSame('effective', $measure->effectiveness_result);
        $this->assertNotNull($measure->effectiveness_checked_at);
        $this->assertStringContainsString('caiu 0,3', (string) $measure->effectiveness_note);

        // Histórico preservado, em ordem, com autor.
        $events = PsychosocialMeasureEvent::query()->where('measure_id', $measure->id)->orderBy('id')->get();
        $fields = $events->pluck('field')->all();
        foreach (['created', 'confirmed_responsible', 'confirmed_due_date', 'status', 'evidence', 'effectiveness_note', 'effectiveness_result'] as $expected) {
            $this->assertContains($expected, $fields, "Evento {$expected} não registrado.");
        }
        $statusEvents = $events->where('field', 'status')->map(fn ($e) => [$e->old_value, $e->new_value])->values()->all();
        $this->assertSame([['to_approve', 'pending'], ['pending', 'in_progress'], ['in_progress', 'implemented']], $statusEvents);
        $this->assertSame($plan->admin->id, (int) $events->firstWhere('field', 'confirmed_responsible')->user_id);
    }

    public function test_status_cycle_only_follows_the_allowed_transitions(): void
    {
        $plan = $this->plan(published: true);
        $measure = $plan->measure;

        // Proposta não pula para andamento nem conclusão.
        foreach (['start', 'complete'] as $method) {
            try {
                $this->service->{$method}($measure, $plan->admin);
                $this->fail("Proposta → {$method} deveria ser recusado.");
            } catch (PsychosocialMeasureException) {
            }
        }
        try {
            $this->service->replan($measure, $plan->admin, now()->addDays(5)->toDateString(), 'x');
            $this->fail('Proposta não é replanejada.');
        } catch (PsychosocialMeasureException) {
        }

        $this->service->confirm($measure, $plan->admin, null, 'Coordenação', now()->addDays(10)->toDateString());
        // Confirmada não confirma de novo.
        try {
            $this->service->confirm($measure->fresh(), $plan->admin, null, 'Coordenação', now()->addDays(10)->toDateString());
            $this->fail('Confirmar duas vezes.');
        } catch (PsychosocialMeasureException) {
        }

        // Replanejar exige justificativa e prazo futuro.
        try {
            $this->service->replan($measure->fresh(), $plan->admin, now()->addDays(30)->toDateString(), '');
            $this->fail('Replanejar sem justificativa.');
        } catch (PsychosocialMeasureException) {
        }
        try {
            $this->service->replan($measure->fresh(), $plan->admin, now()->subDay()->toDateString(), 'Atraso');
            $this->fail('Replanejar com prazo no passado.');
        } catch (PsychosocialMeasureException) {
        }

        $newDue = now()->addDays(40)->toDateString();
        $this->service->replan($measure->fresh(), $plan->admin, $newDue, 'Equipe reduzida no período.');
        $replanned = $measure->fresh();
        $this->assertSame(PsychosocialMeasure::STATUS_REPLANNED, $replanned->status);
        $this->assertSame('Replanejada', $replanned->planLabel());
        $this->assertSame($newDue, $replanned->confirmed_due_date->toDateString());

        $dueEvent = PsychosocialMeasureEvent::query()->where('measure_id', $measure->id)->where('field', 'confirmed_due_date')->orderByDesc('id')->first();
        $this->assertSame($newDue, $dueEvent->new_value);
        $this->assertSame('Equipe reduzida no período.', $dueEvent->reason);

        // Replanejada volta a andamento, conclui com evidência e depois não replaneja.
        $this->service->start($replanned, $plan->admin);
        $this->service->addEvidence($measure->fresh(), $plan->admin, 'Registro em texto da execução.');
        $this->service->complete($measure->fresh(), $plan->admin);

        try {
            $this->service->replan($measure->fresh(), $plan->admin, now()->addDays(60)->toDateString(), 'Depois de concluída');
            $this->fail('Concluída não é replanejada.');
        } catch (PsychosocialMeasureException) {
        }
    }

    public function test_confirmation_validates_responsible_and_due_date(): void
    {
        $plan = $this->plan(published: true);
        $other = $this->createSurveyFixture();
        $outsider = User::factory()->companyUser($other->company->id)->create();

        $cases = [
            'sem responsável' => [null, null, now()->addDays(5)->toDateString()],
            'usuário de outra empresa' => [$outsider->id, null, now()->addDays(5)->toDateString()],
            'prazo no passado' => [null, 'Coordenação', now()->subDay()->toDateString()],
        ];

        foreach ($cases as $label => [$userId, $text, $due]) {
            try {
                $this->service->confirm($plan->measure->fresh(), $plan->admin, $userId, $text, $due);
                $this->fail("Confirmação inválida aceita: {$label}.");
            } catch (PsychosocialMeasureException) {
            }
        }

        $this->assertSame(PsychosocialMeasure::STATUS_TO_APPROVE, $plan->measure->fresh()->status);
        $this->assertNull($plan->measure->fresh()->confirmed_at);
    }

    public function test_adjusting_a_confirmed_plan_requires_a_reason_and_keeps_before_and_after(): void
    {
        $plan = $this->plan(published: true);
        $firstDue = now()->addDays(10)->toDateString();
        $this->service->confirm($plan->measure, $plan->admin, null, 'Coordenação de RH', $firstDue);

        try {
            $this->service->adjustConfirmation($plan->measure->fresh(), $plan->admin, null, 'Diretoria', now()->addDays(15)->toDateString(), '');
            $this->fail('Ajuste sem justificativa.');
        } catch (PsychosocialMeasureException) {
        }

        $secondDue = now()->addDays(15)->toDateString();
        $this->service->adjustConfirmation($plan->measure->fresh(), $plan->admin, null, 'Diretoria de operações', $secondDue, 'Responsável saiu da empresa.');

        $responsible = PsychosocialMeasureEvent::query()->where('field', 'confirmed_responsible')->orderByDesc('id')->first();
        $this->assertSame('Coordenação de RH', $responsible->old_value);
        $this->assertSame('Diretoria de operações', $responsible->new_value);
        $this->assertSame('Responsável saiu da empresa.', $responsible->reason);

        $due = PsychosocialMeasureEvent::query()->where('field', 'confirmed_due_date')->orderByDesc('id')->first();
        $this->assertSame($firstDue, $due->old_value);
        $this->assertSame($secondDue, $due->new_value);
    }

    public function test_events_and_evidences_are_immutable_and_confirmation_needs_both_pieces(): void
    {
        $plan = $this->plan(published: true);
        $this->service->confirm($plan->measure, $plan->admin, null, 'Coordenação', now()->addDays(10)->toDateString());
        $evidence = $this->service->addEvidence($plan->measure->fresh(), $plan->admin, 'Comprovação em texto.');

        foreach ([
            fn () => PsychosocialMeasureEvent::query()->firstOrFail()->update(['reason' => 'x']),
            fn () => PsychosocialMeasureEvent::query()->firstOrFail()->delete(),
            fn () => $evidence->update(['description' => 'x']),
            fn () => $evidence->delete(),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Registro de histórico ou evidência foi alterado.');
            } catch (LogicException) {
            }
        }

        $this->expectException(PsychosocialMeasureException::class);
        $plan->measure->fresh()->forceFill(['confirmed_due_date' => null])->save();
    }

    public function test_profile_matrix_for_the_company_plan(): void
    {
        $plan = $this->plan(published: true);
        $confirm = ['responsible_text' => 'Coordenação', 'due_date' => now()->addDays(10)->toDateString()];

        $editor = $this->companyUser($plan, [PermissionAction::View, PermissionAction::Edit]);
        $viewer = $this->companyUser($plan, [PermissionAction::View]);
        $none = $this->companyUser($plan, []);

        // Leitura: administrador, quem edita e quem só vê.
        foreach ([$plan->admin, $editor, $viewer] as $user) {
            $this->actingAs($user)->get(route('client.surveys.measures.index', $plan->survey))->assertOk();
        }
        // Sem o módulo: 403.
        $this->actingAs($none)->get(route('client.surveys.measures.index', $plan->survey))->assertForbidden();
        $this->actingAs($none)->post($this->url('confirm', $plan), $confirm)->assertForbidden();

        // Só quem tem a ação `edit` escreve.
        $this->actingAs($viewer)->post($this->url('confirm', $plan), $confirm)->assertForbidden();
        $this->assertNull($plan->measure->fresh()->confirmed_at);
        $this->actingAs($editor)->post($this->url('confirm', $plan), $confirm)->assertSessionHas('success');
        $this->assertNotNull($plan->measure->fresh()->confirmed_at);

        // Talents (owner) e comercial não usam a tela da empresa.
        foreach ([$plan->talents, User::factory()->superAdmin()->create()] as $user) {
            $status = $this->actingAs($user)->get(route('client.surveys.measures.index', $plan->survey))->getStatusCode();
            $this->assertContains($status, [302, 403, 404]);
        }
    }

    public function test_company_page_shows_suggested_and_confirmed_with_plan_labels(): void
    {
        $plan = $this->plan(published: true);
        $this->service->confirm($plan->measure, $plan->admin, null, 'Coordenação de RH', now()->addDays(10)->toDateString());

        $this->actingAs($plan->admin)->get(route('client.surveys.measures.index', $plan->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Surveys/ActionPlanMeasures')
                ->where('canEdit', true)
                ->where('cardLabel', 'Medidas com responsável confirmado')
                ->has('measures', 1)
                ->where('measures.0.status_label', 'Confirmada')
                ->where('measures.0.suggested.responsible', 'Gestão de operações')
                ->where('measures.0.confirmed.responsible', 'Coordenação de RH')
                ->has('measures.0.history'));
    }

    public function test_idor_measures_of_other_surveys_or_companies_are_not_found(): void
    {
        $plan = $this->plan(published: true);
        $other = $this->plan(published: true);
        $confirm = ['responsible_text' => 'Coordenação', 'due_date' => now()->addDays(10)->toDateString()];

        // Medida de outra empresa na URL da minha campanha.
        $this->actingAs($plan->admin)
            ->post(route('client.surveys.measures.confirm', [$plan->survey->id, $other->measure->id]), $confirm)
            ->assertNotFound();
        // Minha medida na URL da campanha de outra empresa.
        $this->actingAs($plan->admin)
            ->post(route('client.surveys.measures.confirm', [$other->survey->id, $plan->measure->id]), $confirm)
            ->assertNotFound();
        $this->actingAs($plan->admin)->get(route('client.surveys.measures.index', $other->survey))->assertNotFound();

        $this->assertNull($other->measure->fresh()->confirmed_at);
        $this->assertNull($plan->measure->fresh()->confirmed_at);

        // Evidência de outra empresa não baixa.
        $this->service->confirm($other->measure, $other->admin, null, 'Outra', now()->addDays(10)->toDateString());
        $file = UploadedFile::fake()->createWithContent('x.pdf', 'conteudo');
        $this->actingAs($other->admin)->post(route('client.surveys.measures.evidences.store', [$other->survey->id, $other->measure->id]), [
            'description' => 'Arquivo', 'file' => $file,
        ])->assertSessionHas('success');
        $evidence = PsychosocialMeasureEvidence::query()->sole();

        $this->actingAs($plan->admin)
            ->get(route('client.surveys.measures.evidences.download', [$plan->survey->id, $plan->measure->id, $evidence->id]))
            ->assertNotFound();
    }

    public function test_dashboard_card_counts_measures_with_confirmed_responsible(): void
    {
        $plan = $this->plan(published: true);
        $second = $this->service->createManual($plan->survey, [
            'factor_label' => 'Segurança Psicológica',
            'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
            'measure_description' => 'Canal de escuta com a liderança.',
        ], $plan->talents);

        $builder = fn () => app(PsychosocialDashboardBuilder::class)->build($plan->survey->fresh())['cards']['plan'];
        $this->assertSame(['confirmed' => 0, 'implemented' => 0, 'total' => 2], $builder());

        $this->service->confirm($plan->measure, $plan->admin, null, 'Coordenação', now()->addDays(10)->toDateString());
        $this->assertSame(['confirmed' => 1, 'implemented' => 0, 'total' => 2], $builder());
        $this->assertNotNull($second);
    }

    public function test_campaigns_that_predate_the_gate_stay_open_for_both_sides(): void
    {
        $plan = $this->plan(legacy: true);

        $this->service->updateSuggestion($plan->measure, ['responsible_text' => 'Nova área'], $plan->talents, 'Ajuste');
        $this->service->confirm($plan->measure->fresh(), $plan->admin, null, 'Coordenação', now()->addDays(10)->toDateString());

        $this->assertSame(PsychosocialMeasure::STATUS_PENDING, $plan->measure->fresh()->status);
    }

    public function test_legacy_labels_and_free_admin_status_flow_are_unchanged(): void
    {
        $plan = $this->plan();

        $this->assertSame('A aprovar', $plan->measure->statusLabel());
        $this->assertSame('Proposta', $plan->measure->planLabel());
        $this->assertSame('Implantada', PsychosocialMeasure::STATUS_LABELS[PsychosocialMeasure::STATUS_IMPLEMENTED]);

        $this->actingAs($plan->talents)->patch(
            route('admin.companies.surveys.measures.status', [$plan->company->id, $plan->survey->id, $plan->measure->id]),
            ['status' => PsychosocialMeasure::STATUS_IMPLEMENTED],
        )->assertSessionHas('success');
        $this->assertSame(PsychosocialMeasure::STATUS_IMPLEMENTED, $plan->measure->fresh()->status);
    }

    public function test_admin_page_exposes_the_plan_fields_and_locks_suggestions_after_publication(): void
    {
        $plan = $this->plan();
        $url = route('admin.companies.surveys.measures.edit', [$plan->company->id, $plan->survey->id]);

        $this->actingAs($plan->talents)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canEditSuggestion', true)
            ->where('measures.0.plan.status_label', 'Proposta')
            ->where('measures.0.plan.suggested.responsible', 'Gestão de operações')
            ->where('measures.0.plan.confirmed', null));

        $plan->survey->forceFill(['published_at' => now()])->save();

        $this->actingAs($plan->talents)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canEditSuggestion', false));
    }
}
