<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Jobs\GenerateAiAnalysisJob;
use App\Models\AiSetting;
use App\Models\Department;
use App\Models\PsychosocialAccessLog;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\PsychosocialMeasureService;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Telas antigas do NR-1 removidas: o painel é a única interface, as URLs antigas só redirecionam (com permissão) e as
 * funções que viviam nelas continuam disponíveis no painel.
 */
class Nr1OldScreensRemovedTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private const OLD_PATHS = '#/surveys/d+/action-plan"|/surveys/\d+/results"|/painel-psicossocial"|/surveys/\d+/plano-de-acao"#';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** @return object{company: mixed, survey: Survey, owner: User, admin: User} */
    private function legacy(): object
    {
        $f = $this->createSurveyFixture();
        $department = Department::query()->create(['company_id' => $f->company->id, 'name' => 'Produção']);
        for ($i = 0; $i < 6; $i++) {
            $response = SurveyResponse::query()->create(['survey_id' => $f->survey->id, 'session_token' => (string) Str::uuid(), 'department_id' => $department->id, 'completed_at' => now()]);
            SurveyAnswer::query()->create(['survey_response_id' => $response->id, 'survey_template_question_id' => $f->question->id, 'value' => 3]);
        }
        app(SurveyResultCalculator::class)->recalculate($f->survey->fresh());

        return (object) [
            'company' => $f->company,
            'survey' => $f->survey->fresh(),
            'owner' => User::factory()->talentsOwner()->mfaVerified()->create(),
            'admin' => User::factory()->companyAdmin($f->company->id)->create(),
        ];
    }

    /** @return object{company: mixed, survey: Survey, owner: User, admin: User} */
    private function v11(bool $published = true): object
    {
        $c = $this->v11Campaign(true);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => $published ? now() : null])->save();

        return (object) [
            'company' => $c->company,
            'survey' => $c->survey->fresh(),
            'owner' => User::factory()->talentsOwner()->mfaVerified()->create(),
            'admin' => User::factory()->companyAdmin($c->company->id)->create(),
        ];
    }

    private function enableMia(): void
    {
        AiSetting::query()->create(['provider' => 'openai', 'model' => 'gpt-test', 'is_enabled' => true, 'api_key' => 'chave-de-teste']);
    }

    public function test_the_old_page_routes_are_gone(): void
    {
        foreach (['admin.companies.surveys.action-plan.edit', 'client.surveys.results', 'client.surveys.action-plan', 'client.surveys.measures.index', 'client.surveys.psychosocial-dashboard'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
    }

    public function test_no_admin_or_client_page_links_to_the_old_screens(): void
    {
        $c = $this->legacy();
        $v = $this->v11();

        foreach ([[$c->survey, $c->owner, $c->admin], [$v->survey, $v->owner, $v->admin]] as [$survey, $owner, $admin]) {
            $query = ['empresa' => $survey->company_id, 'campanha' => $survey->id];
            $staff = [route('admin.companies.show', $survey->company_id), route('admin.companies.surveys.measures.edit', [$survey->company_id, $survey->id])];
            foreach (['visao-geral', 'campanha', 'resultados', 'plano-de-acao', 'documentos', 'parecer', 'solucoes'] as $section) {
                $staff[] = route('nr1.'.$section, $query);
            }
            foreach ($staff as $url) {
                $this->assertNoOldLink($this->actingAs($owner)->followingRedirects()->get($url), $url);
            }

            foreach ([route('client.dashboard'), route('nr1.resultados'), route('nr1.plano-de-acao'), route('nr1.documentos')] as $url) {
                $this->assertNoOldLink($this->actingAs($admin)->followingRedirects()->get($url), $url);
            }
        }
    }

    public function test_old_urls_redirect_to_the_equivalent_panel_section(): void
    {
        $c = $this->legacy();
        $v = $this->v11();

        $this->actingAs($c->owner)->get("/admin/companies/{$c->company->id}/surveys/{$c->survey->id}/action-plan")
            ->assertRedirect(route('nr1.parecer', ['empresa' => $c->company->id, 'campanha' => $c->survey->id]));
        $this->actingAs($v->owner)->get("/admin/companies/{$v->company->id}/surveys/{$v->survey->id}/action-plan")
            ->assertRedirect(route('nr1.documentos', ['empresa' => $v->company->id, 'campanha' => $v->survey->id]));

        $this->actingAs($c->admin)->get("/client/surveys/{$c->survey->id}/results")->assertRedirect(route('nr1.resultados', ['campanha' => $c->survey->id]));
        $this->actingAs($c->admin)->get("/client/surveys/{$c->survey->id}/action-plan")->assertRedirect(route('nr1.parecer', ['campanha' => $c->survey->id]));
        $this->actingAs($v->admin)->get("/client/surveys/{$v->survey->id}/painel-psicossocial")->assertRedirect(route('nr1.resultados', ['campanha' => $v->survey->id]));
        $this->actingAs($v->admin)->get("/client/surveys/{$v->survey->id}/plano-de-acao")->assertRedirect(route('nr1.plano-de-acao', ['campanha' => $v->survey->id]));
    }

    public function test_old_urls_respect_permission_company_and_publication(): void
    {
        $c = $this->legacy();
        $unpublished = $this->v11(false);

        $this->get("/client/surveys/{$c->survey->id}/results")->assertRedirect(route('login'));
        // Outra empresa não descobre a campanha; quem é da empresa não entra pela URL do Admin.
        $this->actingAs($unpublished->admin)->get("/client/surveys/{$c->survey->id}/results")->assertNotFound();
        $this->actingAs($c->admin)->get("/admin/companies/{$c->company->id}/surveys/{$c->survey->id}/action-plan")->assertForbidden();
        $this->actingAs($unpublished->admin)->get("/client/surveys/{$unpublished->survey->id}/results")->assertNotFound();
        $this->actingAs($c->owner)->get("/client/surveys/{$c->survey->id}/results")->assertForbidden();

        $noModules = User::factory()->companyUser($c->company->id)->create();
        $this->actingAs($noModules)->get("/client/surveys/{$c->survey->id}/results")->assertForbidden();
        $this->actingAs($noModules)->get("/client/surveys/{$c->survey->id}/action-plan")->assertForbidden();
    }

    public function test_other_old_paths_answer_404(): void
    {
        $c = $this->legacy();

        $this->actingAs($c->admin)->get("/client/surveys/{$c->survey->id}/painel-psicossocial/inexistente")->assertNotFound();
        $this->actingAs($c->owner)->get("/admin/companies/{$c->company->id}/surveys/{$c->survey->id}/action-plan/inexistente")->assertNotFound();
    }

    public function test_mia_and_recalculate_are_in_the_panel_for_both_models(): void
    {
        $this->enableMia();
        $c = $this->legacy();
        $v = $this->v11();
        $query = ['empresa' => $v->company->id, 'campanha' => $v->survey->id];

        $this->actingAs($v->admin)->get(route('nr1.resultados'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.results.ai.postUrl', route('client.surveys.ai-analysis', $v->survey->id))
            ->where('section.results.ai.canRequest', true)
            ->where('section.results.recalculateUrl', null)
            ->has('section.results.exports', 4)
            ->has('section.results.campaigns', 1)
            ->where('section.results.campaigns.0.url', route('nr1.resultados', ['empresa' => $v->company->id, 'campanha' => $v->survey->id]))
            ->has('section.results.legend')
            ->where('section.results.disclaimer', 'Faixa do questionário. Não é classificação de risco ocupacional do PGR.')
            ->has('section.results.items.0.quality_alert'));
        $this->actingAs($v->owner)->get(route('nr1.resultados', $query))->assertInertia(fn (Assert $p) => $p
            ->where('section.results.ai.postUrl', route('admin.companies.surveys.ai-analysis', [$v->company->id, $v->survey->id]))
            ->where('section.results.recalculateUrl', route('admin.companies.surveys.recalculate', [$v->company->id, $v->survey->id]))->where('section.results.exports', []));
        $this->actingAs($c->owner)->get(route('nr1.resultados', ['empresa' => $c->company->id, 'campanha' => $c->survey->id]))->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.results.ai.postUrl', route('admin.companies.surveys.ai-analysis', [$c->company->id, $c->survey->id])));

        Queue::fake();
        $this->actingAs($v->admin)->post(route('client.surveys.ai-analysis', $v->survey->id))->assertRedirect();
        Queue::assertPushed(GenerateAiAnalysisJob::class);
    }

    public function test_mia_stays_hidden_from_the_company_below_the_minimum_group(): void
    {
        $this->enableMia();
        $v = $this->v11();
        SurveyResponse::query()->where('survey_id', $v->survey->id)->orderBy('id')->skip(3)->take(1000)->delete();
        app(SurveyResultCalculator::class)->recalculate($v->survey->fresh());

        $this->actingAs($v->admin)->get(route('nr1.resultados'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('section.results.ai', null));
    }

    public function test_view_only_company_user_cannot_recalculate_or_ask_the_mia(): void
    {
        $this->enableMia();
        $v = $this->v11();
        $viewer = $this->viewer($v->company->id, [PermissionModule::Pesquisas, PermissionModule::Relatorios]);

        $this->actingAs($viewer)->get(route('nr1.resultados'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.results.ai.canRequest', false)->where('section.results.recalculateUrl', null));
        $this->actingAs($viewer)->post(route('client.surveys.recalculate', $v->survey->id))->assertForbidden();
        $this->actingAs($viewer)->post(route('client.surveys.ai-analysis', $v->survey->id))->assertForbidden();
    }

    public function test_company_access_to_panel_results_stays_in_the_access_log(): void
    {
        $v = $this->v11();

        $this->actingAs($v->admin)->get(route('nr1.resultados'))->assertOk();

        $this->assertSame(1, PsychosocialAccessLog::query()->where('survey_id', $v->survey->id)->where('user_id', $v->admin->id)->where('resource', 'dashboard')->count());
    }

    public function test_plan_execution_moved_to_the_panel_with_the_same_rules(): void
    {
        $v = $this->v11();
        $measure = app(PsychosocialMeasureService::class)->createManual($v->survey, [
            'factor_label' => 'Demandas e Sobrecarga',
            'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
            'measure_description' => 'Revisar metas.',
            'responsible_text' => 'RH',
            'suggested_due_date' => now()->addDays(30)->toDateString(),
        ], $v->owner);

        $this->actingAs($v->admin)->post(route('client.surveys.measures.confirm', [$v->survey->id, $measure->id]), ['responsible_text' => 'RH', 'due_date' => now()->addDays(20)->toDateString()])->assertRedirect();
        $this->actingAs($v->admin)->post(route('client.surveys.measures.start', [$v->survey->id, $measure->id]))->assertRedirect();

        $this->actingAs($v->admin)->get(route('nr1.plano-de-acao'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.plan.measures.0.urls.evidence', route('client.surveys.measures.evidences.store', [$v->survey->id, $measure->id]))
            ->where('section.plan.measures.0.urls.adjust', route('client.surveys.measures.adjust', [$v->survey->id, $measure->id]))
            ->where('section.plan.measures.0.urls.replan', route('client.surveys.measures.replan', [$v->survey->id, $measure->id]))
            ->where('section.plan.measures.0.urls.effectiveness', null)
            ->has('section.plan.measures.0.history')->has('section.plan.effectivenessResults'));

        $viewer = $this->viewer($v->company->id, [PermissionModule::PlanosAcao]);
        $this->actingAs($viewer)->get(route('nr1.plano-de-acao'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.plan.measures.0.urls.evidence', null)->where('section.plan.measures.0.urls.adjust', null));
    }

    public function test_technical_opinion_of_v11_lives_in_documents(): void
    {
        $v = $this->v11();
        $query = ['empresa' => $v->company->id, 'campanha' => $v->survey->id];

        $this->actingAs($v->owner)->get(route('nr1.documentos', $query))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.documents.opinion.saveUrl', route('admin.companies.surveys.action-plan.update', [$v->company->id, $v->survey->id])));

        $this->actingAs($v->owner)->put(route('admin.companies.surveys.action-plan.update', [$v->company->id, $v->survey->id]), ['technical_opinion' => '<p>Parecer da consultoria.</p>'])
            ->assertRedirect(route('nr1.documentos', $query));

        $this->actingAs($v->owner)->get(route('nr1.documentos', $query))->assertInertia(fn (Assert $p) => $p
            ->where('section.documents.opinion.html', '<p>Parecer da consultoria.</p>'));
    }

    /** @param  list<PermissionModule>  $modules */
    private function viewer(int $companyId, array $modules): User
    {
        $user = User::factory()->companyUser($companyId)->create();
        foreach ($modules as $module) {
            UserPermission::query()->create(['user_workspace_id' => $user->workspaces()->firstOrFail()->id, 'module' => $module->value, 'action' => PermissionAction::View->value]);
        }

        return $user;
    }

    private function assertNoOldLink(mixed $response, string $url): void
    {
        $response->assertOk();
        $this->assertDoesNotMatchRegularExpression(self::OLD_PATHS, json_encode($response->viewData('page')['props'], JSON_UNESCAPED_SLASHES), "Link antigo em {$url}");
    }
}
