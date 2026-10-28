<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\Department;
use App\Models\Nr1Opportunity;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyGroupResult;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\Nr1\Nr1OpportunityService;
use App\Services\Nr1\Nr1TrainingService;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\PsychosocialMeasureService;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Bancada V11: lacunas da matriz T01 a T25 do Caderno (ver docs/nr1-v11/NOTAS.md, «Bancada de testes»).
 * T01 isolamento entre empresas em TODAS as rotas com {survey}; T06 orientação também para Líderes; T13 filtro por
 * dimensão e demais saídas sem reidentificar setor pequeno; T15 dados dos 39 cartões; T22 recusa comercial não altera resultado.
 */
class Nr1V11BenchTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Campanha V11 publicada, com público Time e Líderes, uma medida no plano e um treinamento contratado.
     *
     * @return object{company: mixed, survey: Survey, talents: User, admin: User, time: SurveyAudienceLink, leaders: SurveyAudienceLink, measure: PsychosocialMeasure}
     */
    private function campaign(bool $withResponses = true): object
    {
        $campaign = $this->v11Campaign($withResponses);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();

        $links = [];
        foreach (SurveyAudienceLink::AUDIENCES as $audience) {
            $links[$audience] = SurveyAudienceLink::query()->create(['survey_id' => $campaign->survey->id, 'audience' => $audience, 'token' => Str::random(40), 'enabled' => true]);
        }

        $talents = User::factory()->talentsOwner()->create();
        $measure = app(PsychosocialMeasureService::class)->createManual($campaign->survey, [
            'factor_label' => 'Demandas e Sobrecarga',
            'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
            'measure_description' => 'Revisar a distribuição das tarefas.',
            'responsible_text' => 'Gestão de operações',
            'suggested_due_date' => now()->addDays(30)->toDateString(),
        ], $talents);
        app(Nr1TrainingService::class)->contract($campaign->survey, $talents, 'online', 'time', ['scope' => 'Trilha online', 'link_url' => 'https://t.example.com', 'released' => true]);

        return (object) [
            'company' => $campaign->company,
            'survey' => $campaign->survey->fresh(),
            'talents' => $talents,
            'admin' => User::factory()->companyAdmin($campaign->company->id)->create(),
            'time' => $links['time'],
            'leaders' => $links['lideres'],
            'measure' => $measure,
        ];
    }

    public function test_t01_a_company_cannot_reach_another_companys_survey_through_any_client_route(): void
    {
        $a = $this->campaign();
        $b = $this->v11Campaign(false);
        $intruder = User::factory()->companyAdmin($b->company->id)->create();

        $values = [
            'survey' => $a->survey->id,
            'link' => $a->time->id,
            'measure' => $a->measure->id,
            'modality' => 'online',
            'audience' => 'time',
            'type' => 'executive_brief',
        ];

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route) => str_starts_with($route->getName() ?? '', 'client.') && in_array('survey', $route->parameterNames(), true))
            ->values();
        $this->assertGreaterThan(20, $routes->count(), 'A lista de rotas com {survey} parece vazia.');

        // Corpo válido por rota de escrita das medidas: com dados corretos a recusa tem de ser 403/404, sem efeito algum.
        $validBodies = [
            'client.surveys.measures.confirm' => ['due_date' => '2026-12-01'],
            'client.surveys.measures.adjust' => ['due_date' => '2026-12-01', 'reason' => 'Ajuste indevido'],
            'client.surveys.measures.replan' => ['due_date' => '2026-12-01', 'reason' => 'Replanejamento indevido'],
            'client.surveys.measures.evidences.store' => ['description' => 'Evidência indevida'],
            'client.surveys.measures.effectiveness' => ['note' => 'Eficácia indevida', 'result' => 'effective'],
        ];
        $measureBefore = json_encode($a->measure->fresh());

        $leaks = [];
        $validationOnly = [];
        foreach ($routes as $route) {
            $parameters = [];
            foreach ($route->parameterNames() as $name) {
                $parameters[$name] = $values[$name] ?? 1;
            }
            $method = collect($route->methods())->first(fn (string $m) => $m !== 'HEAD');
            $url = route($route->getName(), $parameters);

            // Sem corpo: 403/404, ou (rotas que validam antes de autorizar) um redirecionamento de ERRO DE VALIDAÇÃO, nunca de sucesso.
            $response = $this->actingAs($intruder)->call($method, $url);
            $status = $response->getStatusCode();
            if ($status === 302 && session()->has('errors') && ! session()->has('success')) {
                $validationOnly[] = $route->getName();
            } elseif (! in_array($status, [403, 404], true)) {
                $leaks[] = "{$method} {$route->getName()} => {$status}";
            }

            if (isset($validBodies[$route->getName()])) {
                $strict = $this->actingAs($intruder)->call($method, $url, $validBodies[$route->getName()])->getStatusCode();
                if (! in_array($strict, [403, 404], true)) {
                    $leaks[] = "{$method} {$route->getName()} (corpo válido) => {$strict}";
                }
            }
        }

        $this->assertSame([], $leaks, 'Rota da empresa devolveu algo diferente de 403/404 para outra empresa.');
        $this->assertSame($measureBefore, json_encode($a->measure->fresh()), 'A medida da outra empresa foi alterada.');
        $this->assertSame(0, \App\Models\PsychosocialMeasureEvidence::query()->count());
        // Teste estrutural: nenhuma rota da empresa com {survey} valida o corpo antes de autorizar. Para outra empresa,
        // mesmo sem corpo, a resposta é sempre 403/404 (nunca 302 com erro de validação).
        $this->assertSame([], $validationOnly, 'Rota que valida antes de autorizar: '.implode(', ', $validationOnly));

        // Controle: o dono da campanha acessa a mesma tela (o teste não é vazio).
        $this->actingAs($a->admin)->get(route('client.surveys.show', $a->survey))->assertOk();
        $this->actingAs($a->admin)->get(route('client.surveys.psychosocial-dashboard', $a->survey))->assertOk();
    }

    public function test_t01_public_links_of_one_campaign_never_serve_files_of_another(): void
    {
        $a = $this->campaign();
        $b = $this->campaign();
        $training = app(Nr1TrainingService::class)->contract($b->survey, $b->talents, 'online', 'lideres', ['scope' => 'Material da campanha B', 'link_url' => 'https://b.example.com', 'released' => true]);

        // Treinamento da campanha B pelo link da campanha A: 404.
        $this->get(route('survey.public.training-material', [$a->time->token, $training->id]))->assertNotFound();
    }

    public function test_t06_orientation_is_shown_to_the_leaders_link_too_and_only_their_training(): void
    {
        $c = $this->campaign();
        app(Nr1TrainingService::class)->contract($c->survey, $c->talents, 'online', 'lideres', ['scope' => 'Oficina de líderes', 'link_url' => 'https://l.example.com', 'released' => true]);

        foreach (['time' => $c->time, 'lideres' => $c->leaders] as $audience => $link) {
            $this->get(route('survey.public', $link->token))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('journey.orientation.availability', 'Disponível para todos')
                ->where('journey.orientation.title', 'Orientação sobre a pesquisa')
                ->has('journey.orientation.paragraphs', 4)
                ->where('journey.audience_label', $audience === 'time' ? 'Time' : 'Líderes')
                ->has('journey.trainings', 1)
                ->where('journey.trainings.0.scope', $audience === 'time' ? 'Trilha online' : 'Oficina de líderes'));
        }
    }

    public function test_t13_dimension_filter_print_and_item_export_never_reidentify_a_small_sector(): void
    {
        $fx = $this->createSurveyFixture(['method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1)]);
        foreach (['Grande' => 12, 'Médio' => 6, 'Minúsculo' => 1, 'Pequeno' => 3] as $name => $size) {
            $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => $name]);
            for ($i = 0; $i < $size; $i++) {
                SurveyResponse::query()->create([
                    'survey_id' => $fx->survey->id,
                    'session_token' => (string) Str::uuid(),
                    'department_id' => $department->id,
                    'completed_at' => now()->startOfMonth(),
                ])->answers()->create(['survey_template_question_id' => $fx->question->id, 'value' => 4]);
            }
        }
        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());
        $fx->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $user = User::factory()->companyAdmin($fx->company->id)->create();
        $survey = $fx->survey->fresh();

        $dashboard = app(PsychosocialDashboardBuilder::class)->build($survey);

        // O filtro por dimensão trabalha só sobre este payload: nenhum setor com menos de 5 respostas pode estar nele.
        foreach ($dashboard['sectors'] as $sector) {
            $this->assertGreaterThanOrEqual(5, $sector['n'], "Setor {$sector['department_name']} abaixo do piso no payload.");
        }
        $dimensionIds = collect($dashboard['dimensions'])->pluck('section_id');
        $this->assertNotEmpty($dimensionIds);
        foreach ($dimensionIds as $sectionId) {
            foreach ($dashboard['sectors'] as $sector) {
                foreach ($sector['dimensions'] as $dimension) {
                    if ((int) $dimension['section_id'] === (int) $sectionId) {
                        $this->assertGreaterThanOrEqual(5, $dimension['n'] ?? $sector['n']);
                    }
                }
            }
        }

        $outputs = [
            'página' => json_encode($this->actingAs($user)->get(route('client.surveys.psychosocial-dashboard', $survey))->assertOk()->viewData('page')['props'], JSON_UNESCAPED_UNICODE),
            'impressão' => $this->actingAs($user)->get(route('client.surveys.psychosocial-dashboard.print', $survey))->assertOk()->getContent(),
            'CSV' => $this->actingAs($user)->get(route('client.surveys.psychosocial-dashboard.export.csv', $survey))->assertOk()->streamedContent(),
            'JSON' => $this->actingAs($user)->get(route('client.surveys.psychosocial-dashboard.export.json', $survey))->assertOk()->getContent(),
            'perguntas CSV' => $this->actingAs($user)->get(route('client.surveys.psychosocial-dashboard.export.items', $survey))->assertOk()->streamedContent(),
        ];

        foreach ($outputs as $label => $content) {
            foreach (['Minúsculo', 'Pequeno', 'Médio'] as $hidden) {
                $this->assertStringNotContainsString($hidden, $content, "{$hidden} apareceu em {$label}.");
            }
        }
    }

    public function test_t15_the_39_item_cards_carry_everything_search_filter_and_sort_need(): void
    {
        $c = $this->campaign();
        $items = app(PsychosocialDashboardBuilder::class)->build($c->survey)['items'];

        $this->assertCount(39, $items);
        $this->assertSame(range(1, 39), array_column($items, 'position'));
        $this->assertCount(39, array_unique(array_column($items, 'id')));
        $this->assertCount(7, array_unique(array_column($items, 'section_id')));

        foreach ($items as $item) {
            foreach (['id', 'position', 'section_id', 'section_title', 'body', 'n', 'distribution', 'favorable_pct', 'unfavorable_pct'] as $key) {
                $this->assertArrayHasKey($key, $item);
            }
            $this->assertNotSame('', trim((string) $item['body']), 'Enunciado vazio não é pesquisável.');
            $this->assertSame(40, $item['n']);
            $this->assertIsNumeric($item['favorable_pct']);
            $this->assertIsNumeric($item['unfavorable_pct']);
            $this->assertEqualsWithDelta(100.0, array_sum($item['distribution']), 0.2);
        }

        // Cartões só da campanha inteira: nenhum recorte por setor.
        $this->assertSame([], array_values(array_filter(array_keys($items[0]), fn (string $key) => str_contains($key, 'sector') || str_contains($key, 'department'))));

        // Ordenar por favorável (o que a tela faz) devolve ordem decrescente estável a partir dos mesmos dados.
        $sorted = collect($items)->sortBy([['favorable_pct', 'desc'], ['position', 'asc']])->values();
        $this->assertSame(7, $sorted->first()['position'], 'Q7 (92,5%) é a mais favorável do dataset V11.');

        // A exportação tem as mesmas 39 linhas, na ordem das perguntas.
        $csv = $this->actingAs($c->admin)->get(route('client.surveys.psychosocial-dashboard.export.items', $c->survey))->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(40, $lines, 'Cabeçalho + 39 perguntas.');
    }

    public function test_t22_commercial_refusal_changes_nothing_in_results_plan_documents_or_the_campaign(): void
    {
        $c = $this->campaign();
        $opportunity = app(Nr1OpportunityService::class)->requestTraining($c->survey, $c->admin, 'online', 'lideres');

        $commercial = User::factory()->superAdmin()->create(['is_owner' => false]);
        foreach ([PermissionAction::View, PermissionAction::Edit] as $action) {
            AdminUserPermission::query()->create([
                'user_workspace_id' => $commercial->workspaces()->firstOrFail()->id,
                'module' => AdminPermissionModule::Nr1Oportunidades,
                'action' => $action,
            ]);
        }

        $snapshot = fn (): string => json_encode([
            'dashboard' => app(PsychosocialDashboardBuilder::class)->build($c->survey->fresh()),
            'results' => SurveyResult::query()->where('survey_id', $c->survey->id)->orderBy('id')->get(),
            'groups' => SurveyGroupResult::query()->where('survey_id', $c->survey->id)->orderBy('id')->get(),
            'measures' => PsychosocialMeasure::query()->where('survey_id', $c->survey->id)->orderBy('id')->get(),
            'survey' => $c->survey->fresh()->only(['status', 'published_at', 'requires_publication', 'requires_passeg_review', 'min_group_size']),
            'training' => \App\Models\Nr1Training::query()->where('survey_id', $c->survey->id)->orderBy('id')->get(),
            'rounds' => \App\Models\PsychosocialReviewRound::query()->where('survey_id', $c->survey->id)->count(),
        ], JSON_UNESCAPED_UNICODE);
        $before = $snapshot();

        $this->actingAs($commercial)
            ->post(route('admin.nr1-opportunities.status', $opportunity->id), ['status' => 'lost', 'note' => 'Cliente optou por fornecedor interno'])
            ->assertSessionHas('success');

        $this->assertSame('lost', $opportunity->fresh()->status);
        $this->assertSame($before, $snapshot(), 'A recusa comercial alterou resultado, plano, treinamento, documento ou campanha.');
        $this->assertSame(1, Nr1Opportunity::query()->count());
    }
}
