<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\Department;
use App\Models\PsychosocialAccessLog;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Nr1AiAnalyzer;
use App\Services\SurveyResultCalculator;
use App\Support\Nr1GroupPrivacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

/**
 * Etapa 0 (segurança): política única de grupo, exportação só agregada para a empresa,
 * dado bruto interno e auditado, data só com mês, V11 sem faixa etária/tempo de casa.
 */
class Nr1Stage0PrivacyTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private const AGE_MARKER = 'faixa-etaria-25-34';

    private const TENURE_MARKER = 'tempo-casa-1-3';

    /**
     * Respostas reais (A=19, B=6, C=4) já calculadas pelo calculador oficial.
     *
     * @return object{fx: object, user: User}
     */
    private function seedCampaign(array $surveyOverrides = []): object
    {
        $fx = $this->createSurveyFixture($surveyOverrides);

        foreach (['A' => 19, 'B' => 6, 'C' => 4] as $name => $size) {
            $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => "Setor {$name}"]);

            for ($i = 0; $i < $size; $i++) {
                $response = SurveyResponse::query()->create([
                    'survey_id' => $fx->survey->id,
                    'session_token' => (string) Str::uuid(),
                    'department_id' => $department->id,
                    'age_range' => self::AGE_MARKER,
                    'tenure_range' => self::TENURE_MARKER,
                    'completed_at' => Carbon::parse('2026-10-23 14:33:12'),
                ]);
                SurveyAnswer::query()->create([
                    'survey_response_id' => $response->id,
                    'survey_template_question_id' => $fx->question->id,
                    'value' => 3,
                ]);
            }
        }

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        return (object) [
            'fx' => $fx,
            'user' => User::factory()->companyAdmin($fx->company->id)->create(),
        ];
    }

    public function test_cascade_hides_small_groups_and_subtraction(): void
    {
        $this->assertSame([1], Nr1GroupPrivacy::publishableDepartmentIds([1 => 19, 2 => 6, 3 => 4], 5));
        // Dois ocultos (3 + 2 = 5) já bastam: nenhum setor visível é sacrificado (saída em ordem crescente de n).
        $this->assertSame([2, 1], Nr1GroupPrivacy::publishableDepartmentIds([1 => 19, 2 => 6, 3 => 3, 4 => 2], 5));
        // Um oculto de 3 com soma < 5 arrasta o menor visível.
        $this->assertSame([1], Nr1GroupPrivacy::publishableDepartmentIds([1 => 19, 2 => 7, 3 => 3], 5));
        $this->assertSame([], Nr1GroupPrivacy::publishableDepartmentIds([1 => 4, 2 => 3], 5));
        $this->assertSame([1, 2], Nr1GroupPrivacy::publishableDepartmentIds([2 => 5, 1 => 5], 5));
        $this->assertSame([], Nr1GroupPrivacy::publishableDepartmentIds([], 5));
    }

    public function test_min_group_size_floor_is_five_and_only_goes_up(): void
    {
        $fx = $this->createSurveyFixture();
        $this->assertSame(5, (int) $fx->survey->fresh()->min_group_size);
        $this->assertSame(5, Nr1GroupPrivacy::minFor($fx->survey->fresh()));

        $this->expectException(\InvalidArgumentException::class);
        $fx->survey->update(['min_group_size' => 4]);
    }

    public function test_campaign_form_rejects_below_five_and_accepts_higher(): void
    {
        $fx = $this->createSurveyFixture();
        $user = User::factory()->companyAdmin($fx->company->id)->create();
        $payload = ['survey_template_id' => $fx->template->id, 'title' => 'Nova', 'status' => 'active'];

        $this->actingAs($user)
            ->post(route('client.surveys.store'), $payload + ['min_group_size' => 1])
            ->assertSessionHasErrors('min_group_size');

        $this->actingAs($user)
            ->post(route('client.surveys.store'), $payload + ['min_group_size' => 8])
            ->assertSessionHasNoErrors();

        $created = Survey::query()->where('title', 'Nova')->firstOrFail();
        $this->assertSame(8, (int) $created->min_group_size);

        $this->actingAs($user)
            ->put(route('client.surveys.update', $created), ['title' => 'Nova', 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame(8, (int) $created->fresh()->min_group_size);

        $this->actingAs($user)
            ->put(route('client.surveys.update', $created), ['title' => 'Nova', 'status' => 'active', 'min_group_size' => 3])
            ->assertSessionHasErrors('min_group_size');
        $this->assertSame(8, (int) $created->fresh()->min_group_size);
    }

    public function test_raising_the_minimum_hides_more_sectors(): void
    {
        $campaign = $this->seedCampaign();
        $campaign->fx->survey->update(['min_group_size' => 20]);

        $this->actingAs($campaign->user)
            ->get(route('client.surveys.psychosocial-dashboard', $campaign->fx->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('dashboard.sectors', []));
    }

    public function test_company_csv_export_is_aggregated_only(): void
    {
        $campaign = $this->seedCampaign();

        $response = $this->actingAs($campaign->user)
            ->get(route('client.surveys.export.csv', $campaign->fx->survey))
            ->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString('Setor A', $body);
        $this->assertStringNotContainsString('Setor B', $body);
        $this->assertStringNotContainsString('Setor C', $body);
        foreach ([self::AGE_MARKER, self::TENURE_MARKER, '2026-10', 'session_token', 'linha_resposta', 'valor_likert'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body);
        }
        $this->assertSame(1, PsychosocialAccessLog::query()->where('resource', 'export_csv')->count());
    }

    public function test_company_json_export_has_no_rows_and_no_small_sector(): void
    {
        $campaign = $this->seedCampaign();

        $json = $this->actingAs($campaign->user)
            ->get(route('client.surveys.export.json', $campaign->fx->survey))
            ->assertOk()
            ->json();
        $encoded = json_encode($json, JSON_UNESCAPED_UNICODE);

        $this->assertArrayNotHasKey('responses_anonymized', $json);
        $this->assertStringContainsString('Setor A', $encoded);
        $this->assertStringNotContainsString('Setor B', $encoded);
        $this->assertStringNotContainsString('Setor C', $encoded);
        $this->assertStringNotContainsString(self::AGE_MARKER, $encoded);
        $this->assertStringNotContainsString('min_responses_for_breakdown', $encoded);
        $this->assertSame(5, $json['aggregated']['min_group_size']);
    }

    public function test_ai_payload_never_carries_small_sectors(): void
    {
        $campaign = $this->seedCampaign();

        $payload = app(Nr1AiAnalyzer::class)->buildAggregatedPayload($campaign->fx->survey->fresh());
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Setor A', $encoded);
        $this->assertStringNotContainsString('Setor B', $encoded);
        $this->assertStringNotContainsString('Setor C', $encoded);
        $this->assertArrayNotHasKey('min_responses_for_breakdown', $payload);
        $this->assertSame(['Setor A'], array_column($payload['by_department_summary'], 'department'));
        $this->assertSame(29, $payload['overall']['respondent_count']);
    }

    public function test_raw_export_is_internal_audited_and_without_demographics(): void
    {
        $campaign = $this->seedCampaign();
        $owner = User::factory()->superAdmin()->mfaVerified()->create(['is_owner' => true]);
        $url = route('admin.companies.surveys.raw-export.csv', [$campaign->fx->company->id, $campaign->fx->survey->id]);

        $body = $this->actingAsMfa($owner)->get($url)->assertOk()->streamedContent();

        $this->assertStringContainsString('Setor C', $body);
        $this->assertStringContainsString('2026-10', $body);
        $this->assertStringNotContainsString('2026-10-23', $body);
        $this->assertStringNotContainsString('14:33', $body);
        foreach ([self::AGE_MARKER, self::TENURE_MARKER, 'session_token'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body);
        }

        $log = PsychosocialAccessLog::query()->where('resource', 'raw_export')->first();
        $this->assertNotNull($log);
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame($campaign->fx->survey->id, (int) $log->survey_id);
    }

    public function test_raw_export_needs_the_specific_permission_and_never_reaches_clients(): void
    {
        $campaign = $this->seedCampaign();
        $url = route('admin.companies.surveys.raw-export.csv', [$campaign->fx->company->id, $campaign->fx->survey->id]);

        $this->actingAs($campaign->user)->get($url)->assertForbidden();

        $plain = User::factory()->superAdmin()->create();
        $this->actingAs($plain)->get($url)->assertForbidden();

        $granted = User::factory()->superAdmin()->mfaVerified()->create();
        AdminUserPermission::query()->create([
            'user_workspace_id' => $granted->workspaces()->firstOrFail()->id,
            'module' => AdminPermissionModule::Nr1DadosBrutos,
            'action' => PermissionAction::View,
        ]);
        foreach ([AdminPermissionModule::Companies] as $module) {
            AdminUserPermission::query()->create([
                'user_workspace_id' => $granted->workspaces()->firstOrFail()->id,
                'module' => $module,
                'action' => PermissionAction::View,
            ]);
        }
        $this->actingAsMfa($granted)->get($url)->assertOk();
        $this->assertSame(1, PsychosocialAccessLog::query()->where('resource', 'raw_export')->count());
    }

    public function test_raw_export_of_a_survey_from_another_company_is_not_found(): void
    {
        $campaign = $this->seedCampaign();
        $other = $this->createSurveyFixture();
        $owner = User::factory()->superAdmin()->mfaVerified()->create(['is_owner' => true]);

        $this->actingAsMfa($owner)
            ->get(route('admin.companies.surveys.raw-export.csv', [$other->company->id, $campaign->fx->survey->id]))
            ->assertNotFound();
    }

    public function test_completion_is_stored_with_month_granularity(): void
    {
        Carbon::setTestNow('2026-10-23 14:33:12');
        $fx = $this->createSurveyFixture();

        $this->post(route('survey.public.submit', $fx->survey->public_token), [
            'answers' => [$fx->question->id => 4],
        ])->assertRedirect();

        $stored = SurveyResponse::query()->where('survey_id', $fx->survey->id)->firstOrFail()->completed_at;
        $this->assertSame('2026-10-01 00:00:00', $stored->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10', Nr1GroupPrivacy::month($stored));
        $this->assertNull(Nr1GroupPrivacy::month(null));

        Carbon::setTestNow();
    }

    public function test_v11_campaign_does_not_collect_age_or_tenure_but_legacy_does(): void
    {
        $talents39 = SurveyMethodVersion::query()->where('code', SurveyMethodVersion::CODE_TALENTS39_V1)->firstOrFail();
        $v11 = $this->createSurveyFixture(['method_version_id' => $talents39->id]);
        $legacy = $this->createSurveyFixture();
        $payload = fn ($fx) => [
            'answers' => [$fx->question->id => 4],
            'age_range' => '25-34',
            'tenure_range' => '1-3',
        ];

        $this->post(route('survey.public.submit', $v11->survey->public_token), $payload($v11))->assertRedirect();
        $this->post(route('survey.public.submit', $legacy->survey->public_token), $payload($legacy))->assertRedirect();

        $v11Row = SurveyResponse::query()->where('survey_id', $v11->survey->id)->firstOrFail();
        $legacyRow = SurveyResponse::query()->where('survey_id', $legacy->survey->id)->firstOrFail();
        $this->assertNull($v11Row->age_range);
        $this->assertNull($v11Row->tenure_range);
        $this->assertSame('25-34', $legacyRow->age_range);
        $this->assertSame('1-3', $legacyRow->tenure_range);

        $this->get(route('survey.public', $v11->survey->public_token))
            ->assertInertia(fn (Assert $page) => $page->where('collectDemographics', false));
        $this->get(route('survey.public', $legacy->survey->public_token))
            ->assertInertia(fn (Assert $page) => $page->where('collectDemographics', true));
    }

    public function test_other_company_cannot_use_dashboard_exports_or_documents(): void
    {
        $campaign = $this->seedCampaign();
        $outsiderFx = $this->createSurveyFixture();
        $outsider = User::factory()->companyAdmin($outsiderFx->company->id)->create();
        $survey = $campaign->fx->survey;

        $this->actingAs($outsider)->get(route('client.surveys.psychosocial-dashboard.export.csv', $survey))->assertNotFound();
        $this->actingAs($outsider)->get(route('client.surveys.psychosocial-dashboard.export.json', $survey))->assertNotFound();
        $this->actingAs($outsider)->get(route('client.surveys.executive-brief.download', $survey))->assertNotFound();
        foreach (['executive_brief', 'pgr_subsidy', 'evidence_dossier'] as $type) {
            $this->actingAs($outsider)
                ->get(route('client.surveys.psychosocial-documents.download', [$survey, $type]))
                ->assertNotFound();
            $this->actingAs($outsider)
                ->post(route('client.surveys.psychosocial-documents.validate', [$survey, $type]))
                ->assertStatus(404);
        }
    }
}
