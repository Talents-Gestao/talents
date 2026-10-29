<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\AdminUserPermission;
use App\Models\ApproverOrganization;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Shell NR-1 (/nr1), etapa A: flag, menu por perfil, acesso por rota e as rotas antigas intactas nos dois estados.
 */
class Nr1ShellTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private const ALL = ['visao-geral', 'campanha', 'resultados', 'plano-de-acao', 'documentos', 'solucoes', 'oportunidades'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function campaign(bool $published = true): object
    {
        $campaign = $this->v11Campaign(true);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => $published ? now() : null])->save();

        return $campaign;
    }

    private function commercial(): User
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        AdminUserPermission::query()->create([
            'user_workspace_id' => $user->workspaces()->firstOrFail()->id,
            'module' => AdminPermissionModule::Nr1Oportunidades,
            'action' => PermissionAction::View,
        ]);

        return $user;
    }

    /** @return list<string> chaves do menu do shell que o usuário recebe */
    private function menuOf(User $user): array
    {
        $response = $this->actingAs($user)->get(route('nr1.index'))->assertRedirect();
        $page = $this->actingAs($user)->get($response->headers->get('Location'))->assertOk();

        return array_column($page->viewData('page')['props']['shell']['menu'], 'key');
    }

    public function test_the_main_menu_gets_a_single_nr1_item_for_who_has_access(): void
    {
        $c = $this->campaign();

        $this->actingAs(User::factory()->companyAdmin($c->company->id)->create())->get(route('client.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('nr1Shell.url', '/nr1'));
        $this->actingAs(User::factory()->talentsOwner()->create())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('nr1Shell.url', '/nr1'));
        $this->actingAs($this->commercial())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('nr1Shell.url', '/nr1'));

        // Sem acesso a nada do NR-1 (super_admin sem grants): sem item.
        $this->actingAs(User::factory()->superAdmin()->create(['is_owner' => false]))->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('nr1Shell.url', null));
    }

    public function test_the_shell_has_no_flag_visitors_go_to_login_and_the_collaborator_link_is_public(): void
    {
        $c = $this->campaign();

        $this->get('/nr1')->assertRedirect(route('login'));
        $this->get(route('nr1.visao-geral'))->assertRedirect(route('login'));
        $this->get(route('nr1.jornada', $c->survey->public_token))->assertOk();
        $this->assertArrayNotHasKey('shell_enabled', config('nr1'));
        $this->assertArrayNotHasKey('shell_legacy_embed', config('nr1'));
    }

    public function test_the_old_routes_keep_answering_next_to_the_shell(): void
    {
        $c = $this->campaign();
        $admin = User::factory()->companyAdmin($c->company->id)->create();
        $owner = User::factory()->talentsOwner()->create();

        $this->actingAs($admin)->get(route('client.surveys.index'))->assertOk();
        $this->actingAs($admin)->get(route('client.surveys.psychosocial-dashboard', $c->survey))->assertOk();
        $this->actingAs($admin)->get(route('client.surveys.documents.index', $c->survey))->assertOk();
        $this->actingAs($owner)->get(route('admin.companies.surveys.results.show', [$c->company->id, $c->survey->id]))->assertOk();
        $this->actingAs($owner)->get(route('admin.nr1-opportunities.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.service-matches.index'))->assertOk();
    }

    public function test_talents_sees_all_sections_except_my_journey_and_opens_on_the_overview(): void
    {
        $c = $this->campaign();
        $owner = User::factory()->talentsOwner()->create();

        $this->assertSame(self::ALL, $this->menuOf($owner));
        $this->actingAs($owner)->get(route('nr1.index'))->assertRedirect(route('nr1.visao-geral'));

        foreach (self::ALL as $section) {
            $this->actingAs($owner)->get('/nr1/'.$section)->assertOk()->assertInertia(fn (Assert $p) => $p
                ->component('Nr1/Section')->where('context.company.id', $c->company->id)->where('context.survey.id', $c->survey->id));
        }
        $this->actingAs($owner)->get('/nr1/minha-jornada')->assertNotFound();

        // Os números são os reais do Apêndice A (índice 2,60; 40 respostas).
        $this->actingAs($owner)->get(route('nr1.visao-geral'))->assertInertia(fn (Assert $p) => $p
            ->where('section.kpis.0.value', '2,60 / 5')->where('context.survey.respondents', 40));
    }

    public function test_the_company_sees_its_own_sections_only_and_opens_on_the_overview(): void
    {
        $c = $this->campaign();
        $other = $this->campaign();
        $admin = User::factory()->companyAdmin($c->company->id)->create();

        $this->assertSame(['visao-geral', 'campanha', 'resultados', 'plano-de-acao', 'documentos', 'solucoes'], $this->menuOf($admin));
        $this->actingAs($admin)->get(route('nr1.oportunidades'))->assertForbidden();
        $this->actingAs($admin)->get(route('nr1.resultados'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('context.company.id', $c->company->id)->where('context.canPickCompany', false));

        // Escolher a campanha de outra empresa pela URL não funciona.
        $this->actingAs($admin)->get(route('nr1.resultados', ['empresa' => $other->company->id, 'campanha' => $other->survey->id]))
            ->assertInertia(fn (Assert $p) => $p->where('context.company.id', $c->company->id)->where('context.survey.id', $c->survey->id));
    }

    public function test_the_company_sees_nothing_before_the_campaign_is_published(): void
    {
        $c = $this->campaign(published: false);
        $admin = User::factory()->companyAdmin($c->company->id)->create();

        $this->actingAs($admin)->get(route('nr1.visao-geral'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('context.survey', null)->where('context.awaitingPublication', true)->where('section.kpis', [])->where('section.links', []));
    }

    public function test_commercial_sees_opportunities_and_opens_on_it_and_cannot_read_results(): void
    {
        $this->campaign();
        $commercial = $this->commercial();

        $this->assertSame(['oportunidades'], $this->menuOf($commercial));
        $this->actingAs($commercial)->get(route('nr1.index'))->assertRedirect(route('nr1.oportunidades'));
        foreach (['visao-geral', 'campanha', 'resultados', 'plano-de-acao', 'documentos', 'solucoes'] as $section) {
            $this->actingAs($commercial)->get('/nr1/'.$section)->assertForbidden();
        }
    }

    public function test_the_matrix_is_enforced_in_the_backend_not_only_in_the_menu(): void
    {
        $c = $this->campaign();
        $companyUser = User::factory()->companyUser($c->company->id)->create();

        // company_user sem grants: sem as seções que dependem de permissão (a rota responde 403).
        foreach (self::ALL as $section) {
            $this->actingAs($companyUser)->get('/nr1/'.$section)->assertForbidden();
        }
        $this->actingAs($companyUser)->get(route('nr1.index'))->assertForbidden();

        // Visitante: login.
        auth()->logout();
        $this->get('/nr1')->assertRedirect(route('login'));
    }

    public function test_the_external_reviewer_only_gets_documents_and_goes_to_the_review_area(): void
    {
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        $reviewer = User::factory()->externalReviewer($organization->id)->create();

        // Revisão externa desligada (padrão): nada no shell.
        config(['nr1.external_review.enabled' => false]);
        $this->actingAs($reviewer)->get(route('nr1.documentos'))->assertForbidden();

        config(['nr1.external_review.enabled' => true]);
        $this->actingAs($reviewer)->get(route('nr1.documentos'))->assertRedirect(route('external-review.index'));
        foreach (['visao-geral', 'campanha', 'resultados', 'plano-de-acao', 'solucoes', 'oportunidades'] as $section) {
            $this->actingAs($reviewer)->get('/nr1/'.$section)->assertForbidden();
        }
        $this->actingAs($reviewer)->get(route('nr1.index'))->assertRedirect(route('nr1.documentos'));
    }

    public function test_the_collaborator_gets_only_campaign_and_my_journey_through_the_campaign_link(): void
    {
        $c = $this->campaign();
        $token = $c->survey->public_token;

        foreach (['minha-jornada', 'campanha'] as $section) {
            $this->get(route('nr1.jornada', [$token, $section]))->assertOk()->assertInertia(fn (Assert $p) => $p
                ->component('Nr1/Section')->where('shell.profile', 'colaborador')
                ->where('shell.menu.0.key', 'campanha')->where('shell.menu.1.key', 'minha-jornada')->has('shell.menu', 2)
                ->where('context.company', null)->where('section.kpis', []));
        }

        // Só essas duas seções existem para o colaborador e o token precisa ser de uma campanha V11.
        $this->get('/nr1/jornada/'.$token.'/resultados')->assertNotFound();
        $this->get('/nr1/jornada/token-inexistente')->assertNotFound();
    }

    public function test_the_collaborator_link_mirrors_the_old_public_route_for_invalid_closed_and_not_started_campaigns(): void
    {
        $c = $this->campaign();
        $token = $c->survey->public_token;
        $title = $c->survey->title;

        $this->get(route('nr1.jornada', 'nao-e-um-token'))->assertNotFound();
        $this->get(route('nr1.jornada', (string) Str::uuid()))->assertNotFound();

        $cases = [
            'ended' => [['ends_at' => now()->subDay()], 'Esta pesquisa já foi encerrada.'],
            'not_started' => [['starts_at' => now()->addDay()], 'Esta pesquisa ainda não iniciou.'],
            'inactive' => [['status' => 'closed'], 'Esta pesquisa não está ativa no momento.'],
        ];
        foreach ($cases as [$attributes, $message]) {
            $c->survey->forceFill(['status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->forceFill($attributes)->save();

            // Mesma resposta da rota antiga e nada de título/empresa na página nova.
            $old = $this->get(route('survey.public', $token))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('Survey/Closed')->where('message', $message));
            $new = $this->get(route('nr1.jornada', $token))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('Survey/Closed')->where('message', $message));
            $this->assertStringNotContainsString($title, json_encode($new->viewData('page')['props']));
            $this->assertSame($old->viewData('page')['component'], $new->viewData('page')['component']);
        }
    }

    public function test_the_company_cannot_pick_another_companys_campaign_and_talents_handles_valid_and_invalid_company(): void
    {
        $mine = $this->campaign();
        $other = $this->campaign();
        $admin = User::factory()->companyAdmin($mine->company->id)->create();

        $response = $this->actingAs($admin)->get(route('nr1.visao-geral', ['campanha' => $other->survey->id, 'empresa' => $other->company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('context.survey.id', $mine->survey->id)->where('context.company.id', $mine->company->id));
        $this->assertStringNotContainsString($other->survey->public_token, json_encode($response->viewData('page')['props']));

        $owner = User::factory()->talentsOwner()->create();
        $this->actingAs($owner)->get(route('nr1.visao-geral', ['empresa' => $other->company->id]))
            ->assertInertia(fn (Assert $p) => $p->where('context.company.id', $other->company->id)->where('context.survey.id', $other->survey->id));
        // Empresa/campanha inválidas: volta para a primeira empresa com campanha V11, sem erro.
        $this->actingAs($owner)->get(route('nr1.visao-geral', ['empresa' => 999999, 'campanha' => 999999]))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('context.company.id', $mine->company->id)->where('context.survey.id', $mine->survey->id));
    }

    public function test_company_and_collaborator_get_no_response_count_or_participation_below_the_minimum_group(): void
    {
        $c = $this->campaign();
        $admin = User::factory()->companyAdmin($c->company->id)->create();
        $owner = User::factory()->talentsOwner()->create();

        // 40 respostas, mas grupo mínimo da campanha acima disso: abaixo do mínimo.
        $c->survey->forceFill(['min_group_size' => 100])->save();
        $page = $this->actingAs($admin)->get(route('nr1.visao-geral'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('context.survey.respondents', null)->where('context.survey.insufficientSample', true)
            ->where('section.kpis.1.label', 'Participação')->where('section.kpis.1.value', '—'));
        $hint = $page->viewData('page')['props']['section']['kpis'][1]['hint'];
        $this->assertStringContainsString('Amostra insuficiente', $hint);
        $this->assertStringNotContainsString('40', $hint);
        $this->assertStringNotContainsString('respostas ·', $hint);

        // Colaborador nunca recebe contagem.
        $this->get(route('nr1.jornada', $c->survey->public_token))
            ->assertInertia(fn (Assert $p) => $p->where('context.survey.respondents', null));

        // Talents mantém o comportamento antigo (número exato).
        $this->actingAs($owner)->get(route('nr1.visao-geral'))->assertInertia(fn (Assert $p) => $p
            ->where('context.survey.respondents', 40)->where('context.survey.insufficientSample', false));

        // Sem respostas (0 < 5): sem número para a empresa.
        $empty = $this->campaign();
        $adminEmpty = User::factory()->companyAdmin($empty->company->id)->create();
        $empty->survey->responses()->delete();
        $this->actingAs($adminEmpty)->get(route('nr1.visao-geral'))->assertInertia(fn (Assert $p) => $p
            ->where('context.survey.respondents', null)->where('context.survey.insufficientSample', true));
    }

    public function test_the_shell_payload_has_no_individual_answers_other_campaign_tokens_or_respondent_emails(): void
    {
        $c = $this->campaign();
        $other = $this->campaign();
        $admin = User::factory()->companyAdmin($c->company->id)->create();
        $owner = User::factory()->talentsOwner()->create();

        $responses = [
            $this->actingAs($admin)->get(route('nr1.resultados')),
            $this->actingAs($owner)->get(route('nr1.resultados')),
            $this->get(route('nr1.jornada', $c->survey->public_token)),
        ];
        foreach ($responses as $response) {
            $response->assertOk();
            $props = $response->viewData('page')['props'];
            unset($props['auth']); // dados do próprio usuário logado (compartilhados em todas as páginas)
            $json = json_encode($props);

            $this->assertStringNotContainsString($other->survey->public_token, $json);
            foreach (['"answers"', '"answer_value"', '"responses"', '"respondent_', '"email"', '"cpf"'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $json, 'campo sensível no payload do shell: '.$forbidden);
            }
        }
        // A empresa nem recebe o token público da própria campanha.
        $this->assertStringNotContainsString($c->survey->public_token, json_encode($responses[0]->viewData('page')['props']['section']));
    }

    public function test_the_company_sections_follow_the_module_of_the_old_client_routes(): void
    {
        $c = $this->campaign();
        $user = User::factory()->companyUser($c->company->id)->create();

        // Só «pesquisas»: campanha e soluções (como nas rotas antigas); visão geral e resultados leem o painel (relatórios).
        UserPermission::query()->create([
            'user_workspace_id' => $user->workspaces()->firstOrFail()->id,
            'module' => PermissionModule::Pesquisas,
            'action' => PermissionAction::View,
        ]);
        $this->assertSame(['campanha', 'solucoes'], $this->menuOf($user));
    }

    public function test_the_shell_menu_is_a_labelled_nav_with_aria_current_and_visible_focus(): void
    {
        $layout = (string) file_get_contents(resource_path('js/Layouts/Nr1Layout.vue'));
        $css = (string) file_get_contents(resource_path('css/nr1-shell.css'));

        $this->assertStringContainsString('<nav aria-label=', $layout);
        $this->assertStringContainsString(":aria-current=\"shell.current === item.key ? 'page' : undefined\"", $layout);
        // Itens são links reais (teclado nativo: Tab/Enter) e o foco visível está definido.
        $this->assertStringContainsString('<Link', $layout);
        $this->assertStringContainsString('a:focus-visible', $css);
    }
}
