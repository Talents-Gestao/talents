<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\SurveyAnswer;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\SurveyTemplateQuestion;
use App\Models\SurveyTemplateSection;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\SurveyResultCalculator;
use App\Services\SurveyResultsPresenter;
use App\Support\Nr1PooledItemScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class Nr1Talents39CalculationPhaseSixTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private function talents39(): SurveyMethodVersion
    {
        return SurveyMethodVersion::query()->where('code', SurveyMethodVersion::CODE_TALENTS39_V1)->firstOrFail();
    }

    private function overall(int $surveyId): SurveyResult
    {
        return SurveyResult::query()
            ->where('survey_id', $surveyId)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->sole();
    }

    /**
     * @param  array<int, int>  $valuesByQuestion
     */
    private function respond(int $surveyId, array $valuesByQuestion, ?int $departmentId = null): SurveyResponse
    {
        $response = SurveyResponse::query()->create([
            'survey_id' => $surveyId,
            'session_token' => (string) Str::uuid(),
            'department_id' => $departmentId,
            'completed_at' => now(),
        ]);

        foreach ($valuesByQuestion as $questionId => $value) {
            SurveyAnswer::query()->create([
                'survey_response_id' => $response->id,
                'survey_template_question_id' => $questionId,
                'value' => $value,
            ]);
        }

        return $response;
    }

    /**
     * 39 itens em 3 dimensões, 40 respondentes em 2 setores: 1560 respostas válidas somando 4052.
     * Pesos diferentes de 1 provam que a coluna weight é ignorada.
     *
     * @return object{fx: object, questionIds: list<int>}
     */
    private function seedFixture4052Over1560(): object
    {
        $fx = $this->createSurveyFixture(['method_version_id' => $this->talents39()->id]);
        $fx->question->update(['weight' => 5.00]);
        $questionIds = [$fx->question->id];

        $sections = [$fx->section];
        foreach ([1, 2] as $order) {
            $sections[] = SurveyTemplateSection::query()->create([
                'survey_template_id' => $fx->template->id,
                'title' => "Dimensão {$order}",
                'sort_order' => $order,
            ]);
        }

        foreach ($sections as $sectionIndex => $section) {
            $start = $sectionIndex === 0 ? 1 : 0;
            for ($i = $start; $i < 13; $i++) {
                $questionIds[] = SurveyTemplateQuestion::query()->create([
                    'survey_template_section_id' => $section->id,
                    'body' => "Item {$sectionIndex}.{$i}",
                    'reverse_score' => false,
                    'weight' => $i % 2 === 0 ? 3.00 : 1.00,
                    'sort_order' => $i,
                ])->id;
            }
        }
        $this->assertCount(39, $questionIds);

        $departments = [
            Department::query()->create(['company_id' => $fx->company->id, 'name' => 'Setor 1'])->id,
            Department::query()->create(['company_id' => $fx->company->id, 'name' => 'Setor 2'])->id,
        ];

        $answerIndex = 0;
        $rows = [];
        for ($person = 0; $person < 40; $person++) {
            $response = SurveyResponse::query()->create([
                'survey_id' => $fx->survey->id,
                'session_token' => (string) Str::uuid(),
                'department_id' => $departments[$person % 2],
                'completed_at' => now(),
            ]);
            foreach ($questionIds as $questionId) {
                $rows[] = [
                    'survey_response_id' => $response->id,
                    'survey_template_question_id' => $questionId,
                    'value' => $answerIndex < 932 ? 3 : 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $answerIndex++;
            }
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('survey_answers')->insert($chunk);
        }

        return (object) ['fx' => $fx, 'questionIds' => $questionIds];
    }

    public function test_fixture_4052_over_1560_stores_2_600_with_numerator_and_denominator(): void
    {
        $seed = $this->seedFixture4052Over1560();
        $survey = $seed->fx->survey;

        app(SurveyResultCalculator::class)->recalculate($survey->fresh());

        $overall = $this->overall($survey->id);
        $this->assertSame(2.6, (float) $overall->average_score);
        $this->assertSame('2.600', number_format((float) $overall->average_score, 3, '.', ''));
        $this->assertSame('yellow', $overall->risk_level);
        $this->assertSame(4052.0, (float) $overall->score_numerator);
        $this->assertSame(1560, (int) $overall->score_denominator);
        $this->assertSame(40, (int) $overall->respondent_count);
        $this->assertSame($this->talents39()->id, (int) $overall->method_version_id);

        $rows = SurveyResult::query()->where('survey_id', $survey->id)->get();
        $this->assertCount(1 + 3 + 2 + 2 * 3, $rows);
        $this->assertTrue($rows->every(fn (SurveyResult $row) => (int) $row->method_version_id === $this->talents39()->id));

        $dimensions = $rows->filter(fn (SurveyResult $row) => $row->survey_template_section_id !== null && $row->department_id === null);
        $this->assertSame(4052.0, (float) $dimensions->sum(fn (SurveyResult $row) => (float) $row->score_numerator));
        $this->assertSame(1560, (int) $dimensions->sum('score_denominator'));

        $sectors = $rows->filter(fn (SurveyResult $row) => $row->department_id !== null && $row->survey_template_section_id === null);
        $this->assertSame([20, 20], $sectors->pluck('respondent_count')->map(fn ($n) => (int) $n)->values()->all());
        $this->assertSame([780, 780], $sectors->pluck('score_denominator')->map(fn ($n) => (int) $n)->values()->all());
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: int, 3: string}>
     */
    public static function bandCuts(): array
    {
        return [
            '2,334 vira 2,33 Leve' => [2334, 1000, 233, 'green'],
            '2,335 vira 2,34 Moderado' => [2335, 1000, 234, 'yellow'],
            '3,664 vira 3,66 Moderado' => [3664, 1000, 366, 'yellow'],
            '3,665 vira 3,67 Grave' => [3665, 1000, 367, 'red'],
        ];
    }

    #[DataProvider('bandCuts')]
    public function test_band_is_decided_after_half_up_rounding_of_exact_fraction(
        int $numerator,
        int $denominator,
        int $expectedHundredths,
        string $expectedBand,
    ): void {
        $score = Nr1PooledItemScore::fromSums($numerator, $denominator, $this->talents39());

        $this->assertSame($expectedHundredths, $score->hundredths);
        $this->assertSame($expectedBand, $score->riskLevel);
        $this->assertSame(sprintf('%d.%02d0', intdiv($expectedHundredths, 100), $expectedHundredths % 100), $score->storedScore());
    }

    public function test_respondent_count_counts_people_not_answers(): void
    {
        $fx = $this->createSurveyFixture(['method_version_id' => $this->talents39()->id]);
        $secondSection = SurveyTemplateSection::query()->create([
            'survey_template_id' => $fx->template->id,
            'title' => 'Dimensão 2',
            'sort_order' => 1,
        ]);
        $q2 = SurveyTemplateQuestion::query()->create([
            'survey_template_section_id' => $fx->section->id,
            'body' => 'Item 2',
            'reverse_score' => true,
            'sort_order' => 1,
        ]);
        $q3 = SurveyTemplateQuestion::query()->create([
            'survey_template_section_id' => $secondSection->id,
            'body' => 'Item 3',
            'reverse_score' => false,
            'sort_order' => 0,
        ]);

        $this->respond($fx->survey->id, [$fx->question->id => 4, $q2->id => 1, $q3->id => 2]);
        $this->respond($fx->survey->id, [$fx->question->id => 2, $q2->id => 5]);
        $this->respond($fx->survey->id, []);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $overall = $this->overall($fx->survey->id);
        $this->assertSame(2, (int) $overall->respondent_count);
        $this->assertSame(5, (int) $overall->score_denominator);
        // Item 2 é invertido: 1 → 5 e 5 → 1. Soma 4 + 5 + 2 + 2 + 1 = 14; 14/5 = 2,80.
        $this->assertSame(14.0, (float) $overall->score_numerator);
        $this->assertSame(2.8, (float) $overall->average_score);

        $secondDimension = SurveyResult::query()
            ->where('survey_id', $fx->survey->id)
            ->where('survey_template_section_id', $secondSection->id)
            ->whereNull('department_id')
            ->sole();
        $this->assertSame(1, (int) $secondDimension->respondent_count);
        $this->assertSame(1, (int) $secondDimension->score_denominator);
    }

    public function test_talents39_ignores_item_weight(): void
    {
        $fx = $this->createSurveyFixture(['method_version_id' => $this->talents39()->id]);
        $q2 = $this->weightedSecondQuestion($fx);
        $this->respond($fx->survey->id, [$fx->question->id => 2, $q2->id => 3]);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $this->assertSame(2.5, (float) $this->overall($fx->survey->id)->average_score);
    }

    public function test_production_v1_recalculation_keeps_raw_formula_and_band_on_raw_value(): void
    {
        $fx = $this->createSurveyFixture();
        $q2 = $this->weightedSecondQuestion($fx);
        // Média ponderada: (2 × 66,98 + 3 × 33,02) / 100 = 2,3302. Grava 2,330; a faixa sobre o bruto é Moderado.
        $this->respond($fx->survey->id, [$fx->question->id => 2, $q2->id => 3]);
        $this->assertSame(SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_PRODUCTION_V1), (int) $fx->survey->method_version_id);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());
        $first = $this->overall($fx->survey->id);

        $this->assertSame(round((2 * 66.98 + 3 * 33.02) / 100, 3), (float) $first->average_score);
        $this->assertSame('yellow', $first->risk_level);
        $this->assertNull($first->score_numerator);
        $this->assertNull($first->score_denominator);
        $this->assertSame((int) $fx->survey->method_version_id, (int) $first->method_version_id);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());
        $second = $this->overall($fx->survey->id);
        $this->assertSame((float) $first->average_score, (float) $second->average_score);
        $this->assertSame($first->risk_level, $second->risk_level);
        $this->assertNull($second->score_numerator);
    }

    public function test_production_v1_still_stores_sector_with_one_respondent(): void
    {
        $fx = $this->createSurveyFixture();
        $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => 'Setor único']);
        $this->respond($fx->survey->id, [$fx->question->id => 4], $department->id);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $this->assertSame(1, SurveyResult::query()
            ->where('survey_id', $fx->survey->id)
            ->where('department_id', $department->id)
            ->whereNull('survey_template_section_id')
            ->value('respondent_count'));
    }

    public function test_results_screen_and_dashboard_show_same_talents39_index(): void
    {
        $seed = $this->seedFixture4052Over1560();
        $survey = $seed->fx->survey;
        app(SurveyResultCalculator::class)->recalculate($survey->fresh());

        $presented = SurveyResultsPresenter::forSurvey($survey->fresh());
        $dashboard = app(PsychosocialDashboardBuilder::class)->build($survey->fresh());

        $this->assertSame((float) $presented['overall']->average_score, (float) $dashboard['overall']['score']);
        $this->assertSame(2.6, (float) $dashboard['overall']['score']);
        $this->assertSame('Moderado', $dashboard['overall']['band_label']);
        $this->assertSame(PsychosocialDashboardBuilder::LEGENDS[SurveyMethodVersion::CODE_TALENTS39_V1], $dashboard['method']['legend']);
    }

    public function test_new_campaign_without_explicit_method_is_born_production_v1(): void
    {
        $fx = $this->createSurveyFixture();

        $this->assertSame(SurveyMethodVersion::CODE_PRODUCTION_V1, $fx->survey->fresh()->methodVersion->code);
    }

    private function weightedSecondQuestion(object $fx): SurveyTemplateQuestion
    {
        $fx->question->update(['weight' => 66.98]);

        return SurveyTemplateQuestion::query()->create([
            'survey_template_section_id' => $fx->section->id,
            'body' => 'Item ponderado',
            'reverse_score' => false,
            'weight' => 33.02,
            'sort_order' => 1,
        ]);
    }
}
