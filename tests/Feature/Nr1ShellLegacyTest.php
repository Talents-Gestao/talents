<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\Company;
use App\Models\Department;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

/**
 * Etapa L: campanhas do modelo anterior (sem fluxo de revisão) dentro do shell NR-1. O shell reaproveita as props, o
 * acesso e o fluxo Diagnóstico/Parecer/Publicar das telas antigas, que seguem intactas por URL direta.
 */
class Nr1ShellLegacyTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['nr1.shell_enabled' => true]);
    }

    /** @return object{company: Company, survey: Survey, owner: User, admin: User} */
    private function legacy(array $surveyOverrides = []): object
    {
        $f = $this->createSurveyFixture($surveyOverrides);
        $department = Department::query()->create(['company_id' => $f->company->id, 'name' => 'Produção']);
        for ($i = 0; $i < 6; $i++) {
            $response = SurveyResponse::query()->create(['survey_id' => $f->survey->id, 'session_token' => (string) Str::uuid(), 'department_id' => $department->id, 'completed_at' => now()]);
            SurveyAnswer::query()->create(['survey_response_id' => $response->id, 'survey_template_question_id' => $f->question->id, 'value' => 3 + ($i % 3)]);
        }
        app(SurveyResultCalculator::class)->recalculate($f->survey->fresh());

        return (object) [
            'company' => $f->company,
            'survey' => $f->survey->fresh(),
            'owner' => User::factory()->talentsOwner()->create(),
            'admin' => User::factory()->companyAdmin($f->company->id)->create(),
        ];
    }

    public function test_talents_sees_the_legacy_laudo_inside_the_shell_with_the_same_props_as_the_old_pages(): void
    {
        $c = $this->legacy();
        $q = ['empresa' => $c->company->id, 'campanha' => $c->survey->id];

        $this->actingAs($c->owner)->get(route('nr1.visao-geral', $q))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('context.survey.id', $c->survey->id)
            ->where('section.legacy.notice', 'Pesquisa no modelo anterior')
            ->where('section.legacy.overview.minPerSector', 1)
            ->where('section.legacy.overview.publicationLabel', 'Rascunho — ainda não enviado')
            ->where('section.legacy.overview.lastPublication', null)
            ->where('section.legacy.overview.respondents', 6)
            ->has('section.legacy.overview.dimensions', 1)
            ->missing('section.overview')->where('section.kpis', []));

        $this->actingAs($c->owner)->get(route('nr1.resultados', $q))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('section.legacy.results.panel.overall')->has('section.legacy.results.panel.deptSectionsByDepartment')
            ->has('section.legacy.results.panel.questionDistributions')->where('section.legacy.results.client', null)
            ->where('section.legacy.results.nextUrl', route('nr1.documentos'))->missing('section.results'));

        $page = $this->actingAs($c->owner)->get(route('nr1.documentos', $q))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('section.legacy.documents.laudo.technicalOpinionGeneratePostUrl')->has('section.legacy.documents.laudo.publication')
            ->where('section.legacy.documents.laudo.company.id', $c->company->id));

        // As props do laudo no shell são as MESMAS que a página antiga recebe (mesmo controller).
        $old = $this->actingAs($c->owner)->get(route('admin.companies.surveys.action-plan.edit', [$c->company->id, $c->survey->id]))->assertOk()
            ->viewData('page')['props'];
        $laudo = $page->viewData('page')['props']['section']['legacy']['documents']['laudo'];
        foreach (['survey', 'overall', 'plan', 'publication', 'riskScenarioLabel', 'technical_opinion'] as $key) {
            $this->assertEquals($old[$key], $laudo[$key], "prop {$key}");
        }

        $this->actingAs($c->owner)->get(route('nr1.campanha', $q))->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.campaign.completed', 6)->has('section.legacy.campaign.period')->missing('section.campaign'));

        // Seções que só existem na V11: aviso, sem dado inventado e sem erro.
        foreach (['plano-de-acao', 'solucoes', 'oportunidades'] as $section) {
            $this->actingAs($c->owner)->get('/nr1/'.$section.'?'.http_build_query($q))->assertOk()->assertInertia(fn (Assert $p) => $p
                ->where('section.legacy.notice', 'Pesquisa no modelo anterior')->missing('section.plan')->missing('section.results'));
        }
    }

    public function test_the_company_sees_the_legacy_results_and_the_plan_only_as_the_old_pages_do(): void
    {
        $c = $this->legacy();

        $this->actingAs($c->admin)->get(route('nr1.resultados'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('context.company.id', $c->company->id)
            ->has('section.legacy.results.panel.overall')->has('section.legacy.results.client')
            ->where('section.legacy.results.recalculateUrl', route('client.surveys.recalculate', $c->survey->id))
            ->where('section.legacy.minPerSector', 1));

        // Parecer e plano: nada antes de publicar (a tela antiga mostra o bloqueio) e só os relatórios automáticos.
        $this->actingAs($c->admin)->get(route('nr1.documentos'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.documents.actionPlan.actionPlanLocked', true)->where('section.legacy.documents.actionPlan.plan', null)
            ->has('section.legacy.documents.reports', 5)->missing('section.legacy.documents.laudo'));

        ActionPlan::query()->create(['company_id' => $c->company->id, 'survey_id' => $c->survey->id, 'technical_opinion' => '<p>Parecer publicado</p>', 'admin_published_at' => now()]);
        $this->actingAs($c->admin)->get(route('nr1.documentos'))->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.documents.actionPlan.actionPlanLocked', false)->where('section.legacy.documents.actionPlan.plan.technical_opinion', '<p>Parecer publicado</p>')
            ->has('section.legacy.documents.reports', 6));

        // Nada do Talents chega à empresa.
        $json = (string) json_encode($this->actingAs($c->admin)->get(route('nr1.documentos'))->viewData('page')['props']['section']);
        $this->assertStringNotContainsString('technicalOpinionGeneratePostUrl', $json);
        $this->assertStringNotContainsString('aiGeneratePostUrl', $json);
    }

    public function test_the_shell_does_not_bypass_the_legacy_access_rules(): void
    {
        $c = $this->legacy(['requires_publication' => true]);
        $companyUser = User::factory()->companyUser($c->company->id)->create();

        // Exige publicação e ainda não publicada: a empresa não vê a campanha nem nada dela.
        $this->actingAs($c->admin)->get(route('nr1.resultados'))->assertInertia(fn (Assert $p) => $p
            ->where('context.survey', null)->where('context.awaitingPublication', true)->missing('section.legacy'));
        $this->actingAs($c->admin)->get(route('nr1.documentos', ['campanha' => $c->survey->id]))->assertInertia(fn (Assert $p) => $p->where('context.survey', null)->missing('section.legacy'));

        // company_user sem permissão nos módulos do legado: 403 (a matriz vale no back-end).
        $c->survey->forceFill(['published_at' => now()])->save();
        $this->actingAs($companyUser)->get(route('nr1.resultados'))->assertForbidden();
        $this->actingAs($companyUser)->get(route('nr1.documentos'))->assertForbidden();
    }

    public function test_old_screens_and_routes_keep_working_with_the_flag_on_and_off(): void
    {
        $c = $this->legacy();

        foreach ([false, true] as $on) {
            config(['nr1.shell_enabled' => $on]);
            $this->actingAs($c->owner)->get(route('admin.companies.surveys.action-plan.edit', [$c->company->id, $c->survey->id]))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('Admin/ActionPlan/Edit')->has('technicalOpinionGeneratePostUrl')->where('company.id', $c->company->id));
            $this->actingAs($c->admin)->get(route('client.surveys.results', $c->survey->id))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('Client/Surveys/Results')->has('overall'));
            $this->actingAs($c->admin)->get(route('client.surveys.action-plan', $c->survey->id))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('Client/Surveys/ActionPlan')->where('actionPlanLocked', true));
        }

        // Flag desligada: o shell não existe, mesmo para a campanha antiga.
        config(['nr1.shell_enabled' => false]);
        $this->actingAs($c->owner)->get(route('nr1.documentos', ['campanha' => $c->survey->id]))->assertNotFound();
        $this->actingAs($c->admin)->get(route('nr1.resultados'))->assertNotFound();
    }

    public function test_the_legacy_pages_were_split_into_layout_free_bodies_without_changing_their_pages(): void
    {
        $edit = (string) file_get_contents(resource_path('js/Pages/Admin/ActionPlan/Edit.vue'));
        $results = (string) file_get_contents(resource_path('js/Pages/Client/Surveys/Results.vue'));
        $plan = (string) file_get_contents(resource_path('js/Pages/Client/Surveys/ActionPlan.vue'));
        $laudo = (string) file_get_contents(resource_path('js/Components/Nr1Legacy/LaudoBody.vue'));

        // A página antiga segue com o layout antigo e usa o corpo extraído.
        $this->assertStringContainsString('<AdminLayout>', $edit);
        $this->assertStringContainsString('<LaudoBody ref="body"', $edit);
        $this->assertStringContainsString('<ClientLayout>', $results);
        $this->assertStringContainsString('<ClientResultsBody', $results);
        $this->assertStringContainsString('<ClientLayout>', $plan);
        $this->assertStringContainsString('<ClientActionPlanBody', $plan);

        // O corpo não conhece layout e só muda de comportamento com a prop do shell (padrão: desligada).
        $this->assertStringNotContainsString('AdminLayout', $laudo);
        $this->assertStringContainsString('shellMode: { type: Boolean, default: false }', $laudo);
        $this->assertStringContainsString("const activeStep = ref(props.shellMode ? 'opinion' : 'results');", $laudo);
        foreach (['releaseExecutiveBrief', 'releasePgrSubsidy', 'openExportPdfModal'] as $exposed) {
            $this->assertStringContainsString($exposed.',', $laudo);
        }
    }
}
