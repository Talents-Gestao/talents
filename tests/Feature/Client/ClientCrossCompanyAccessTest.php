<?php

namespace Tests\Feature\Client;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class ClientCrossCompanyAccessTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_super_admin_is_redirected_from_client_dashboard(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('client.dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_cannot_view_other_company_survey(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $userA = User::factory()->companyAdmin($fxA->company->id)->create();

        $this->actingAs($userA)
            ->get(route('client.surveys.show', $fxB->survey))
            ->assertNotFound();
    }

    public function test_cannot_export_json_of_other_company_survey(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $userA = User::factory()->companyAdmin($fxA->company->id)->create();

        $this->actingAs($userA)
            ->get(route('client.surveys.export.json', $fxB->survey))
            ->assertNotFound();
    }

    public function test_cannot_export_csv_of_other_company_survey(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $userA = User::factory()->companyAdmin($fxA->company->id)->create();

        $this->actingAs($userA)
            ->get(route('client.surveys.export.csv', $fxB->survey))
            ->assertNotFound();
    }

    public function test_cannot_download_executive_report_of_other_company_survey(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $userA = User::factory()->companyAdmin($fxA->company->id)->create();

        $this->actingAs($userA)
            ->get(route('client.surveys.reports.executive', $fxB->survey))
            ->assertNotFound();
    }

    public function test_cannot_download_technical_report_of_other_company_survey(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $userA = User::factory()->companyAdmin($fxA->company->id)->create();

        $this->actingAs($userA)
            ->get(route('client.surveys.reports.technical', $fxB->survey))
            ->assertNotFound();
    }

    public function test_cannot_download_referral_report_of_other_company_survey(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $userA = User::factory()->companyAdmin($fxA->company->id)->create();

        $this->actingAs($userA)
            ->get(route('client.surveys.reports.referral', $fxB->survey))
            ->assertNotFound();
    }

    public function test_cannot_download_action_plan_report_of_other_company_survey(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $userA = User::factory()->companyAdmin($fxA->company->id)->create();

        $this->actingAs($userA)
            ->get(route('client.surveys.reports.action-plan', $fxB->survey))
            ->assertNotFound();
    }

    public function test_cannot_view_other_company_complaint(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $userA = User::factory()->companyAdmin($fxA->company->id)->create();

        $complaintB = Complaint::query()->create([
            'company_id' => $fxB->company->id,
            'protocol' => (string) Str::uuid(),
            'category' => 'outros',
            'description' => str_repeat('a', 25),
            'status' => 'new',
            'is_anonymous' => true,
        ]);

        $this->actingAs($userA)
            ->get(route('client.complaints.show', $complaintB))
            ->assertNotFound();
    }
}
