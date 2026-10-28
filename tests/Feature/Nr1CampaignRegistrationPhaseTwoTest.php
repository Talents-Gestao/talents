<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\WipeCompanyDataCommand;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class Nr1CampaignRegistrationPhaseTwoTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_empty_registration_is_stored_without_inventing_eligible_count(): void
    {
        $fx = $this->createSurveyFixture();

        $registration = SurveyCampaignRegistration::query()->create([
            'survey_id' => $fx->survey->id,
            'company_id' => $fx->company->id,
        ]);

        $this->assertNull($registration->eligible_count);
        $this->assertNull($registration->technical_responsible_name);
        $this->assertNull($registration->aep_summary);
        $this->assertSame($fx->survey->id, $fx->survey->fresh()->campaignRegistration->survey_id);
    }

    public function test_rollback_drops_only_the_new_table(): void
    {
        $fx = $this->createSurveyFixture();
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'average_score' => 2.334,
            'risk_level' => 'yellow',
            'respondent_count' => 4,
        ]);
        SurveyCampaignRegistration::query()->create([
            'survey_id' => $fx->survey->id,
            'company_id' => $fx->company->id,
            'eligible_count' => 10,
        ]);

        $surveysBefore = DB::table('surveys')->count();
        $resultsBefore = DB::table('survey_results')->orderBy('id')->get(['id', 'average_score', 'risk_level']);

        $migration = require database_path('migrations/2026_10_20_160000_create_survey_campaign_registrations_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('survey_campaign_registrations'));
        $this->assertSame($surveysBefore, DB::table('surveys')->count());
        $resultsAfter = DB::table('survey_results')->orderBy('id')->get(['id', 'average_score', 'risk_level']);
        $this->assertEquals($resultsBefore->toArray(), $resultsAfter->toArray());
    }

    public function test_wipe_company_data_removes_registration_before_the_survey(): void
    {
        $fx = $this->createSurveyFixture();
        SurveyCampaignRegistration::query()->create([
            'survey_id' => $fx->survey->id,
            'company_id' => $fx->company->id,
        ]);

        $method = new ReflectionMethod(WipeCompanyDataCommand::class, 'wipe');
        $method->invoke(new WipeCompanyDataCommand(), $fx->company->id);

        $this->assertSame(0, DB::table('survey_campaign_registrations')->where('company_id', $fx->company->id)->count());
        $this->assertSame(0, DB::table('surveys')->where('company_id', $fx->company->id)->count());
        $this->assertNotNull(DB::table('companies')->where('id', $fx->company->id)->first());
    }
}
