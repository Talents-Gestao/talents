<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\Department;
use App\Models\Survey;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Nr1CommercialSerializer;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

/**
 * Etapa 4B: Comercial limitado. Só metadados e agregados de campanha liberada; nunca resposta bruta.
 */
class Nr1V11CommercialTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private const SESSION_MARKER = 'sessao-marcador-secreta';

    private function commercial(array $modules = [AdminPermissionModule::Nr1Oportunidades]): User
    {
        $user = User::factory()->superAdmin()->create();
        $workspaceId = $user->workspaces()->firstOrFail()->id;

        foreach ($modules as $module) {
            AdminUserPermission::query()->create([
                'user_workspace_id' => $workspaceId,
                'module' => $module,
                'action' => PermissionAction::View,
            ]);
        }

        return $user;
    }

    /**
     * Grande (8) e Médio (6) publicáveis só em parte pela cascata; Pequeno (2) sempre oculto.
     */
    private function campaign(bool $released = true): object
    {
        $fx = $this->createSurveyFixture([
            'method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1),
            'title' => 'Campanha Comercial',
        ]);

        foreach (['Grande' => 8, 'Médio' => 6, 'Pequeno' => 2] as $name => $size) {
            $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => $name]);
            for ($i = 0; $i < $size; $i++) {
                SurveyResponse::query()->create([
                    'survey_id' => $fx->survey->id,
                    'session_token' => self::SESSION_MARKER.'-'.Str::uuid(),
                    'department_id' => $department->id,
                    'completed_at' => now()->startOfMonth(),
                ])->answers()->create(['survey_template_question_id' => $fx->question->id, 'value' => 4]);
            }
        }

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        if ($released) {
            $fx->survey->forceFill(['commercial_released_at' => now()])->save();
        }

        return $fx;
    }

    public function test_unreleased_campaign_is_invisible_to_commercial(): void
    {
        $fx = $this->campaign(released: false);
        $user = $this->commercial();

        $this->actingAs($user)->get(route('admin.nr1-opportunities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('surveys', 0));
        $this->actingAs($user)->get(route('admin.nr1-opportunities.show', $fx->survey->id))->assertNotFound();
    }

    public function test_released_campaign_shows_only_aggregates_under_the_group_minimum_policy(): void
    {
        $fx = $this->campaign();
        $user = $this->commercial();

        $this->actingAs($user)->get(route('admin.nr1-opportunities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('surveys', 1)->where('surveys.0.id', $fx->survey->id));

        $response = $this->actingAs($user)->get(route('admin.nr1-opportunities.show', $fx->survey->id))->assertOk();
        $payload = $response->viewData('page')['props']['opportunity'];

        $this->assertSame(16, $payload['respondents']);
        $names = array_column($payload['sectors'], 'name');
        $this->assertNotContains('Pequeno', $names, 'Grupo abaixo do mínimo não sai.');
        foreach ($payload['sectors'] as $sector) {
            $this->assertGreaterThanOrEqual(5, $sector['n']);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString(self::SESSION_MARKER, $json);
        $this->assertStringNotContainsString('Pequeno', $json);
        foreach (['session_token', 'answers', 'survey_answers', 'items', 'age_range', 'tenure', 'respondent'] as $forbidden) {
            $this->assertStringNotContainsString('"'.$forbidden.'"', $json, "Chave proibida no payload: {$forbidden}");
        }
    }

    public function test_serializer_payload_is_a_closed_whitelist(): void
    {
        $fx = $this->campaign();
        $detail = app(Nr1CommercialSerializer::class)->detail(Survey::query()->with('company')->findOrFail($fx->survey->id));

        $this->assertSame(
            ['id', 'title', 'company_name', 'status', 'starts_at', 'ends_at', 'released_at', 'min_group_size', 'respondents', 'method_label', 'overall', 'dimensions', 'sectors', 'others'],
            array_keys($detail),
        );
        $this->assertSame(['n', 'score', 'band_label'], array_keys($detail['overall']));
        foreach ($detail['dimensions'] as $dimension) {
            $this->assertSame(['title', 'n', 'score', 'band_label'], array_keys($dimension));
        }
        foreach ($detail['sectors'] as $sector) {
            $this->assertSame(['name', 'n', 'score', 'band_label', 'dimensions'], array_keys($sector));
        }
    }

    public function test_commercial_module_reaches_nothing_else(): void
    {
        $fx = $this->campaign();
        $user = $this->commercial();

        $blocked = [
            ['get', route('admin.companies.surveys.raw-export.csv', [$fx->company->id, $fx->survey->id])],
            ['get', route('admin.companies.index')],
            ['post', route('admin.companies.surveys.commercial-release.store', [$fx->company->id, $fx->survey->id])],
            ['delete', route('admin.companies.surveys.commercial-release.destroy', [$fx->company->id, $fx->survey->id])],
            ['get', route('admin.companies.surveys.psychosocial-documents.download', [$fx->company->id, $fx->survey->id, 'pgr_subsidy'])],
            ['get', route('admin.survey-templates.index')],
        ];

        foreach ($blocked as [$method, $url]) {
            $this->actingAs($user)->{$method}($url)->assertForbidden();
        }

        $this->assertNotNull($fx->survey->fresh()->commercial_released_at, 'Nada foi retirado pelo Comercial.');
    }

    public function test_user_without_the_module_is_forbidden(): void
    {
        $fx = $this->campaign();
        $plain = $this->commercial([AdminPermissionModule::ComercialPropostas]);

        $this->actingAs($plain)->get(route('admin.nr1-opportunities.index'))->assertForbidden();
        $this->actingAs($plain)->get(route('admin.nr1-opportunities.show', $fx->survey->id))->assertForbidden();
    }

    public function test_the_module_exposes_exactly_two_read_only_routes(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(
            fn (LaravelRoute $route) => collect($route->gatherMiddleware())
                ->contains(fn ($m) => is_string($m) && str_contains($m, 'nr1_oportunidades')),
        );

        $this->assertSame(
            ['admin.nr1-opportunities.index', 'admin.nr1-opportunities.show'],
            $routes->map(fn (LaravelRoute $route) => $route->getName())->sort()->values()->all(),
        );
        foreach ($routes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->getName().' precisa ser só leitura.');
        }
    }

    public function test_release_and_withdraw_are_done_by_talents_not_commercial(): void
    {
        $fx = $this->campaign(released: false);
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $other = $this->createSurveyFixture();

        $this->actingAs($owner)
            ->post(route('admin.companies.surveys.commercial-release.store', [$other->company->id, $fx->survey->id]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('admin.companies.surveys.commercial-release.store', [$fx->company->id, $fx->survey->id]))
            ->assertRedirect();
        $this->assertNotNull($fx->survey->fresh()->commercial_released_at);
        $this->assertSame($owner->id, (int) $fx->survey->fresh()->commercial_released_by);

        $this->actingAs($owner)
            ->delete(route('admin.companies.surveys.commercial-release.destroy', [$fx->company->id, $fx->survey->id]))
            ->assertRedirect();
        $this->assertNull($fx->survey->fresh()->commercial_released_at);
    }
}
