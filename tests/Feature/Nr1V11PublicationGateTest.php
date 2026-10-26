<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Models\User;
use App\Support\SurveyPublicationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * T02: a empresa não vê nada de uma campanha que exige publicação antes de "Publicado".
 * Campanhas anteriores ao gate (requires_publication = false) seguem como estavam (D9).
 */
class Nr1V11PublicationGateTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    /** Rotas da empresa com {survey} que NÃO expõem resultado (gestão da coleta). */
    private const COLLECTION_ROUTES = [
        'client.surveys.show',
        'client.surveys.edit',
        'client.surveys.update',
        'client.surveys.audiences.qr',
        'client.surveys.audiences.store',
        'client.surveys.audiences.update',
        'client.surveys.recalculate',
    ];

    private function unpublished(): object
    {
        $campaign = $this->v11Campaign();
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => null])->save();

        return $campaign;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function resultRoutes(Survey $survey): array
    {
        return [
            ['get', route('client.surveys.results', $survey)],
            ['get', route('client.surveys.psychosocial-dashboard', $survey)],
            ['get', route('client.surveys.psychosocial-dashboard.print', $survey)],
            ['get', route('client.surveys.psychosocial-dashboard.export.csv', $survey)],
            ['get', route('client.surveys.psychosocial-dashboard.export.json', $survey)],
            ['get', route('client.surveys.psychosocial-dashboard.export.items', $survey)],
            ['get', route('client.surveys.action-plan', $survey)],
            ['get', route('client.surveys.action-plan.technical-opinion-file', $survey)],
            ['get', route('client.surveys.reports.executive', $survey)],
            ['get', route('client.surveys.reports.technical', $survey)],
            ['get', route('client.surveys.reports.referral', $survey)],
            ['get', route('client.surveys.reports.action-plan', $survey)],
            ['get', route('client.surveys.export.json', $survey)],
            ['get', route('client.surveys.export.csv', $survey)],
            ['get', route('client.surveys.executive-brief.download', $survey)],
            ['get', route('client.surveys.psychosocial-documents.download', [$survey, 'pgr_subsidy'])],
            ['post', route('client.surveys.psychosocial-documents.validate', [$survey, 'pgr_subsidy'])],
            ['post', route('client.surveys.ai-analysis', $survey)],
        ];
    }

    public function test_unpublished_campaign_is_not_found_on_every_company_result_route(): void
    {
        $campaign = $this->unpublished();
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();

        foreach ($this->resultRoutes($campaign->survey) as [$method, $url]) {
            $this->actingAs($admin)->{$method}($url)->assertNotFound("{$method} {$url} não deveria responder antes de Publicado.");
        }
    }

    public function test_published_campaign_opens_the_company_panels(): void
    {
        $campaign = $this->unpublished();
        $campaign->survey->forceFill(['published_at' => now()])->save();
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();

        foreach ([
            route('client.surveys.psychosocial-dashboard', $campaign->survey),
            route('client.surveys.psychosocial-dashboard.print', $campaign->survey),
            route('client.surveys.results', $campaign->survey),
            route('client.surveys.action-plan', $campaign->survey),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get(route('client.surveys.psychosocial-dashboard.export.csv', $campaign->survey))->assertOk()->streamedContent();
    }

    public function test_campaigns_that_predate_the_gate_are_unchanged(): void
    {
        $campaign = $this->v11Campaign();
        $this->assertFalse((bool) $campaign->survey->fresh()->requires_publication);
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();

        $this->actingAs($admin)->get(route('client.surveys.psychosocial-dashboard', $campaign->survey))->assertOk();
        $this->actingAs($admin)->get(route('client.surveys.psychosocial-dashboard.print', $campaign->survey))->assertOk();
        $this->assertTrue(SurveyPublicationGate::allows($campaign->survey->fresh()));
    }

    public function test_collection_management_stays_available_before_publication(): void
    {
        $campaign = $this->unpublished();
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();

        $this->actingAs($admin)->get(route('client.surveys.show', $campaign->survey))->assertOk();
        $this->actingAs($admin)->get(route('client.surveys.edit', $campaign->survey))->assertOk();
    }

    public function test_collection_screens_never_expose_scores_before_publication(): void
    {
        $campaign = $this->unpublished();
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();
        $forbidden = ['average_score', 'risk_level', 'respondent_count', 'survey_results', 'Demandas e Sobrecarga', '2,60', '2.60', '3,30', '3.30'];

        $this->actingAs($admin)->post(route('client.surveys.audiences.store', $campaign->survey))->assertRedirect();
        $link = $campaign->survey->audienceLinks()->firstOrFail();

        foreach ([
            route('client.surveys.index'),
            route('client.surveys.show', $campaign->survey),
            route('client.surveys.edit', $campaign->survey),
        ] as $url) {
            $props = json_encode(
                $this->actingAs($admin)->get($url)->assertOk()->viewData('page')['props'],
                JSON_UNESCAPED_UNICODE,
            );
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $props, "{$url} expôs «{$needle}» antes de Publicado.");
            }
        }

        $qr = $this->actingAs($admin)->get(route('client.surveys.audiences.qr', [$campaign->survey, $link]))->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $qr->headers->get('Content-Type'));

        $this->actingAs($admin)
            ->patch(route('client.surveys.audiences.update', [$campaign->survey, $link]), ['enabled' => true])
            ->assertRedirect()
            ->assertSessionHas('success', 'Link atualizado.');

        $this->actingAs($admin)
            ->post(route('client.surveys.recalculate', $campaign->survey))
            ->assertRedirect()
            ->assertSessionHas('success', 'Resultados recalculados.');
    }

    public function test_plan_item_route_is_gated_through_the_items_survey(): void
    {
        $campaign = $this->unpublished();
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();
        $plan = ActionPlan::query()->create([
            'survey_id' => $campaign->survey->id,
            'company_id' => $campaign->company->id,
        ]);
        $item = ActionPlanItem::query()->create([
            'action_plan_id' => $plan->id,
            'title' => 'Ação',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->patch(route('client.action-plan-items.update', $item), ['status' => 'in_progress'])->assertNotFound();
        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_company_home_does_not_leak_results_or_plan_before_publication(): void
    {
        $campaign = $this->unpublished();
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();
        $this->assertNotNull(SurveyResult::query()->where('survey_id', $campaign->survey->id)->first(), 'A campanha tem resultados calculados.');

        $this->actingAs($admin)->get(route('client.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overallRisk', null)
                ->where('lastCampaign.section_results', [])
                ->where('actionPlanHref', null));

        $campaign->survey->forceFill(['published_at' => now()])->save();

        $this->actingAs($admin)->get(route('client.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overallRisk.average_score', fn ($score) => (float) $score > 0)
                ->has('lastCampaign.section_results')
                ->where('actionPlanHref', route('client.surveys.action-plan', $campaign->survey)));
    }

    public function test_open_plan_items_of_unpublished_campaigns_do_not_reach_the_home(): void
    {
        $campaign = $this->unpublished();
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();
        $plan = ActionPlan::query()->create(['survey_id' => $campaign->survey->id, 'company_id' => $campaign->company->id]);
        ActionPlanItem::query()->create(['action_plan_id' => $plan->id, 'title' => 'Ação oculta', 'status' => 'pending']);

        $this->actingAs($admin)->get(route('client.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('openActionPlanCount', 0)->has('openActionPlanItems', 0));

        $campaign->survey->forceFill(['published_at' => now()])->save();

        $this->actingAs($admin)->get(route('client.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('openActionPlanCount', 1)->has('openActionPlanItems', 1));
    }

    public function test_new_campaigns_created_by_the_company_require_publication(): void
    {
        $campaign = $this->v11Campaign(withResponses: false);
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();

        $this->actingAs($admin)->post(route('client.surveys.store'), [
            'survey_template_id' => $campaign->template->id,
            'title' => 'Campanha nova',
        ])->assertRedirect();

        $created = Survey::query()->where('title', 'Campanha nova')->sole();
        $this->assertTrue($created->requires_publication);
        $this->assertNull($created->published_at);
        $this->assertFalse(SurveyPublicationGate::allows($created));
    }

    public function test_talents_publishes_once_and_only_from_the_companys_own_campaign(): void
    {
        $campaign = $this->unpublished();
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $other = $this->createSurveyFixture();
        $url = route('admin.companies.surveys.publication.store', [$campaign->company->id, $campaign->survey->id]);

        $this->actingAs($owner)
            ->post(route('admin.companies.surveys.publication.store', [$other->company->id, $campaign->survey->id]))
            ->assertNotFound();
        $this->assertNull($campaign->survey->fresh()->published_at);

        $this->actingAs($owner)->post($url)->assertRedirect()->assertSessionHas('success');
        $published = $campaign->survey->fresh();
        $this->assertNotNull($published->published_at);
        $this->assertSame($owner->id, (int) $published->published_by);

        $first = $published->published_at->toIso8601String();
        $this->travel(2)->hours();
        $this->actingAs($owner)->post($url)->assertRedirect();
        $this->assertSame($first, $campaign->survey->fresh()->published_at->toIso8601String(), 'Publicar de novo não muda a data.');
    }

    public function test_company_and_plain_admin_cannot_publish(): void
    {
        $campaign = $this->unpublished();
        $url = route('admin.companies.surveys.publication.store', [$campaign->company->id, $campaign->survey->id]);

        $this->actingAs(User::factory()->companyAdmin($campaign->company->id)->create())->post($url)->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->post($url)->assertForbidden();
        $this->assertNull($campaign->survey->fresh()->published_at);
    }

    public function test_every_company_route_with_a_survey_parameter_is_gated_or_explicitly_allowed(): void
    {
        $missing = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route) => str_starts_with($route->getName() ?? '', 'client.')
                && in_array('survey', $route->parameterNames(), true))
            ->reject(fn (LaravelRoute $route) => in_array($route->getName(), self::COLLECTION_ROUTES, true))
            ->reject(fn (LaravelRoute $route) => in_array('survey.published', $route->gatherMiddleware(), true))
            ->map(fn (LaravelRoute $route) => $route->getName().' ('.$route->uri().')')
            ->values()
            ->all();

        $this->assertSame([], $missing, 'Rota da empresa com {survey} sem o gate de publicação e fora da lista de coleta.');
    }
}
