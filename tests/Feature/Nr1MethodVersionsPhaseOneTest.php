<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SurveyAnswer;
use App\Models\SurveyInstrumentVersion;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\SurveyTemplate;
use App\Models\SurveyTemplateQuestion;
use App\Services\SurveyResultCalculator;
use App\Support\Nr1InstrumentMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class Nr1MethodVersionsPhaseOneTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_20_150000_create_survey_method_and_instrument_versions.php';

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    public function test_catalog_has_production_and_talents39_with_agreed_rules(): void
    {
        $production = SurveyMethodVersion::query()->where('code', SurveyMethodVersion::CODE_PRODUCTION_V1)->firstOrFail();
        $talents39 = SurveyMethodVersion::query()->where('code', SurveyMethodVersion::CODE_TALENTS39_V1)->firstOrFail();

        $this->assertSame('respondent_mean_of_means', $production->aggregation);
        $this->assertSame('none', $production->rounding_mode);
        $this->assertNull($production->rounding_scale);
        $this->assertSame('raw', $production->band_basis);
        $this->assertSame('item_weight', $production->weighting);

        $this->assertSame('pooled_item_mean', $talents39->aggregation);
        $this->assertSame('half_up', $talents39->rounding_mode);
        $this->assertSame(2, $talents39->rounding_scale);
        $this->assertSame('rounded', $talents39->band_basis);
        $this->assertSame('equal', $talents39->weighting);
        $this->assertNull($talents39->min_item_coverage);

        foreach ([$production, $talents39] as $method) {
            $this->assertSame(2.33, $method->green_max);
            $this->assertSame(3.66, $method->yellow_max);
            $this->assertSame(5, $method->min_group_size_new_outputs);
        }
    }

    public function test_catalog_rows_are_immutable(): void
    {
        $production = SurveyMethodVersion::query()->where('code', SurveyMethodVersion::CODE_PRODUCTION_V1)->firstOrFail();

        $this->expectException(LogicException::class);
        $production->update(['label' => 'outro']);
    }

    public function test_new_campaign_starts_in_production_v1_with_frozen_instrument(): void
    {
        $fx = $this->createSurveyFixture();
        $survey = $fx->survey->fresh();

        $this->assertSame(
            SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_PRODUCTION_V1),
            (int) $survey->method_version_id
        );

        $instrument = $survey->instrumentVersion;
        $this->assertNotNull($instrument);
        $this->assertSame(Nr1InstrumentMatrix::SOURCE_CAMPAIGN_START, $instrument->source);
        $this->assertSame(Nr1InstrumentMatrix::CODE_CUSTOM, $instrument->instrument_code);
        $this->assertCount(1, $instrument->item_matrix);
        $this->assertSame($fx->question->id, $instrument->item_matrix[0]['question_id']);
        $this->assertSame(Nr1InstrumentMatrix::hash(Nr1InstrumentMatrix::forTemplate($fx->template->id)), $instrument->item_matrix_sha256);
    }

    public function test_same_matrix_reuses_version_and_edited_template_creates_new_one(): void
    {
        $fx = $this->createSurveyFixture();
        $first = $fx->survey->fresh()->instrument_version_id;

        $second = $this->createSurveyOnTemplate($fx->company->id, $fx->template->id);
        $this->assertSame($first, $second->instrument_version_id);

        SurveyTemplateQuestion::query()->whereKey($fx->question->id)->update(['reverse_score' => true]);
        $third = $this->createSurveyOnTemplate($fx->company->id, $fx->template->id);

        $this->assertNotSame($first, $third->instrument_version_id);
        $this->assertSame('v2', $third->instrumentVersion->version_label);
        $this->assertTrue($third->instrumentVersion->item_matrix[0]['reverse_score']);
    }

    public function test_hse_template_is_flagged_pending_polarity_audit(): void
    {
        $fx = $this->createSurveyFixture();
        SurveyTemplate::query()->whereKey($fx->template->id)->update(['title' => 'HSE-IT / Management Standards (NR-1)']);

        $survey = $this->createSurveyOnTemplate($fx->company->id, $fx->template->id);

        $this->assertSame(Nr1InstrumentMatrix::CODE_HSE_IT, $survey->instrumentVersion->instrument_code);
        $this->assertTrue($survey->instrumentVersion->isPolarityAuditPending());
    }

    public function test_calculator_keeps_production_numbers_and_marks_rows_with_production_v1(): void
    {
        $fx = $this->createSurveyFixture();
        $reverse = SurveyTemplateQuestion::query()->create([
            'survey_template_section_id' => $fx->section->id,
            'body' => 'Item protetivo?',
            'reverse_score' => true,
            'weight' => 3,
            'sort_order' => 1,
        ]);

        $this->answer($fx->survey->id, 'a', [$fx->question->id => 4, $reverse->id => 2]);
        $this->answer($fx->survey->id, 'b', [$fx->question->id => 2]);

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        $overall = SurveyResult::query()
            ->where('survey_id', $fx->survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->firstOrFail();

        // Respondente a: (4·1 + (6−2)·3) / 4 = 4,0; respondente b: 2,0; média das médias = 3,0.
        $this->assertSame(3.0, (float) $overall->average_score);
        $this->assertSame('yellow', $overall->risk_level);
        $this->assertNull($overall->score_numerator);
        $this->assertNull($overall->score_denominator);

        $productionId = SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_PRODUCTION_V1);
        $this->assertSame(0, SurveyResult::query()->where('survey_id', $fx->survey->id)
            ->where(fn ($q) => $q->whereNull('method_version_id')->orWhere('method_version_id', '!=', $productionId))
            ->count());
    }

    public function test_backfill_points_every_campaign_to_production_without_touching_scores(): void
    {
        $fx = $this->createSurveyFixture();
        $copsoq = SurveyTemplate::query()->create(['title' => 'NR-1 / COPSOQ (modelo Talents)', 'is_active' => true]);
        $hse = SurveyTemplate::query()->create(['title' => 'HSE-IT / Management Standards (NR-1)', 'is_active' => true]);

        $activeId = $this->insertLegacySurvey($fx->company->id, $copsoq->id, null);
        $trashedId = $this->insertLegacySurvey($fx->company->id, $hse->id, now());

        $resultIds = [
            $this->insertLegacyResult($activeId, '2.334', 'yellow'),
            $this->insertLegacyResult($trashedId, '3.661', 'red'),
        ];
        $before = DB::table('survey_results')->whereIn('id', $resultIds)->orderBy('id')->get(['id', 'average_score', 'risk_level']);

        $this->migration()->backfill();

        $productionId = SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_PRODUCTION_V1);
        $talents39Id = SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1);

        $this->assertSame(0, DB::table('surveys')->where(fn ($q) => $q->whereNull('method_version_id')->orWhere('method_version_id', '!=', $productionId))->count());
        $this->assertSame(0, DB::table('surveys')->where('method_version_id', $talents39Id)->count());

        $trashedVersion = SurveyInstrumentVersion::query()->findOrFail(DB::table('surveys')->where('id', $trashedId)->value('instrument_version_id'));
        $this->assertSame('v1-backfill', $trashedVersion->version_label);
        $this->assertSame(Nr1InstrumentMatrix::SOURCE_BACKFILL, $trashedVersion->source);
        $this->assertSame(Nr1InstrumentMatrix::CODE_HSE_IT, $trashedVersion->instrument_code);
        $this->assertSame(Nr1InstrumentMatrix::AUDIT_PENDING, $trashedVersion->polarity_audit_status);

        $activeVersion = SurveyInstrumentVersion::query()->findOrFail(DB::table('surveys')->where('id', $activeId)->value('instrument_version_id'));
        $this->assertSame(Nr1InstrumentMatrix::CODE_COPSOQ_TALENTS, $activeVersion->instrument_code);
        $this->assertSame(Nr1InstrumentMatrix::AUDIT_NOT_FLAGGED, $activeVersion->polarity_audit_status);

        $fixtureVersion = SurveyInstrumentVersion::query()->findOrFail(DB::table('surveys')->where('id', $fx->survey->id)->value('instrument_version_id'));
        $this->assertSame(Nr1InstrumentMatrix::CODE_CUSTOM, $fixtureVersion->instrument_code);

        $after = DB::table('survey_results')->whereIn('id', $resultIds)->orderBy('id')->get(['id', 'average_score', 'risk_level', 'method_version_id', 'score_numerator', 'score_denominator']);
        foreach ($before as $index => $row) {
            $this->assertSame((float) $row->average_score, (float) $after[$index]->average_score);
            $this->assertSame($row->risk_level, $after[$index]->risk_level);
            $this->assertSame($productionId, (int) $after[$index]->method_version_id);
            $this->assertNull($after[$index]->score_numerator);
            $this->assertNull($after[$index]->score_denominator);
        }
    }

    public function test_rollback_is_blocked_while_a_campaign_uses_talents39(): void
    {
        $fx = $this->createSurveyFixture();
        DB::table('surveys')->where('id', $fx->survey->id)->update([
            'method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1),
        ]);

        try {
            $this->migration()->down();
            $this->fail('O rollback deveria ter sido bloqueado.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('talents39_v1', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('surveys', 'method_version_id'));
        $this->assertTrue(Schema::hasColumn('survey_results', 'method_version_id'));
        $this->assertTrue(Schema::hasTable('survey_method_versions'));
    }

    private function createSurveyOnTemplate(int $companyId, int $templateId): \App\Models\Survey
    {
        return \App\Models\Survey::query()->create([
            'company_id' => $companyId,
            'survey_template_id' => $templateId,
            'title' => 'Outra campanha',
            'public_token' => (string) Str::uuid(),
            'status' => 'active',
            'min_responses_for_breakdown' => 1,
        ])->fresh();
    }

    /**
     * @param  array<int, int>  $valuesByQuestion
     */
    private function answer(int $surveyId, string $token, array $valuesByQuestion): void
    {
        $response = SurveyResponse::query()->create([
            'survey_id' => $surveyId,
            'session_token' => $token,
            'completed_at' => now(),
        ]);

        foreach ($valuesByQuestion as $questionId => $value) {
            SurveyAnswer::query()->create([
                'survey_response_id' => $response->id,
                'survey_template_question_id' => $questionId,
                'value' => $value,
            ]);
        }
    }

    private function insertLegacySurvey(int $companyId, int $templateId, ?\DateTimeInterface $deletedAt): int
    {
        return DB::table('surveys')->insertGetId([
            'company_id' => $companyId,
            'survey_template_id' => $templateId,
            'title' => 'Campanha legada',
            'public_token' => (string) Str::uuid(),
            'status' => 'closed',
            'min_responses_for_breakdown' => 1,
            'deleted_at' => $deletedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertLegacyResult(int $surveyId, string $score, string $riskLevel): int
    {
        return DB::table('survey_results')->insertGetId([
            'survey_id' => $surveyId,
            'average_score' => $score,
            'risk_level' => $riskLevel,
            'respondent_count' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
