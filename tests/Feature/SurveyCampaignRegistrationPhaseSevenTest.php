<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PsychosocialDocument;
use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\PsychosocialDashboardBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class SurveyCampaignRegistrationPhaseSevenTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private const CONTENT_FIELDS = [
        'unit_name',
        'process_name',
        'legal_name_snapshot',
        'cnpj_snapshot',
        'eligible_count',
        'eligible_source',
        'exposure_groups',
        'aep_summary',
        'observation_summary',
        'existing_controls',
        'technical_responsible_name',
        'technical_responsible_role',
        'technical_responsible_registration',
        'approving_organization_name',
    ];

    private function admin(): User
    {
        return User::factory()->superAdmin()->create(['is_owner' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return array_merge(array_fill_keys(self::CONTENT_FIELDS, ''), ['exposure_groups' => []]);
    }

    private function editUrl(Company $company, Survey $survey): string
    {
        return route('admin.companies.surveys.registration.edit', [$company->id, $survey->id]);
    }

    private function updateUrl(Company $company, Survey $survey): string
    {
        return route('admin.companies.surveys.registration.update', [$company->id, $survey->id]);
    }

    public function test_opening_screen_does_not_create_row(): void
    {
        $fx = $this->createSurveyFixture();

        $this->actingAs($this->admin())
            ->get($this->editUrl($fx->company, $fx->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/CampaignRegistration/Edit')
                ->where('registration.eligible_count', null)
                ->where('lastUpdate', null));

        $this->assertSame(0, SurveyCampaignRegistration::query()->count());
    }

    public function test_saving_everything_empty_creates_row_with_null_content(): void
    {
        $fx = $this->createSurveyFixture();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put($this->updateUrl($fx->company, $fx->survey), $this->emptyPayload())
            ->assertRedirect($this->editUrl($fx->company, $fx->survey))
            ->assertSessionHasNoErrors();

        $row = SurveyCampaignRegistration::query()->sole();
        $this->assertSame($fx->survey->id, $row->survey_id);
        $this->assertSame($fx->company->id, $row->company_id);
        $this->assertSame($admin->id, $row->updated_by);
        foreach (self::CONTENT_FIELDS as $field) {
            $this->assertNull($row->getAttribute($field), "{$field} deveria ser nulo.");
        }
    }

    public function test_eligible_40_makes_dashboard_show_29_of_40(): void
    {
        $fx = $this->createSurveyFixture();
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => null,
            'department_id' => null,
            'average_score' => '2.414',
            'risk_level' => 'yellow',
            'respondent_count' => 29,
            'meta' => [],
        ]);

        $this->actingAs($this->admin())
            ->put($this->updateUrl($fx->company, $fx->survey), array_merge($this->emptyPayload(), ['eligible_count' => '40']))
            ->assertSessionHasNoErrors();

        $eligible = app(PsychosocialDashboardBuilder::class)->build($fx->survey->fresh())['eligible'];
        $this->assertSame(40, $eligible['count']);
        $this->assertSame('29 de 40', $eligible['participation_label']);
    }

    public function test_blank_text_and_empty_groups_become_null(): void
    {
        $fx = $this->createSurveyFixture();
        $admin = $this->admin();
        $payload = array_merge(
            array_fill_keys(array_diff(self::CONTENT_FIELDS, ['eligible_count', 'exposure_groups']), '   '),
            ['eligible_count' => '', 'exposure_groups' => ['   ', '']],
        );

        $this->actingAs($admin)->put($this->updateUrl($fx->company, $fx->survey), $payload)->assertSessionHasNoErrors();

        $row = SurveyCampaignRegistration::query()->sole();
        foreach (self::CONTENT_FIELDS as $field) {
            $this->assertNull($row->getAttribute($field), "{$field} deveria ser nulo.");
        }

        $this->actingAs($admin)->put($this->updateUrl($fx->company, $fx->survey), array_merge($this->emptyPayload(), [
            'unit_name' => '  Matriz  ',
            'exposure_groups' => ['Operação', '  ', ' Administrativo '],
        ]))->assertSessionHasNoErrors();

        $row->refresh();
        $this->assertSame('Matriz', $row->unit_name);
        $this->assertSame(['Operação', 'Administrativo'], $row->exposure_groups);
        $this->assertSame(1, SurveyCampaignRegistration::query()->count());
    }

    public function test_typed_zero_is_stored_and_negative_or_fraction_is_refused(): void
    {
        $fx = $this->createSurveyFixture();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put($this->updateUrl($fx->company, $fx->survey), array_merge($this->emptyPayload(), ['eligible_count' => '0']))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, SurveyCampaignRegistration::query()->sole()->eligible_count);

        foreach (['-1', '2.5', 'abc'] as $invalid) {
            $this->actingAs($admin)
                ->put($this->updateUrl($fx->company, $fx->survey), array_merge($this->emptyPayload(), ['eligible_count' => $invalid]))
                ->assertSessionHasErrors('eligible_count');
        }

        $this->assertSame(0, SurveyCampaignRegistration::query()->sole()->eligible_count);
    }

    public function test_company_snapshot_does_not_change_company(): void
    {
        $fx = $this->createSurveyFixture();
        $fx->company->update(['legal_name' => 'Razão Atual Ltda']);

        $this->actingAs($this->admin())->put($this->updateUrl($fx->company, $fx->survey), array_merge($this->emptyPayload(), [
            'legal_name_snapshot' => 'Razão Digitada Ltda',
            'cnpj_snapshot' => '22.222.222/0001-22',
        ]))->assertSessionHasNoErrors();

        $company = $fx->company->fresh();
        $this->assertSame('Razão Atual Ltda', $company->legal_name);
        $this->assertSame('11.111.111/0001-11', $company->cnpj);
        $this->assertSame('Razão Digitada Ltda', SurveyCampaignRegistration::query()->sole()->legal_name_snapshot);
    }

    public function test_campaign_from_another_company_returns_404(): void
    {
        $fx = $this->createSurveyFixture();
        $other = $this->createSurveyFixture();
        $admin = $this->admin();

        $this->actingAs($admin)->get($this->editUrl($fx->company, $other->survey))->assertNotFound();
        $this->actingAs($admin)
            ->put($this->updateUrl($fx->company, $other->survey), array_merge($this->emptyPayload(), ['eligible_count' => '10']))
            ->assertNotFound();

        $this->assertSame(0, SurveyCampaignRegistration::query()->count());
    }

    public function test_no_document_and_no_dompdf(): void
    {
        Pdf::shouldReceive('loadView', 'loadHTML', 'loadFile', 'setPaper')->never();
        $fx = $this->createSurveyFixture();
        $admin = $this->admin();

        $this->actingAs($admin)->get($this->editUrl($fx->company, $fx->survey))->assertOk();
        $this->actingAs($admin)
            ->put($this->updateUrl($fx->company, $fx->survey), array_merge($this->emptyPayload(), ['eligible_count' => '12']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, PsychosocialDocument::query()->count());
    }
}
