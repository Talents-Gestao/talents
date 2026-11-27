<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\SyncAdminUserPermissions;
use App\Models\AiSetting;
use App\Models\Company;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialMeasureEvent;
use App\Models\PsychosocialMeasureEvidence;
use App\Models\Survey;
use App\Models\User;
use App\Services\ActionMeasuresGenerator;
use App\Services\Nr1AiAnalyzer;
use App\Services\PsychosocialMeasureDeduplicator;
use App\Services\PsychosocialMeasureService;
use App\Support\Nr1Shell\Nr1ShellPageData;
use Database\Seeders\Nr1DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Uma medida por dimensão no plano de ação (PsychosocialMeasureDeduplicator): pares de origens diferentes detectados
 * e unificados pela ação da Talents (também após a publicação), sem nunca remover medida manual, editada, confirmada
 * ou com evidência; «Nova medida» recusa dimensão repetida; o gerador junta na hora; o comando segue a mesma regra.
 */
class Nr1MeasureDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Survey $survey;

    private User $owner;

    /** @var list<string> */
    private array $exportsBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Nr1DemoSeeder::class);
        $this->company = Company::query()->where('name', Nr1DemoSeeder::COMPANY)->firstOrFail();
        // Campanha publicada: a ação continua disponível para a Talents.
        $this->survey = Survey::query()->where('company_id', $this->company->id)->where('title', 'Campanha 2026')->firstOrFail();
        $this->assertNotNull($this->survey->published_at);
        $this->owner = User::query()->where('email', 'demo-owner@talents.local')->firstOrFail();
        $this->exportsBefore = glob(storage_path('app/nr1-exports/*.json')) ?: [];
    }

    protected function tearDown(): void
    {
        foreach (array_diff(glob(storage_path('app/nr1-exports/*.json')) ?: [], $this->exportsBefore) as $file) {
            File::delete($file);
        }
        parent::tearDown();
    }

    private function engine(string $dimension): PsychosocialMeasure
    {
        return PsychosocialMeasure::query()->where('survey_id', $this->survey->id)->where('factor_label', $dimension)->firstOrFail();
    }

    /** Medida manual antiga (criada antes da trava de «Nova medida»), com o evento de criação da pessoa. */
    private function legacyManual(Survey $survey, string $factor, string $text): PsychosocialMeasure
    {
        $measure = PsychosocialMeasure::query()->create([
            'survey_id' => $survey->id, 'company_id' => $survey->company_id, 'origin' => PsychosocialMeasure::ORIGIN_MANUAL,
            'status' => PsychosocialMeasure::STATUS_TO_APPROVE, 'factor_label' => $factor, 'measure_description' => $text,
            'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
        ]);
        PsychosocialMeasureEvent::query()->create(['measure_id' => $measure->id, 'user_id' => $this->owner->id, 'field' => PsychosocialMeasureEvent::FIELD_CREATED, 'new_value' => $measure->status]);

        return $measure;
    }

    /** @return array<string, mixed>|null */
    private function duplicatesFor(User $user, bool $forTalents = true): ?array
    {
        return app(Nr1ShellPageData::class)->plan($this->survey->fresh(), $user, $forTalents)['duplicates'];
    }

    private function unify(User $user, ?array $expected = null, ?Survey $survey = null, ?Company $company = null)
    {
        $survey ??= $this->survey;

        return $this->actingAs($user)->from(route('nr1.plano-de-acao'))->patch(
            route('admin.companies.surveys.measures.unify-duplicates', [($company ?? $this->company)->id, $survey->id]),
            ['confirm' => true, 'expected_ids' => $expected ?? app(PsychosocialMeasureDeduplicator::class)->removableIds($survey)],
        );
    }

    private function adminWith(array $permissions): User
    {
        $user = User::factory()->superAdmin()->create();
        app(SyncAdminUserPermissions::class)->execute($user->talentsWorkspace(), array_map(fn (array $p) => ['module' => $p[0], 'action' => $p[1]], $permissions));

        return $user->fresh();
    }

    public function test_cross_origin_pair_is_detected_and_unified_keeping_the_manual(): void
    {
        $engine = $this->engine('Esgotamento e Saúde Emocional');
        $manual = $this->legacyManual($this->survey, 'ESGOTAMENTO E SAÚDE EMOCIONAL', 'Medida da Talents.');

        $duplicates = $this->duplicatesFor($this->owner);
        $this->assertCount(1, $duplicates['groups']);
        $group = $duplicates['groups'][0];
        $this->assertSame('Esgotamento e Saúde Emocional', $group['dimension']);
        $this->assertSame(PsychosocialMeasureDeduplicator::DECISION_MERGE, $group['decision']);
        $this->assertSame($manual->id, $group['kept']['id']);
        $this->assertSame([$engine->id], array_column($group['removed'], 'id'));
        $this->assertSame([], $group['manual']);
        $this->assertSame([$engine->id], $duplicates['removable_ids']);

        $this->actingAs($this->owner)->get(route('nr1.plano-de-acao', ['empresa' => $this->company->id, 'campanha' => $this->survey->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('section.plan.duplicates.removable_ids', [$engine->id]));

        $this->unify($this->owner)->assertSessionHas('success');

        $this->assertNull(PsychosocialMeasure::query()->find($engine->id));
        $kept = $manual->fresh();
        $this->assertSame('Medida da Talents.', $kept->measure_description, 'texto da manual nunca muda');
        $merge = $kept->events()->where('field', 'merge')->sole();
        $this->assertSame($this->owner->id, $merge->user_id);
        $this->assertSame('Junção de sugestões duplicadas', $merge->reason);
        $this->assertSame(['removed_ids' => [$engine->id], 'text_merged_into_kept' => false], json_decode($merge->new_value, true), 'auditoria só com IDs, sem conteúdo');
        $this->assertFalse(app(PsychosocialMeasureService::class)->isEditedByTalents($kept), 'a junção não conta como edição');
        $this->assertNull($this->duplicatesFor($this->owner), 'sem duplicata, sem botão');
    }

    public function test_two_untouched_engine_suggestions_merge_the_action_into_the_kept_one(): void
    {
        $first = $this->engine('Demandas e Sobrecarga');
        $second = app(PsychosocialMeasureService::class)->createEngineSuggestion($this->survey, ['factor_label' => 'DEMANDAS E SOBRECARGA', 'measure_description' => 'Mapear picos de trabalho.']);

        $this->unify($this->owner)->assertSessionHas('success');

        $this->assertNull(PsychosocialMeasure::query()->find($second->id));
        $this->assertStringEndsWith('Mapear picos de trabalho.', $first->fresh()->measure_description);
    }

    public function test_edited_confirmed_or_with_evidence_is_never_removed(): void
    {
        $service = app(PsychosocialMeasureService::class);
        $edited = $this->engine('Liderança e Gestão');
        $service->update($edited, ['measure_description' => 'Escrito por pessoa.'], $this->owner);
        $withEvidence = $service->createEngineSuggestion($this->survey, ['factor_label' => 'liderança e gestão', 'measure_description' => 'Com evidência.']);
        PsychosocialMeasureEvidence::query()->create(['measure_id' => $withEvidence->id, 'kind' => PsychosocialMeasureEvidence::KIND_TEXT, 'description' => 'Ata']);
        $confirmed = $service->createEngineSuggestion($this->survey, ['factor_label' => 'LIDERANÇA E GESTÃO', 'measure_description' => 'Confirmada.']);
        PsychosocialMeasure::query()->whereKey($confirmed->id)->update(['status' => PsychosocialMeasure::STATUS_PENDING]);
        $manual = $this->legacyManual($this->survey, 'Liderança e Gestão', 'Manual.');

        $group = $this->duplicatesFor($this->owner)['groups'][0];
        $this->assertSame(PsychosocialMeasureDeduplicator::DECISION_MANUAL, $group['decision']);
        $this->assertSame($confirmed->id, $group['kept']['id'], 'a que já saiu de «A aprovar» é a mantida');
        $this->assertSame([], $group['removed']);
        $this->assertEqualsCanonicalizing([$edited->id, $withEvidence->id, $manual->id], array_column($group['manual'], 'id'));

        $this->unify($this->owner, [])->assertSessionHas('success', 'Nenhuma sugestão foi removida: as duplicatas restantes pedem decisão manual.');
        foreach ([$edited, $withEvidence, $confirmed, $manual] as $measure) {
            $this->assertNotNull($measure->fresh());
        }
        $this->assertSame('Escrito por pessoa.', $edited->fresh()->measure_description);
    }

    public function test_stale_preview_changes_nothing(): void
    {
        $engine = $this->engine('Esgotamento e Saúde Emocional');
        $this->legacyManual($this->survey, 'Esgotamento e Saúde Emocional', 'Manual.');

        $this->unify($this->owner, [$engine->id + 999])->assertSessionHas('error');
        $this->assertNotNull($engine->fresh());

        $this->actingAs($this->owner)->patch(route('admin.companies.surveys.measures.unify-duplicates', [$this->company->id, $this->survey->id]), ['expected_ids' => [$engine->id]])
            ->assertSessionHasErrors('confirm');
        $this->assertNotNull($engine->fresh());
    }

    public function test_button_is_visible_to_talents_in_a_published_campaign_and_hidden_from_everyone_else(): void
    {
        $engine = $this->engine('Esgotamento e Saúde Emocional');
        $this->legacyManual($this->survey, 'Esgotamento e Saúde Emocional', 'Manual.');

        $this->assertFalse(app(Nr1ShellPageData::class)->plan($this->survey->fresh(), $this->owner, true)['canEditSuggestion'], 'publicada: edição de sugestão travada');
        $this->assertNotNull($this->duplicatesFor($this->owner), 'a ação não depende de canEditSuggestion');

        $editor = $this->adminWith([['companies', 'view'], ['companies', 'edit']]);
        $this->assertNotNull($this->duplicatesFor($editor));

        $viewer = $this->adminWith([['companies', 'view']]);
        $this->assertNull($this->duplicatesFor($viewer));
        $this->unify($viewer)->assertForbidden();

        $companyAdmin = User::query()->where('email', 'demo-rh@demo-nr1.local')->firstOrFail();
        $this->assertNull($this->duplicatesFor($companyAdmin, false));
        $this->actingAs($companyAdmin)->get(route('nr1.plano-de-acao', ['campanha' => $this->survey->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('section.plan.duplicates', null));
        $this->unify($companyAdmin)->assertForbidden();

        $this->assertNotNull($engine->fresh());
    }

    public function test_unification_is_isolated_per_company_and_campaign(): void
    {
        $other = Company::query()->create(['name' => 'Outra Empresa', 'is_active' => true]);
        $engine = $this->engine('Esgotamento e Saúde Emocional');
        $this->legacyManual($this->survey, 'Esgotamento e Saúde Emocional', 'Manual.');

        // Empresa errada na URL: 404, nada muda.
        $this->unify($this->owner, null, $this->survey, $other)->assertNotFound();
        $this->assertNotNull($engine->fresh());

        // Outra campanha da mesma empresa com a mesma dimensão não forma grupo com esta.
        $second = Survey::query()->where('company_id', $this->company->id)->where('title', '!=', 'Campanha 2026')->firstOrFail();
        $foreign = app(PsychosocialMeasureService::class)->createEngineSuggestion($second, ['factor_label' => 'Esgotamento e Saúde Emocional', 'measure_description' => 'Outra campanha.']);
        $this->unify($this->owner)->assertSessionHas('success');
        $this->assertNull($engine->fresh());
        $this->assertNotNull($foreign->fresh());
    }

    public function test_new_manual_measure_in_a_dimension_that_already_has_one_is_refused_on_the_server(): void
    {
        $before = PsychosocialMeasure::query()->where('survey_id', $this->survey->id)->count();
        $store = fn (string $factor) => $this->actingAs($this->owner)->from('/admin')->post(route('admin.companies.surveys.measures.store', [$this->company->id, $this->survey->id]), [
            'factor_label' => $factor, 'measure_description' => 'Nova medida.', 'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
        ]);

        $store('demandas e sobrecarga')->assertSessionHas('error', 'Esta campanha já tem uma medida para a dimensão «Demandas e Sobrecarga». Edite o cartão existente em vez de criar outra.');
        $this->assertSame($before, PsychosocialMeasure::query()->where('survey_id', $this->survey->id)->count());

        $store('Autonomia e Organização')->assertSessionHas('success');
        $this->assertSame($before + 1, PsychosocialMeasure::query()->where('survey_id', $this->survey->id)->count());

        // Mover uma sugestão para uma dimensão ocupada também é recusado.
        $this->expectExceptionMessage('Esta campanha já tem uma medida para a dimensão «Liderança e Gestão».');
        $this->survey->forceFill(['published_at' => null])->save();
        app(PsychosocialMeasureService::class)->updateSuggestion($this->engine('Demandas e Sobrecarga'), ['factor_label' => 'LIDERANÇA E GESTÃO'], $this->owner, 'Troca de dimensão');
    }

    public function test_generator_merges_untouched_duplicates_right_away(): void
    {
        $service = app(PsychosocialMeasureService::class);
        $service->createEngineSuggestion($this->survey, ['factor_label' => 'DEMANDAS E SOBRECARGA', 'measure_description' => 'Antiga duplicada.']);
        $manual = $this->legacyManual($this->survey, 'Esgotamento e saúde emocional', 'Manual.');
        $engineEsgotamento = $this->engine('Esgotamento e Saúde Emocional');
        AiSetting::query()->create(['provider' => 'openai', 'model' => 'gpt-test', 'is_enabled' => true, 'api_key' => 'chave-de-teste', 'max_tokens' => 800, 'temperature' => 0.2]);
        $this->app->instance(Nr1AiAnalyzer::class, new class extends Nr1AiAnalyzer
        {
            public function __construct() {}

            public function generateNarrative(...$args): array
            {
                return ['content' => json_encode(['measures' => [
                    ['factor_label' => 'Demandas e Sobrecarga', 'measure_description' => 'Revisar metas.', 'suggested_due_days' => 30],
                    ['factor_label' => 'Esgotamento e Saúde Emocional', 'measure_description' => 'Planejar pausas.', 'suggested_due_days' => 30],
                ]]), 'prompt_tokens' => 1, 'completion_tokens' => 1, 'model_used' => 'falso'];
            }
        });

        app(ActionMeasuresGenerator::class)->generate($this->survey->fresh(), AiSetting::current());

        $this->assertSame([], app(PsychosocialMeasureDeduplicator::class)->groupsForSurvey($this->survey), 'nenhuma duplicata depois de gerar');
        $this->assertNotNull($manual->fresh());
        $this->assertNull($engineEsgotamento->fresh(), 'a sugestão intocada ao lado da manual sai');
    }

    public function test_command_dry_run_changes_nothing_and_apply_follows_the_same_rule(): void
    {
        $engine = $this->engine('Esgotamento e Saúde Emocional');
        $manual = $this->legacyManual($this->survey, 'ESGOTAMENTO E SAÚDE EMOCIONAL', 'Manual.');
        $snapshot = fn () => PsychosocialMeasure::query()->orderBy('id')->get(['id', 'measure_description', 'status'])->toArray() + ['events' => PsychosocialMeasureEvent::query()->count()];
        $before = $snapshot();

        $this->artisan('nr1:dedupe-engine-measures', ['--survey' => $this->survey->id])
            ->expectsOutputToContain('Modo: dry-run')
            ->expectsOutputToContain('Remover #'.$engine->id)
            ->expectsOutputToContain('Seriam removidas: 1')
            ->assertSuccessful();
        $this->assertSame($before, $snapshot());

        $this->artisan('nr1:dedupe-engine-measures', ['--apply' => true, '--survey' => $this->survey->id])->expectsOutputToContain('Removidas: 1')->assertSuccessful();
        $this->assertNull($engine->fresh());
        $this->assertNotNull($manual->fresh());
        $this->assertNull($manual->events()->where('field', 'merge')->sole()->user_id, 'comando grava a junção sem autor');
    }
}
