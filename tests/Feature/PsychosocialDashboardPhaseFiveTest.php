<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PsychosocialAccessLog;
use App\Models\SurveyAnswer;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\SurveyResultsPresenter;
use App\Support\Nr1InstrumentMatrix;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class PsychosocialDashboardPhaseFiveTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private const AGE_MARKER = 'faixa-etaria-25-34';

    private const TENURE_MARKER = 'tempo-casa-1-3';

    /**
     * Campanha com 29 respondentes: A=19, B=6, C=4. Grava survey_results como o calculador faria.
     *
     * @return object{fx: object, user: User, departments: array<string, Department>}
     */
    private function seedCascadeCampaign(): object
    {
        $fx = $this->createSurveyFixture();
        $sizes = ['A' => 19, 'B' => 6, 'C' => 4];
        $departments = [];

        foreach ($sizes as $name => $size) {
            $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => "Setor {$name}"]);
            $departments[$name] = $department;

            for ($i = 0; $i < $size; $i++) {
                $response = SurveyResponse::query()->create([
                    'survey_id' => $fx->survey->id,
                    'session_token' => (string) Str::uuid(),
                    'department_id' => $department->id,
                    'age_range' => self::AGE_MARKER,
                    'tenure_range' => self::TENURE_MARKER,
                    'completed_at' => now(),
                ]);
                SurveyAnswer::query()->create([
                    'survey_response_id' => $response->id,
                    'survey_template_question_id' => $fx->question->id,
                    'value' => 3,
                ]);
            }

            $this->storeResult($fx, null, $department->id, '2.500', 'yellow', $size);
            $this->storeResult($fx, $fx->section->id, $department->id, '2.500', 'yellow', $size);
        }

        $this->storeResult($fx, null, null, '2.414', 'yellow', 29);
        $this->storeResult($fx, $fx->section->id, null, '2.414', 'yellow', 29);

        return (object) [
            'fx' => $fx,
            'user' => User::factory()->companyAdmin($fx->company->id)->create(),
            'departments' => $departments,
        ];
    }

    private function storeResult(object $fx, ?int $sectionId, ?int $departmentId, string $score, string $risk, int $n): void
    {
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => $sectionId,
            'department_id' => $departmentId,
            'average_score' => $score,
            'risk_level' => $risk,
            'respondent_count' => $n,
            'meta' => ['section_title' => $sectionId ? $fx->section->title : null],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardProps(object $campaign): array
    {
        $props = [];
        $this->actingAs($campaign->user)
            ->get(route('client.surveys.psychosocial-dashboard', $campaign->fx->survey))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $page->component('Client/Surveys/PsychosocialDashboard');
                $props = $page->toArray()['props']['dashboard'];
            });

        return $props;
    }

    public function test_cascade_29_19_6_4_publishes_only_sector_a(): void
    {
        $this->assertSame([1], PsychosocialDashboardBuilder::publishableSectorIds([1 => 19, 2 => 6, 3 => 4], 5));

        $campaign = $this->seedCascadeCampaign();
        $dashboard = $this->dashboardProps($campaign);

        $this->assertSame(['Setor A'], array_column($dashboard['sectors'], 'department_name'));
        $this->assertSame(19, $dashboard['sectors'][0]['n']);
        $this->assertCount(1, $dashboard['sectors'][0]['dimensions']);
        $this->assertSame('2.414', $dashboard['overall']['score']);
        $this->assertSame(29, $dashboard['overall']['n']);

        $encoded = json_encode($dashboard, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Setor B', $encoded);
        $this->assertStringNotContainsString('Setor C', $encoded);
        $this->assertStringNotContainsString('Demais setores', $encoded);
        $this->assertStringNotContainsString('"n":10', $encoded);

        $json = $this->actingAs($campaign->user)
            ->get(route('client.surveys.psychosocial-dashboard.export.json', $campaign->fx->survey))
            ->assertOk()
            ->json();
        $this->assertSame(['geral', 'dimensao', 'setor', 'setor_dimensao'], array_column($json['rows'], 'recorte'));
        $this->assertSame(['Setor A', 'Setor A'], array_values(array_filter(array_column($json['rows'], 'setor'))));
        $this->assertNotContains(10, array_column($json['rows'], 'n'));
    }

    public function test_current_screen_and_presenter_suppress_sectors_below_the_floor(): void
    {
        $campaign = $this->seedCascadeCampaign();

        $presented = SurveyResultsPresenter::forSurvey($campaign->fx->survey);
        $this->assertSame([19], $presented['deptOveralls']->pluck('respondent_count')->all());
        $this->assertSame(['Setor A'], array_column($presented['departmentParticipation'], 'department_name'));
        $this->assertSame(['Setor A'], array_column($presented['questionDistributionsByDepartment'], 'department_name'));
        $this->assertSame(['Setor A'], array_column($presented['deptSectionsByDepartment']->all(), 'department_name'));
        // Só a exibição é suprimida: o índice geral continua com as 29 respostas.
        $this->assertSame(29, $presented['overall']->respondent_count);

        $this->actingAs($campaign->user)
            ->get(route('client.surveys.results', $campaign->fx->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Surveys/Results')
                ->has('deptOveralls', 1));
    }

    public function test_missing_eligible_shows_placeholders_without_rate(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $eligible = $this->dashboardProps($campaign)['eligible'];

        $this->assertNull($eligible['count']);
        $this->assertSame('—', $eligible['value_label']);
        $this->assertSame('Aguardando total elegível', $eligible['hint']);
        $this->assertSame('Elegíveis não informados', $eligible['respondents_hint']);
        $this->assertNull($eligible['participation_label']);
    }

    public function test_eligible_below_respondents_alerts_without_rate(): void
    {
        $campaign = $this->seedCascadeCampaign();
        SurveyCampaignRegistration::query()->create([
            'survey_id' => $campaign->fx->survey->id,
            'company_id' => $campaign->fx->company->id,
            'eligible_count' => 20,
        ]);

        $eligible = $this->dashboardProps($campaign)['eligible'];

        $this->assertSame(PsychosocialDashboardBuilder::ELIGIBLE_INCONSISTENT, $eligible['status']);
        $this->assertNotNull($eligible['alert']);
        $this->assertNull($eligible['participation_label']);
    }

    public function test_eligible_at_least_respondents_shows_participation(): void
    {
        $campaign = $this->seedCascadeCampaign();
        SurveyCampaignRegistration::query()->create([
            'survey_id' => $campaign->fx->survey->id,
            'company_id' => $campaign->fx->company->id,
            'eligible_count' => 40,
        ]);

        $this->assertSame('29 de 40', $this->dashboardProps($campaign)['eligible']['participation_label']);
    }

    public function test_hse_pending_audit_shows_stored_number_and_polarity_warning(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $campaign->fx->survey->instrumentVersion->update([
            'instrument_code' => Nr1InstrumentMatrix::CODE_HSE_IT,
            'polarity_audit_status' => Nr1InstrumentMatrix::AUDIT_PENDING,
        ]);

        $dashboard = $this->dashboardProps($campaign);

        $this->assertSame('Polaridade não revalidada', $dashboard['polarity_warning']);
        $this->assertSame('2.414', $dashboard['overall']['score']);
        $this->assertSame('Moderado', $dashboard['overall']['band_label']);
        $this->assertSame(PsychosocialDashboardBuilder::BAND_DISCLAIMER, $dashboard['band_disclaimer']);
        $this->assertSame(PsychosocialDashboardBuilder::LEGENDS['production_v1'], $dashboard['method']['legend']);
    }

    public function test_age_and_tenure_never_reach_payload_or_new_exports(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $survey = $campaign->fx->survey;

        $payload = json_encode($this->dashboardProps($campaign), JSON_UNESCAPED_UNICODE);
        $csv = $this->actingAs($campaign->user)
            ->get(route('client.surveys.psychosocial-dashboard.export.csv', $survey))
            ->assertOk()
            ->streamedContent();
        $json = $this->actingAs($campaign->user)
            ->get(route('client.surveys.psychosocial-dashboard.export.json', $survey))
            ->assertOk()
            ->getContent();

        foreach (['payload' => $payload, 'csv' => $csv, 'json' => $json] as $label => $content) {
            foreach ([self::AGE_MARKER, self::TENURE_MARKER, 'age_range', 'tenure_range', 'faixa_etaria', 'tempo_empresa', 'session_token'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $content, "{$label} não pode conter {$forbidden}.");
            }
        }

        $this->assertSame(5, substr_count(trim(str_replace("\u{FEFF}", '', $csv)), "\n") + 1);
    }

    public function test_items_show_n_and_quality_alert_without_hiding_low_n(): void
    {
        $campaign = $this->seedCascadeCampaign();
        SurveyAnswer::query()
            ->where('survey_template_question_id', $campaign->fx->question->id)
            ->limit(1)
            ->get()
            ->each(fn (SurveyAnswer $answer) => $answer->delete());

        $items = $this->dashboardProps($campaign)['items'];

        $this->assertCount(1, $items);
        $this->assertSame(28, $items[0]['n']);
        $this->assertTrue($items[0]['quality_alert']);
    }

    public function test_opening_and_downloads_write_access_logs(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $survey = $campaign->fx->survey;

        $this->dashboardProps($campaign);
        $this->actingAs($campaign->user)->get(route('client.surveys.psychosocial-dashboard.export.csv', $survey))->assertOk();
        $this->actingAs($campaign->user)->get(route('client.surveys.psychosocial-dashboard.export.json', $survey))->assertOk();

        $logs = PsychosocialAccessLog::query()->orderBy('id')->get();
        $this->assertSame(['dashboard', 'export_csv', 'export_json'], $logs->pluck('resource')->all());

        $dashboardLog = $logs->first();
        $this->assertSame($campaign->user->id, $dashboardLog->user_id);
        $this->assertSame($survey->company_id, $dashboardLog->company_id);
        $this->assertSame($survey->id, $dashboardLog->survey_id);
        $this->assertNull($dashboardLog->document_version_id);
    }

    public function test_survey_results_count_and_values_do_not_change(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $survey = $campaign->fx->survey;
        $snapshot = fn () => DB::table('survey_results')->orderBy('id')
            ->get(['id', 'survey_template_section_id', 'department_id', 'average_score', 'risk_level', 'respondent_count', 'method_version_id'])
            ->map(fn ($row) => (array) $row)
            ->all();
        $before = $snapshot();

        $this->dashboardProps($campaign);
        $this->actingAs($campaign->user)->get(route('client.surveys.psychosocial-dashboard.export.csv', $survey))->assertOk();
        $this->actingAs($campaign->user)->get(route('client.surveys.psychosocial-dashboard.export.json', $survey))->assertOk();

        $this->assertSame($before, $snapshot());
    }

    public function test_dashboard_and_exports_never_call_dompdf(): void
    {
        Pdf::shouldReceive('loadView', 'loadHTML', 'loadFile', 'setPaper')->never();

        $campaign = $this->seedCascadeCampaign();
        $survey = $campaign->fx->survey;

        $this->dashboardProps($campaign);
        $this->actingAs($campaign->user)->get(route('client.surveys.psychosocial-dashboard.export.csv', $survey))->assertOk();
        $this->actingAs($campaign->user)->get(route('client.surveys.psychosocial-dashboard.export.json', $survey))->assertOk();
    }

    public function test_other_company_cannot_open_dashboard(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $other = $this->createSurveyFixture();
        $outsider = User::factory()->companyAdmin($other->company->id)->create();

        $this->actingAs($outsider)
            ->get(route('client.surveys.psychosocial-dashboard', $campaign->fx->survey))
            ->assertNotFound();

        $this->assertSame(0, PsychosocialAccessLog::query()->count());
    }
}
