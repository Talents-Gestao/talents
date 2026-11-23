<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialMeasureEvent;
use App\Models\PsychosocialMeasureEvidence;
use App\Models\Survey;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\ActionMeasuresGenerator;
use App\Services\Nr1AiAnalyzer;
use App\Services\PsychosocialMeasureService;
use App\Support\Nr1MeasureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

/**
 * Parte C do plano de ação V11: nr1:dedupe-engine-measures (dry-run, junção, decisão manual), nr1:backfill-engine-measures
 * (só campos vazios, só V11 «A aprovar») e o catálogo ligado ao gerador (V11 sim; modelo anterior e manuais não).
 */
class EngineMeasuresCleanupTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    /** @var list<string> */
    private array $exportsBefore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportsBefore = glob(storage_path('app/nr1-exports/*.json')) ?: [];
    }

    protected function tearDown(): void
    {
        // Só os exports criados por este teste.
        foreach (array_diff(glob(storage_path('app/nr1-exports/*.json')) ?: [], $this->exportsBefore) as $file) {
            File::delete($file);
        }
        parent::tearDown();
    }

    private function service(): PsychosocialMeasureService
    {
        return app(PsychosocialMeasureService::class);
    }

    private function survey(bool $v11 = true): Survey
    {
        $fx = $this->createSurveyFixture();
        $code = $v11 ? SurveyMethodVersion::CODE_TALENTS39_V1 : SurveyMethodVersion::CODE_PRODUCTION_V1;
        $fx->survey->forceFill([
            'method_version_id' => SurveyMethodVersion::query()->where('code', $code)->value('id'),
            'ends_at' => now()->subDay(),
            'status' => 'closed',
        ])->save();
        $fx->section->forceFill(['title' => 'Demandas e Sobrecarga'])->save();
        SurveyResult::query()->create(['survey_id' => $fx->survey->id, 'survey_template_section_id' => null, 'department_id' => null, 'average_score' => '2.800', 'risk_level' => 'yellow', 'respondent_count' => 12]);
        SurveyResult::query()->create(['survey_id' => $fx->survey->id, 'survey_template_section_id' => $fx->section->id, 'department_id' => null, 'average_score' => '4.100', 'risk_level' => 'red', 'respondent_count' => 12]);

        return $fx->survey->fresh();
    }

    private function suggestion(Survey $survey, string $factor, string $text, array $extra = []): PsychosocialMeasure
    {
        return $this->service()->createEngineSuggestion($survey, ['factor_label' => $factor, 'measure_description' => $text] + $extra);
    }

    /** @return array<string, mixed> Estado das medidas e eventos para comparar antes e depois. */
    private function snapshot(): array
    {
        return [
            'measures' => PsychosocialMeasure::query()->orderBy('id')->get()->map->only(['id', 'status', 'measure_description', 'objective', 'indicator', 'responsible_text', 'suggested_due_note'])->all(),
            'events' => PsychosocialMeasureEvent::query()->count(),
        ];
    }

    private function latestExport(string $prefix): array
    {
        $files = array_values(array_filter(glob(storage_path("app/nr1-exports/{$prefix}-*.json")) ?: [], fn ($f) => ! in_array($f, $this->exportsBefore, true)));
        $this->assertNotEmpty($files, 'export gravado em storage/app/nr1-exports');
        sort($files);

        return json_decode((string) file_get_contents(end($files)), true);
    }

    // ── nr1:dedupe-engine-measures ──────────────────────────────────────

    public function test_dedupe_dry_run_changes_nothing_and_exports_full_content(): void
    {
        $survey = $this->survey();
        $this->suggestion($survey, 'Demandas e Sobrecarga', 'Revisar metas.');
        $this->suggestion($survey, 'DEMANDAS E SOBRECARGA', 'Mapear picos.');
        $before = $this->snapshot();

        $this->artisan('nr1:dedupe-engine-measures')->expectsOutputToContain('dry-run')->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $export = $this->latestExport('dedupe-engine-measures');
        $this->assertFalse($export['apply']);
        $this->assertSame('Mapear picos.', $export['groups'][0]['remove'][0]['measure_description']);
        $this->assertSame('Revisar metas. Mapear picos.', $export['groups'][0]['keep']['merged_description']);
        $this->assertNotEmpty($export['groups'][0]['remove'][0]['events']);
    }

    public function test_dedupe_apply_keeps_the_right_measure_and_records_the_merge(): void
    {
        $survey = $this->survey();
        $older = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Revisar metas.');
        $fuller = $this->suggestion($survey, 'Demandas e sobrecarga', 'Mapear picos.', ['objective' => 'Equilibrar demandas.', 'indicator' => 'Percentual de equipes']);

        $this->artisan('nr1:dedupe-engine-measures', ['--apply' => true])->assertSuccessful();

        $this->assertNull(PsychosocialMeasure::query()->find($older->id), 'sai a menos completa');
        $kept = $fuller->fresh();
        $this->assertSame('Mapear picos. Revisar metas.', $kept->measure_description, 'mantida intocada recebe a ação');
        $merge = $kept->events()->where('field', 'merge')->sole();
        $this->assertNull($merge->user_id);
        $summary = json_decode($merge->new_value, true);
        $this->assertSame([$older->id], $summary['removed_ids']);
        $this->assertSame('Revisar metas.', $summary['removed_actions'][0]['measure_description']);
        $this->assertTrue($this->latestExport('dedupe-engine-measures')['apply']);
    }

    public function test_discarded_with_evidence_or_human_change_is_kept_and_listed(): void
    {
        $survey = $this->survey();
        // A mantida já saiu de «A aprovar» (vence a editada pela ordem de manutenção).
        $keeper = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Revisar metas.');
        PsychosocialMeasure::query()->whereKey($keeper->id)->update(['status' => PsychosocialMeasure::STATUS_PENDING]);
        $withEvidence = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Com evidência.');
        PsychosocialMeasureEvidence::query()->create(['measure_id' => $withEvidence->id, 'kind' => PsychosocialMeasureEvidence::KIND_TEXT, 'description' => 'Ata da reunião']);
        $withAuthor = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Editada.');
        $this->service()->update($withAuthor, ['expected_result' => 'Escrito por pessoa'], User::factory()->create());

        $this->artisan('nr1:dedupe-engine-measures', ['--apply' => true])
            ->expectsOutputToContain('Requer decisão manual #'.$withEvidence->id)
            ->expectsOutputToContain('Requer decisão manual #'.$withAuthor->id)
            ->assertSuccessful();

        $this->assertSame(3, PsychosocialMeasure::query()->count());
        $this->assertSame(0, $keeper->events()->where('field', 'merge')->count(), 'nada removido, sem junção');
        $this->assertSame('Revisar metas.', $keeper->fresh()->measure_description);
    }

    public function test_group_with_two_advanced_measures_is_left_untouched(): void
    {
        $survey = $this->survey();
        $a = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Primeira.');
        $b = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Segunda.');
        $c = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Terceira.');
        PsychosocialMeasure::query()->whereKey([$a->id, $b->id])->update(['status' => PsychosocialMeasure::STATUS_PENDING]);
        $before = $this->snapshot();

        $this->artisan('nr1:dedupe-engine-measures', ['--apply' => true])->expectsOutputToContain('requer decisão manual')->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertNotNull($c->fresh());
    }

    public function test_confirmed_keeper_text_is_never_changed_the_action_goes_to_the_merge_event(): void
    {
        $survey = $this->survey();
        $confirmed = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Revisar metas.');
        PsychosocialMeasure::query()->whereKey($confirmed->id)->update(['status' => PsychosocialMeasure::STATUS_PENDING]);
        $duplicate = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Mapear picos.', ['objective' => 'a', 'indicator' => 'b', 'expected_result' => 'c']);

        $this->artisan('nr1:dedupe-engine-measures', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Revisar metas.', $confirmed->fresh()->measure_description);
        $this->assertNull(PsychosocialMeasure::query()->find($duplicate->id));
        $summary = json_decode($confirmed->events()->where('field', 'merge')->sole()->new_value, true);
        $this->assertFalse($summary['text_merged_into_kept']);
        $this->assertSame('Mapear picos.', $summary['removed_actions'][0]['measure_description']);
    }

    public function test_dedupe_never_mixes_companies(): void
    {
        $a = $this->survey();
        $b = $this->survey();
        $this->assertNotSame($a->company_id, $b->company_id);
        $this->suggestion($a, 'Demandas e Sobrecarga', 'Empresa A.');
        $this->suggestion($b, 'Demandas e Sobrecarga', 'Empresa B.');
        $before = $this->snapshot();

        $this->artisan('nr1:dedupe-engine-measures', ['--apply' => true])->expectsOutputToContain('Grupos duplicados: 0')->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
    }

    // ── nr1:backfill-engine-measures ────────────────────────────────────

    public function test_backfill_fills_only_empty_fields_of_v11_engine_suggestions_to_approve(): void
    {
        $survey = $this->survey();
        $target = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Revisar metas.', ['objective' => 'Objetivo da Talents.', 'suggested_due_date' => now()->addDays(45)->toDateString()]);
        $confirmed = $this->suggestion($survey, 'Esgotamento e Saúde Emocional', 'Pausas.');
        PsychosocialMeasure::query()->whereKey($confirmed->id)->update(['status' => PsychosocialMeasure::STATUS_PENDING]);
        $manual = $this->service()->createManual($survey, ['factor_label' => 'Liderança e Gestão', 'measure_description' => 'Formar líderes.', 'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION], User::factory()->create());
        $legacy = $this->survey(false);
        $copsoq = $this->suggestion($legacy, 'Demandas e Sobrecarga', 'Modelo anterior.');
        $before = $this->snapshot();

        $this->artisan('nr1:backfill-engine-measures')->expectsOutputToContain('dry-run')->assertSuccessful();
        $this->assertSame($before, $this->snapshot(), 'dry-run não altera nada');

        $this->artisan('nr1:backfill-engine-measures', ['--apply' => true])->assertSuccessful();

        $target->refresh();
        $entry = Nr1MeasureCatalog::V11['Demandas e Sobrecarga'];
        $this->assertSame('Objetivo da Talents.', $target->objective, 'nunca sobrescreve');
        $this->assertSame($entry['indicator'], $target->indicator);
        $this->assertSame($entry['expected_result'], $target->expected_result);
        $this->assertSame($entry['responsible'], $target->responsible_text);
        $this->assertSame('talents', $target->suggested_due_origin);
        $this->assertStringContainsString('Prazo de 45 dias contados da geração da sugestão', (string) $target->suggested_due_note);
        $this->assertStringStartsWith('Faixa da dimensão: ', (string) $target->suggested_due_note);
        $this->assertSame(5, $target->events()->where('reason', 'Preenchimento automático do catálogo')->whereNull('user_id')->count());
        $this->assertFalse($this->service()->isEditedByTalents($target), 'preenchimento automático não conta como edição');

        foreach ([$confirmed, $manual, $copsoq] as $untouched) {
            $this->assertNull($untouched->fresh()->indicator);
        }
    }

    // ── Catálogo ligado ao gerador ──────────────────────────────────────

    private function fakeAi(array $measures): void
    {
        $this->app->instance(Nr1AiAnalyzer::class, new class($measures) extends Nr1AiAnalyzer
        {
            public function __construct(private readonly array $measures) {}

            public function generateNarrative(...$args): array
            {
                return ['content' => json_encode(['measures' => $this->measures]), 'prompt_tokens' => 1, 'completion_tokens' => 1, 'model_used' => 'falso'];
            }
        });
        AiSetting::query()->firstOrCreate(['provider' => 'openai'], ['model' => 'gpt-test', 'is_enabled' => true, 'api_key' => 'chave-de-teste', 'max_tokens' => 800, 'temperature' => 0.2]);
    }

    public function test_generator_fills_the_catalog_in_new_v11_suggestions_only(): void
    {
        $this->fakeAi([['factor_label' => 'Demandas e Sobrecarga', 'measure_description' => 'Revisar metas.', 'suggested_due_days' => 30]]);
        $v11 = $this->survey();
        $legacy = $this->survey(false);

        app(ActionMeasuresGenerator::class)->generate($v11, AiSetting::current());
        app(ActionMeasuresGenerator::class)->generate($legacy, AiSetting::current());

        $filled = PsychosocialMeasure::query()->where('survey_id', $v11->id)->sole();
        $entry = Nr1MeasureCatalog::V11['Demandas e Sobrecarga'];
        $this->assertSame($entry['objective'], $filled->objective);
        $this->assertSame($entry['responsible'], $filled->responsible_text);
        $this->assertSame(PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION, $filled->origin);
        $this->assertSame(PsychosocialMeasure::STATUS_TO_APPROVE, $filled->status);
        $this->assertNull($filled->confirmed_responsible_text, 'sugestão, não aceite');
        $this->assertSame(Nr1MeasureCatalog::dueNote('Maior sinalização', 30), $filled->suggested_due_note);

        $plain = PsychosocialMeasure::query()->where('survey_id', $legacy->id)->sole();
        $this->assertNull($plain->objective);
        $this->assertNull($plain->responsible_text);
        $this->assertNull($plain->suggested_due_note);
    }

    public function test_regenerating_never_overwrites_catalog_fields_already_filled(): void
    {
        $this->fakeAi([['factor_label' => 'Demandas e Sobrecarga', 'measure_description' => 'Revisar metas.', 'suggested_due_days' => 30]]);
        $survey = $this->survey();
        $measure = $this->suggestion($survey, 'Demandas e Sobrecarga', 'Texto antigo.', ['objective' => 'Objetivo já gravado.']);

        app(ActionMeasuresGenerator::class)->generate($survey, AiSetting::current());

        $measure->refresh();
        $this->assertSame('Objetivo já gravado.', $measure->objective);
        $this->assertSame('Revisar metas.', $measure->measure_description, 'intocada: a ação é atualizada no lugar');
        $this->assertSame(Nr1MeasureCatalog::V11['Demandas e Sobrecarga']['indicator'], $measure->indicator, 'vazios são preenchidos');
    }
}
