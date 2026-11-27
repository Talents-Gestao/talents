<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Company;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\ActionMeasuresGenerator;
use App\Services\Nr1AiAnalyzer;
use App\Services\PsychosocialDashboardBuilder;
use App\Support\Nr1MeasureCatalog;
use App\Support\Nr1Shell\Nr1ShellPageData;
use Database\Seeders\Nr1DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Plano de ação: a medida encontra o índice, a faixa e os setores da dimensão pela chave normalizada
 * (Nr1MeasureCatalog::dimensionKey), sem depender de caixa ou acento do rótulo; painel e prompt da Mia usam o mesmo
 * título de dimensão.
 */
class Nr1MeasureDimensionMatchTest extends TestCase
{
    use RefreshDatabase;

    private Survey $survey;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Nr1DemoSeeder::class);
        $company = Company::query()->where('name', Nr1DemoSeeder::COMPANY)->firstOrFail();
        $this->survey = Survey::query()->where('company_id', $company->id)->where('title', 'Campanha 2026')->firstOrFail();
        $this->owner = User::query()->where('email', 'demo-owner@talents.local')->firstOrFail();
    }

    /** @return array<string, mixed> cartão do plano da dimensão informada */
    private function card(string $dimension): array
    {
        $plan = app(Nr1ShellPageData::class)->plan($this->survey->fresh(), $this->owner, true);
        $cards = collect($plan['measures'])->filter(fn (array $m) => Nr1MeasureCatalog::dimensionKey($m['factor_label']) === Nr1MeasureCatalog::dimensionKey($dimension));
        $this->assertCount(1, $cards, "Uma medida só para {$dimension}.");

        return $cards->first();
    }

    private function upperSectionTitles(): void
    {
        $ids = SurveyResult::query()->where('survey_id', $this->survey->id)->whereNotNull('survey_template_section_id')->pluck('survey_template_section_id')->unique();
        DB::table('survey_template_sections')->whereIn('id', $ids)->update(['title' => DB::raw('UPPER(title)')]);
    }

    public function test_label_with_different_case_and_accents_still_gets_index_band_and_sectors(): void
    {
        $reference = $this->card('Demandas e Sobrecarga');
        $this->assertNotNull($reference['score_label']);
        $this->assertNotEmpty($reference['departments']);

        PsychosocialMeasure::query()->where('survey_id', $this->survey->id)->where('factor_label', 'Demandas e Sobrecarga')
            ->update(['factor_label' => 'DEMANDAS E SOBRECARGA']);
        PsychosocialMeasure::query()->where('survey_id', $this->survey->id)->where('factor_label', 'Esgotamento e Saúde Emocional')
            ->update(['factor_label' => 'esgotamento e saude  emocional']);

        $upper = $this->card('Demandas e Sobrecarga');
        $this->assertSame($reference['score_label'], $upper['score_label']);
        $this->assertSame($reference['band_label'], $upper['band_label']);
        $this->assertSame($reference['risk_level'], $upper['risk_level']);
        $this->assertSame($reference['departments'], $upper['departments']);
        $this->assertNotNull($this->card('Esgotamento e Saúde Emocional')['score_label']);
    }

    public function test_uppercase_section_titles_regenerated_suggestion_keeps_index_sectors_and_band_without_duplicate(): void
    {
        $this->upperSectionTitles();
        PsychosocialMeasure::query()->where('survey_id', $this->survey->id)->get()->each->delete();
        AiSetting::query()->create(['provider' => 'openai', 'model' => 'gpt-test', 'is_enabled' => true, 'api_key' => 'chave-de-teste', 'max_tokens' => 800, 'temperature' => 0.2]);
        $this->app->instance(Nr1AiAnalyzer::class, new class extends Nr1AiAnalyzer
        {
            public function __construct() {}

            public function generateNarrative(...$args): array
            {
                return ['content' => json_encode(['measures' => [
                    ['factor_label' => 'DEMANDAS E SOBRECARGA', 'measure_description' => 'Revisar metas e prazos.', 'suggested_due_days' => 30],
                    ['factor_label' => 'ESGOTAMENTO E SAÚDE EMOCIONAL', 'measure_description' => 'Planejar pausas.', 'suggested_due_days' => 45],
                ]]), 'prompt_tokens' => 1, 'completion_tokens' => 1, 'model_used' => 'falso'];
            }
        });

        foreach ([1, 2] as $_) {
            app(ActionMeasuresGenerator::class)->generate($this->survey->fresh(), AiSetting::current());
        }

        $this->assertSame(2, PsychosocialMeasure::query()->where('survey_id', $this->survey->id)->count());
        foreach (['Demandas e Sobrecarga', 'Esgotamento e Saúde Emocional'] as $dimension) {
            $card = $this->card($dimension);
            $this->assertNotNull($card['score_label'], $dimension);
            $this->assertNotNull($card['band_label'], $dimension);
            $this->assertNotEmpty($card['departments'], $dimension);
        }
    }

    public function test_dashboard_and_ai_prompt_use_the_same_dimension_title(): void
    {
        $result = SurveyResult::query()->where('survey_id', $this->survey->id)->whereNull('department_id')->whereNotNull('survey_template_section_id')->firstOrFail();
        $result->forceFill(['meta' => array_merge($result->meta ?? [], ['section_title' => 'Título antigo gravado no cálculo'])])->save();
        $current = (string) $result->section->title;

        $dashboardTitles = array_column(app(PsychosocialDashboardBuilder::class)->build($this->survey->fresh())['dimensions'], 'title');
        $promptTitles = array_column(app(Nr1AiAnalyzer::class)->buildAggregatedPayload($this->survey->fresh())['by_dimension'], 'dimension');

        $this->assertContains($current, $dashboardTitles);
        $this->assertContains($current, $promptTitles);
        $this->assertNotContains('Título antigo gravado no cálculo', $promptTitles);
        $this->assertEqualsCanonicalizing($dashboardTitles, $promptTitles);
    }
}
