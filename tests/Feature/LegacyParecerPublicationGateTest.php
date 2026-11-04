<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\Department;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class LegacyParecerPublicationGateTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @return object{fx: object, admin: User}
     */
    private function seedLegacyCampaign(): object
    {
        $fx = $this->createSurveyFixture();

        $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => 'Setor A']);
        for ($i = 0; $i < 12; $i++) {
            $response = SurveyResponse::query()->create([
                'survey_id' => $fx->survey->id,
                'session_token' => (string) Str::uuid(),
                'department_id' => $department->id,
                'age_range' => 'faixa-etaria-25-34',
                'tenure_range' => 'tempo-casa-1-3',
                'completed_at' => now(),
            ]);
            SurveyAnswer::query()->create([
                'survey_response_id' => $response->id,
                'survey_template_question_id' => $fx->question->id,
                'value' => 3,
            ]);
        }

        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => null,
            'department_id' => null,
            'average_score' => '2.500',
            'risk_level' => 'yellow',
            'respondent_count' => 12,
            'meta' => null,
        ]);
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => $fx->section->id,
            'department_id' => null,
            'average_score' => '2.500',
            'risk_level' => 'yellow',
            'respondent_count' => 12,
            'meta' => ['section_title' => $fx->section->title],
        ]);

        return (object) [
            'fx' => $fx,
            'admin' => User::factory()->superAdmin()->create(['is_owner' => true]),
        ];
    }

    private function releaseAndValidateBrief(object $campaign): PsychosocialDocumentVersion
    {
        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.executive-brief.release', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
            ]))
            ->assertRedirect();

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.executive-brief.validate', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
            ]), ['declaration_accepted' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return PsychosocialDocumentVersion::query()
            ->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)
            ->latest('id')
            ->firstOrFail();
    }

    public function test_publishing_parecer_without_validated_brief_is_rejected(): void
    {
        $campaign = $this->seedLegacyCampaign();

        $this->actingAs($campaign->admin)
            ->put(route('admin.companies.surveys.action-plan.update', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
            ]), [
                'technical_opinion' => '<p>Parecer sem resumo validado.</p>',
            ])
            ->assertSessionHasErrors('technical_opinion');

        $this->assertNull(
            ActionPlan::query()->where('survey_id', $campaign->fx->survey->id)->value('admin_published_at')
        );
    }

    public function test_publishing_parecer_with_released_but_unvalidated_brief_is_rejected(): void
    {
        $campaign = $this->seedLegacyCampaign();

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.executive-brief.release', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
            ]))
            ->assertRedirect();

        $this->assertSame(
            PsychosocialDocumentVersion::KIND_RELEASED,
            PsychosocialDocument::query()->sole()->currentVersion->kind
        );

        $this->actingAs($campaign->admin)
            ->put(route('admin.companies.surveys.action-plan.update', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
            ]), [
                'technical_opinion' => '<p>Parecer com resumo só liberado.</p>',
            ])
            ->assertSessionHasErrors('technical_opinion');
    }

    public function test_publishing_parecer_with_validated_brief_succeeds(): void
    {
        $campaign = $this->seedLegacyCampaign();
        $this->releaseAndValidateBrief($campaign);

        $this->actingAs($campaign->admin)
            ->put(route('admin.companies.surveys.action-plan.update', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
            ]), [
                'technical_opinion' => '<p>Parecer com resumo validado.</p>',
            ])
            ->assertRedirect(route('nr1.parecer', [
                'empresa' => $campaign->fx->company->id,
                'campanha' => $campaign->fx->survey->id,
            ]))
            ->assertSessionHasNoErrors();

        $plan = ActionPlan::query()->where('survey_id', $campaign->fx->survey->id)->firstOrFail();
        $this->assertNotNull($plan->admin_published_at);
        $this->assertStringContainsString('Parecer com resumo validado.', (string) $plan->technical_opinion);
    }

    public function test_talents_can_validate_executive_brief_in_system_without_proof(): void
    {
        $campaign = $this->seedLegacyCampaign();

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.executive-brief.release', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
            ]))
            ->assertRedirect();

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.executive-brief.validate', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
            ]), ['declaration_accepted' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $validated = PsychosocialDocumentVersion::query()
            ->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)
            ->sole();
        $this->assertSame(PsychosocialDocumentVersion::SEAL_VALIDATED, $validated->seal);
    }

    public function test_legacy_parecer_shell_exposes_brief_validation_flags_without_dossier_toolbar(): void
    {
        $campaign = $this->seedLegacyCampaign();
        $this->releaseAndValidateBrief($campaign);

        $legacy = (string) file_get_contents(resource_path('js/Components/Nr1Shell/LegacyDocumentsView.vue'));
        $this->assertStringNotContainsString('Liberar resumo executivo', $legacy);
        $this->assertStringContainsString('openExecutiveBriefModal', $legacy);
        $this->assertStringContainsString('openPgrSubsidyModal', $legacy);

        $this->actingAs($campaign->admin)
            ->get(route('nr1.parecer', [
                'empresa' => $campaign->fx->company->id,
                'campanha' => $campaign->fx->survey->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('section.legacy.documents.laudo.executiveBrief.validated', true)
                ->where('section.legacy.documents.laudo.executiveBrief.ready', true)
                ->where('section.legacy.documents.laudo.executiveBrief.validatable', false)
                ->has('section.legacy.documents.laudo.executiveBrief.urls.validate')
                ->has('section.legacy.documents.laudo.pgrSubsidy.urls.release')
            );
    }
}
