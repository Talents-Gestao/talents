<?php

namespace Tests\Feature;

use App\Models\SurveyNr1Report;
use App\Models\User;
use App\Support\Nr1RiskScenarioResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\SeedsNr1SurveyResults;
use Tests\TestCase;

class Nr1ReportsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;
    use SeedsNr1SurveyResults;

    public function test_executive_pdf_view_includes_red_scenario_copy(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'red');

        $survey = $fx->survey->fresh()->load(['company', 'template.sections', 'results', 'insights']);
        $scenario = Nr1RiskScenarioResolver::forSurvey($survey) ?? 'green';

        $html = view('reports.executive', [
            'survey' => $survey,
            'scenario' => $scenario,
            'scenarioConfig' => Nr1RiskScenarioResolver::scenarioConfig($scenario),
            'riskLevelLabel' => fn (?string $l) => config('nr1.risk_labels.'.$l, $l),
        ])->render();

        $this->assertStringContainsString('Cenário geral: Alto risco', $html);
        $this->assertStringContainsString('pontos críticos', $html);
    }

    public function test_referral_pdf_view_includes_yellow_scenario_copy(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0);

        $survey = $fx->survey->fresh()->load(['company', 'results', 'insights']);
        $scenario = Nr1RiskScenarioResolver::forSurvey($survey) ?? 'green';

        $html = view('reports.referral', [
            'survey' => $survey,
            'scenario' => $scenario,
            'scenarioConfig' => Nr1RiskScenarioResolver::scenarioConfig($scenario),
            'riskLevelLabel' => fn (?string $l) => config('nr1.risk_labels.'.$l, $l),
        ])->render();

        $this->assertStringContainsString('Cenário geral: Atenção', $html);
        $this->assertStringContainsString('atualização do Programa de Gerenciamento de Riscos', $html);
    }

    public function test_client_can_download_nr1_report_pdfs(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);

        $user = User::factory()->companyAdmin($fx->company->id)->create();

        $this->actingAs($user)
            ->get(route('client.surveys.reports.executive', $fx->survey))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('client.surveys.reports.referral', $fx->survey))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('client.surveys.reports.action-plan', $fx->survey))
            ->assertOk();
    }

    public function test_uploaded_executive_report_takes_precedence_for_client(): void
    {
        Storage::fake('local');

        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'green', 2.0);

        $path = UploadedFile::fake()->create('executivo-custom.pdf', 50, 'application/pdf')
            ->store('survey-nr1-reports/'.$fx->survey->id.'/executive', 'local');

        SurveyNr1Report::query()->create([
            'survey_id' => $fx->survey->id,
            'company_id' => $fx->company->id,
            'type' => SurveyNr1Report::TYPE_EXECUTIVE,
            'file_path' => $path,
            'file_name' => 'executivo-custom.pdf',
            'published_at' => now(),
        ]);

        $user = User::factory()->companyAdmin($fx->company->id)->create();

        $this->actingAs($user)
            ->get(route('client.surveys.reports.executive', $fx->survey))
            ->assertOk()
            ->assertDownload('executivo-custom.pdf');
    }
}
