<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\ActionMeasuresGenerator;
use App\Services\Nr1AiAnalyzer;
use App\Services\PsychosocialMeasureService;
use App\Support\Nr1MeasureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

/**
 * Gerador de medidas idempotente por dimensão (campanha + dimensão normalizada + engine_suggestion): regerar não
 * duplica; duas medidas da mesma dimensão viram uma com as ações juntadas; dimensão que já avançou, medida manual e
 * sugestão editada pela Talents são preservadas; nada vaza para outra empresa.
 */
class ActionMeasuresIdempotencyTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $aiMeasures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $test = $this;
        $this->app->instance(Nr1AiAnalyzer::class, new class($test) extends Nr1AiAnalyzer
        {
            public function __construct(private readonly ActionMeasuresIdempotencyTest $test) {}

            public function generateNarrative(...$args): array
            {
                return ['content' => json_encode(['measures' => $this->test->aiMeasures()]), 'prompt_tokens' => 1, 'completion_tokens' => 1, 'model_used' => 'falso'];
            }
        });
        AiSetting::query()->create([
            'provider' => 'openai', 'model' => 'gpt-test', 'is_enabled' => true, 'api_key' => 'chave-de-teste',
            'max_tokens' => 800, 'temperature' => 0.2,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function aiMeasures(): array
    {
        return $this->aiMeasures;
    }

    private function closedSurvey(): Survey
    {
        $fx = $this->createSurveyFixture();
        $fx->survey->forceFill(['ends_at' => now()->subDay(), 'status' => 'closed'])->save();
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id, 'survey_template_section_id' => null, 'department_id' => null,
            'average_score' => '2.800', 'risk_level' => 'yellow', 'respondent_count' => 12,
        ]);

        return $fx->survey->fresh();
    }

    private function generate(Survey $survey): int
    {
        return app(ActionMeasuresGenerator::class)->generate($survey->fresh(), AiSetting::current());
    }

    /** @return array<string, int> dimensão => quantidade de sugestões do motor */
    private function countByDimension(Survey $survey): array
    {
        return PsychosocialMeasure::query()->where('survey_id', $survey->id)->get()
            ->countBy(fn (PsychosocialMeasure $m) => $m->origin.':'.Nr1MeasureCatalog::dimensionKey($m->factor_label))
            ->sortKeys()->all();
    }

    private function measure(string $factor, string $text, int $days = 30): array
    {
        return ['factor_label' => $factor, 'measure_description' => $text, 'suggested_due_days' => $days];
    }

    public function test_generating_twice_does_not_duplicate_and_updates_in_place(): void
    {
        $survey = $this->closedSurvey();
        $this->aiMeasures = [$this->measure('Demandas e Sobrecarga', 'Revisar metas.'), $this->measure('Esgotamento e Saúde Emocional', 'Rever pausas.')];

        $this->generate($survey);
        $ids = PsychosocialMeasure::query()->orderBy('id')->pluck('id')->all();

        $this->aiMeasures = [$this->measure('DEMANDAS E SOBRECARGA', 'Revisar metas e prazos.'), $this->measure('Esgotamento e saude emocional', 'Rever pausas e jornadas.')];
        $this->generate($survey);

        $this->assertSame(['engine_suggestion:demandas e sobrecarga' => 1, 'engine_suggestion:esgotamento e saude emocional' => 1], $this->countByDimension($survey));
        $this->assertSame($ids, PsychosocialMeasure::query()->orderBy('id')->pluck('id')->all(), 'atualiza no lugar, sem recriar');
        $updated = PsychosocialMeasure::query()->where('factor_label', 'Demandas e Sobrecarga')->sole();
        $this->assertSame('Revisar metas e prazos.', $updated->measure_description);
        $this->assertFalse(app(PsychosocialMeasureService::class)->isEditedByTalents($updated), 'atualização automática não conta como edição');
        $this->assertTrue($updated->events()->where('field', 'measure_description')->whereNull('user_id')->exists());
    }

    public function test_two_measures_for_the_same_dimension_become_one_with_merged_action(): void
    {
        $survey = $this->closedSurvey();
        $this->aiMeasures = [
            $this->measure('Demandas e Sobrecarga', 'Revisar metas e distribuição.', 30),
            $this->measure('Demandas e sobrecarga (Operações)', 'Mapear picos de trabalho. Revisar metas e distribuição.', 60),
        ];

        $this->generate($survey);

        $measure = PsychosocialMeasure::query()->where('survey_id', $survey->id)->sole();
        $this->assertSame('Demandas e Sobrecarga', $measure->factor_label);
        $this->assertSame('Revisar metas e distribuição. Mapear picos de trabalho.', $measure->measure_description, 'junta a ação sem repetir frase');
        $this->assertSame(now()->addDays(30)->toDateString(), $measure->suggested_due_date->toDateString(), 'mantém o prazo da primeira');
    }

    public function test_dimension_with_a_confirmed_or_manual_measure_is_skipped(): void
    {
        $survey = $this->closedSurvey();
        $this->aiMeasures = [$this->measure('Demandas e Sobrecarga', 'Revisar metas.')];
        $this->generate($survey);
        PsychosocialMeasure::query()->update(['status' => PsychosocialMeasure::STATUS_PENDING]);
        app(PsychosocialMeasureService::class)->createManual($survey, ['factor_label' => 'Segurança Psicológica', 'measure_description' => 'Canal de escuta.', 'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION], User::factory()->create());

        $this->aiMeasures = [$this->measure('Demandas e Sobrecarga', 'Outra ação.'), $this->measure('Segurança Psicológica', 'Outra ação.')];
        $this->assertSame(0, $this->generate($survey));

        $this->assertSame(['engine_suggestion:demandas e sobrecarga' => 1, 'manual:seguranca psicologica' => 1], $this->countByDimension($survey));
        $this->assertSame('Revisar metas.', PsychosocialMeasure::query()->where('origin', 'engine_suggestion')->sole()->measure_description);
    }

    public function test_suggestion_edited_by_talents_is_never_deleted_nor_overwritten(): void
    {
        $survey = $this->closedSurvey();
        $this->aiMeasures = [$this->measure('Demandas e Sobrecarga', 'Revisar metas.'), $this->measure('Liderança e Gestão', 'Formar líderes.')];
        $this->generate($survey);
        $edited = PsychosocialMeasure::query()->where('factor_label', 'Liderança e Gestão')->sole();
        app(PsychosocialMeasureService::class)->update($edited, ['indicator' => 'Indicador escrito pela Talents.'], User::factory()->create());

        // A IA não devolve mais «Liderança e Gestão» e muda o texto de «Demandas»: a editada fica intacta.
        $this->aiMeasures = [$this->measure('Demandas e Sobrecarga', 'Revisar metas e prazos.')];
        $this->generate($survey);
        $this->assertSame('Indicador escrito pela Talents.', $edited->fresh()->indicator);
        $this->assertSame('Formar líderes.', $edited->fresh()->measure_description);

        // Se a IA voltar a devolver a dimensão, também não sobrescreve nem cria outra.
        $this->aiMeasures = [$this->measure('Liderança e Gestão', 'Texto novo da IA.')];
        $this->generate($survey);
        $this->assertSame('Formar líderes.', $edited->fresh()->measure_description);
        $this->assertSame(1, PsychosocialMeasure::query()->where('factor_label', 'Liderança e Gestão')->count());
        $this->assertSame(0, PsychosocialMeasure::query()->where('factor_label', 'Demandas e Sobrecarga')->count(), 'intocada de dimensão que saiu é substituída');
    }

    public function test_generation_never_touches_another_company(): void
    {
        $other = $this->closedSurvey();
        $this->aiMeasures = [$this->measure('Demandas e Sobrecarga', 'Ação da outra empresa.')];
        $this->generate($other);
        $otherMeasure = PsychosocialMeasure::query()->where('survey_id', $other->id)->sole();

        $survey = $this->closedSurvey();
        $this->assertNotSame($other->company_id, $survey->company_id);
        $this->aiMeasures = [$this->measure('Demandas e Sobrecarga', 'Ação desta empresa.')];
        $this->generate($survey);
        $this->generate($survey);

        $this->assertSame('Ação da outra empresa.', $otherMeasure->fresh()->measure_description);
        $this->assertSame(1, PsychosocialMeasure::query()->where('survey_id', $survey->id)->count());
        $this->assertSame($survey->company_id, PsychosocialMeasure::query()->where('survey_id', $survey->id)->sole()->company_id);
    }

    public function test_prompt_asks_for_one_measure_per_dimension_and_keeps_the_limit(): void
    {
        $prompt = app(Nr1AiAnalyzer::class)->actionMeasuresSystemPrompt();
        $this->assertStringContainsString('No máximo 6 medidas e no máximo 1 medida por dimensão', $prompt);
    }

    public function test_merge_keeps_every_action_without_repeating_sentences(): void
    {
        $this->assertSame('Rever metas. Mapear picos.', Nr1MeasureCatalog::mergeDescriptions('Rever metas', 'Mapear picos. rever metas.'));
        $this->assertSame('demandas e sobrecarga', Nr1MeasureCatalog::dimensionKey('  DEMANDAS   e Sobrecarga '));
        $this->assertSame('Esgotamento e Saúde Emocional', Nr1MeasureCatalog::v11Dimension('esgotamento e saude emocional - Setor A'));
        $this->assertNull(Nr1MeasureCatalog::v11Dimension('Exigências quantitativas'));
    }
}
