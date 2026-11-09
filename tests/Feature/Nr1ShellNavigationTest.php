<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\Company;
use App\Models\Survey;
use App\Models\User;
use App\Support\Nr1Shell\Nr1Shell;
use App\Support\Nr1Shell\Nr1ShellCompanyPicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Painel NR-1 dentro do layout padrão: item «NR-1» do Admin, sub-navegação por perfil e tipo de campanha, escolha de
 * empresa (/nr1/empresas) e a moldura sem o shell próprio antigo.
 */
class Nr1ShellNavigationTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function campaign(): object
    {
        $c = $this->v11Campaign(true);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();

        return $c;
    }

    private function adminWith(AdminPermissionModule ...$modules): User
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        foreach ($modules as $module) {
            AdminUserPermission::query()->create([
                'user_workspace_id' => $user->workspaces()->firstOrFail()->id,
                'module' => $module,
                'action' => PermissionAction::View,
            ]);
        }

        return $user;
    }

    private function otherCompanyWithSurvey(object $c, string $name = 'Outra Empresa'): Survey
    {
        $company = Company::query()->create(['name' => $name, 'cnpj' => '33.333.333/0001-'.random_int(10, 99), 'is_active' => true, 'complaints_public_token' => (string) Str::uuid()]);

        return Survey::query()->create(['company_id' => $company->id, 'survey_template_id' => $c->template->id, 'title' => 'Pesquisa secreta de '.$name, 'public_token' => (string) Str::uuid(), 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'status' => 'active']);
    }

    /** @return list<string> */
    private function menuKeys(User $user, string $url): array
    {
        return array_column($this->actingAs($user)->get($url)->assertOk()->viewData('page')['props']['shell']['menu'], 'key');
    }

    public function test_the_admin_nr1_item_exists_only_for_the_talents_profile(): void
    {
        $c = $this->campaign();

        $this->actingAs(User::factory()->talentsOwner()->create())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('nr1Shell.adminUrl', '/nr1'));
        $this->actingAs($this->adminWith(AdminPermissionModule::Companies, AdminPermissionModule::Dashboard))->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('nr1Shell.adminUrl', '/nr1'));

        // Só-Comercial entra por «Oportunidades NR-1»; sem permissão nenhuma, sem item e sem rota.
        $commercial = $this->adminWith(AdminPermissionModule::Nr1Oportunidades);
        $this->actingAs($commercial)->get(route('nr1.index'))->assertRedirect(route('nr1.oportunidades'));
        $this->assertNull(Nr1Shell::adminMenuUrl($commercial));

        $none = $this->adminWith();
        $this->assertNull(Nr1Shell::adminMenuUrl($none));
        $this->actingAs($none)->get(route('nr1.index'))->assertForbidden();
        $this->actingAs($none)->get(route('nr1.visao-geral'))->assertForbidden();

        $companyAdmin = User::factory()->companyAdmin($c->company->id)->create();
        $this->actingAs($companyAdmin)->get(route('client.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('nr1Shell.adminUrl', null)->where('nr1Shell.url', '/nr1'));

        $admin = (string) file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));
        $this->assertStringContainsString('label="NR-1"', $admin);
        $this->assertStringContainsString('v-if="nr1AdminUrl"', $admin);
        $this->assertStringContainsString(':active="nr1Active"', $admin);
    }

    public function test_the_sub_navigation_follows_profile_and_campaign_type(): void
    {
        $c = $this->campaign();
        $legacy = $this->otherCompanyWithSurvey($c, 'Empresa Legado');
        $owner = User::factory()->talentsOwner()->create();

        $q = fn (Survey $s) => '/nr1/visao-geral?'.http_build_query(['empresa' => $s->company_id, 'campanha' => $s->id]);
        $this->assertSame(['visao-geral', 'resultados', 'plano-de-acao', 'documentos', 'solucoes', 'oportunidades'], $this->menuKeys($owner, $q($c->survey)));
        $this->assertSame(['visao-geral', 'resultados', 'plano-de-acao', 'documentos', 'parecer', 'solucoes', 'oportunidades'], $this->menuKeys($owner, $q($legacy)));

        $this->actingAs($owner)->get($q($c->survey))->assertInertia(fn (Assert $p) => $p
            ->component('Nr1/Section')->where('shell.profile', 'talents')->where('shell.current', 'visao-geral')->where('shell.pickerUrl', '/nr1/empresas'));

        // «Parecer e publicação»: no legado, logo depois de Documentos, com o rótulo e a rota de antes, e ativo na própria seção.
        $legacyQuery = ['empresa' => $legacy->company_id, 'campanha' => $legacy->id];
        $this->actingAs($owner)->get(route('nr1.parecer', $legacyQuery))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('shell.current', 'parecer')->where('section.key', 'parecer')->where('section.label', 'Parecer e publicação')
            ->where('shell.menu.4.key', 'parecer')->where('shell.menu.4.label', 'Parecer e publicação')
            ->where('shell.menu.4.href', route('nr1.parecer', $legacyQuery, false))
            ->where('shell.menu.3.key', 'documentos')->has('section.legacy.documents'));
        // Na V11 o item some e a URL direta leva a Documentos (o parecer e a publicação vivem lá).
        $v11Query = ['empresa' => $c->company->id, 'campanha' => $c->survey->id];
        $this->actingAs($owner)->get(route('nr1.parecer', $v11Query))->assertRedirect(route('nr1.documentos', $v11Query));

        // Troca de campanha pelo seletor: a URL da seção atual com a nova campanha abre a mesma seção, com o menu do novo tipo.
        foreach (['resultados', 'plano-de-acao', 'documentos', 'visao-geral'] as $section) {
            foreach ([[$legacyQuery, true], [$v11Query, false]] as [$query, $isLegacy]) {
                $this->actingAs($owner)->get(route('nr1.'.$section, $query))->assertOk()->assertInertia(fn (Assert $p) => $p
                    ->where('shell.current', $section)->where('context.survey.id', $query['campanha'])
                    ->where('shell.menu', fn ($menu) => collect($menu)->contains('key', 'parecer') === $isLegacy));
            }
        }

        $companyAdmin = User::factory()->companyAdmin($c->company->id)->create();
        $this->assertSame(['visao-geral', 'resultados', 'plano-de-acao', 'documentos', 'solucoes'], $this->menuKeys($companyAdmin, '/nr1/visao-geral'));
        $this->actingAs($companyAdmin)->get('/nr1/resultados')->assertInertia(fn (Assert $p) => $p->where('shell.profile', 'empresa')->where('shell.current', 'resultados')->where('shell.pickerUrl', null));

        $commercial = $this->adminWith(AdminPermissionModule::Nr1Oportunidades, AdminPermissionModule::Methodology);
        $this->assertSame(['solucoes', 'oportunidades'], $this->menuKeys($commercial, '/nr1/oportunidades'));
        $this->actingAs($commercial)->get('/nr1/visao-geral')->assertForbidden();
    }

    public function test_the_admin_entry_opens_the_remembered_company_or_the_company_picker(): void
    {
        $c = $this->campaign();
        $owner = User::factory()->talentsOwner()->create();

        // Uma só empresa com campanha: direto ao painel.
        $this->actingAs($owner)->get(route('nr1.index'))->assertRedirect(route('nr1.visao-geral'));

        // Várias empresas e nada em foco: escolha de empresa.
        $other = $this->otherCompanyWithSurvey($c);
        $fresh = User::factory()->talentsOwner()->create();
        $this->actingAs($fresh)->get(route('nr1.index'))->assertRedirect(route('nr1.empresas'));

        // Depois de abrir uma empresa, a entrada volta para ela (lembrada na sessão).
        $this->actingAs($fresh)->get('/nr1/visao-geral?empresa='.$other->company_id)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('context.company.id', $other->company_id)->where('context.survey.id', $other->id));
        $this->actingAs($fresh)->get(route('nr1.index'))->assertRedirect(route('nr1.visao-geral'));

        // «Trocar empresa» limpa o foco: a entrada NR-1 não devolve à empresa anterior.
        $this->actingAs($fresh)->get(route('nr1.empresas'))->assertOk();
        $this->actingAs($fresh)->get(route('nr1.index'))->assertRedirect(route('nr1.empresas'));

        // Escolher outra empresa abre a nova (não a que estava na sessão antes do seletor).
        $this->actingAs($fresh)->get('/nr1/visao-geral?empresa='.$c->company->id.'&campanha='.$c->survey->id)
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('context.company.id', $c->company->id)
                ->where('context.survey.id', $c->survey->id));

        // «Abrir painel NR-1» (empresa e campanha na URL) continua abrindo o contexto certo.
        $this->actingAs($fresh)->get(route('nr1.index', ['empresa' => $c->company->id, 'campanha' => $c->survey->id]))
            ->assertRedirect(route('nr1.visao-geral', ['empresa' => $c->company->id, 'campanha' => $c->survey->id]));
    }

    public function test_the_company_picker_cards_carry_only_campaign_identification_and_refuse_other_profiles(): void
    {
        $c = $this->campaign();
        $c->company->forceFill(['brand_primary_color' => '#1E6B5C'])->save();
        $other = $this->otherCompanyWithSurvey($c, 'Alfa Indústria');
        $older = Survey::query()->create(['company_id' => $other->company_id, 'survey_template_id' => $c->template->id, 'title' => 'Campanha antiga', 'public_token' => (string) Str::uuid(), 'starts_at' => now()->subYear(), 'ends_at' => now()->subMonths(10), 'status' => 'closed']);
        Company::query()->create(['name' => 'Sem Campanha', 'cnpj' => '44.444.444/0001-44', 'is_active' => true, 'complaints_public_token' => (string) Str::uuid()]);
        $owner = User::factory()->talentsOwner()->create();

        // Cartões adiados (skeleton na tela); ordem alfabética, a campanha mais recente em destaque e as outras no menu.
        $this->actingAs($owner)->get(route('nr1.empresas'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Nr1/Companies')->missing('companies')->where('recentIds', [])
                ->loadDeferredProps(fn (Assert $r) => $r->has('companies', 2)
                    ->where('companies.0.name', 'Alfa Indústria')->where('companies.0.campaigns', 2)
                    ->where('companies.0.latest.id', $other->id)->where('companies.0.latest.method', 'legacy')
                    ->where('companies.0.latest.status', ['label' => 'Ativa', 'tone' => 'ok'])
                    ->where('companies.0.others.0.id', $older->id)->where('companies.0.others.0.href', '/nr1/visao-geral?empresa='.$other->company_id.'&campanha='.$older->id)
                    ->where('companies.0.flags', ['active' => true, 'review' => false, 'published' => false, 'legacy' => true, 'v11' => false])
                    ->where('companies.1.name', 'Empresa V11')->where('companies.1.brandColor', '#1e6b5c')
                    ->where('companies.1.latest.method', 'v11')->where('companies.1.latest.status', ['label' => 'Publicado', 'tone' => 'ok'])
                    ->where('companies.1.flags.published', true)->where('companies.1.flags.v11', true)
                    ->where('companies.1.href', '/nr1/visao-geral?empresa='.$c->company->id)
                    ->etc()));

        // Privacidade do payload: só identificação; nenhum resultado, índice, resposta, setor, CNPJ ou colaborador.
        $props = null;
        $this->actingAs($owner)->get(route('nr1.empresas'))->assertInertia(function (Assert $p) use (&$props) {
            $p->loadDeferredProps(function (Assert $r) use (&$props) {
                $props = $r->toArray()['props'];
            });
        });
        foreach ($props['companies'] as $row) {
            $this->assertSame(['id', 'name', 'brandColor', 'campaigns', 'href', 'latest', 'others', 'flags'], array_keys($row));
            $this->assertSame(['id', 'title', 'year', 'period', 'method', 'status', 'href'], array_keys($row['latest']));
        }
        $json = (string) json_encode($props['companies'], JSON_UNESCAPED_UNICODE);
        foreach (['respondents', 'responses', 'sectors', 'score', 'index', 'band', 'risk', 'Sem Campanha', $c->company->cnpj, 'email', 'public_token', $c->survey->public_token] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }

        // «Abertas recentemente»: a última empresa aberta no painel vem primeiro.
        $this->actingAs($owner)->get('/nr1/visao-geral?empresa='.$c->company->id)->assertOk();
        $this->actingAs($owner)->get('/nr1/visao-geral?empresa='.$other->company_id)->assertOk();
        $this->actingAs($owner)->get(route('nr1.empresas'))->assertInertia(fn (Assert $p) => $p->where('recentIds', [$other->company_id, $c->company->id]));

        // Empresa, Comercial e Admin sem permissão: 403 (IDOR: o usuário da empresa não lista as outras).
        $this->actingAs(User::factory()->companyAdmin($c->company->id)->create())->get(route('nr1.empresas'))->assertForbidden();
        $this->actingAs(User::factory()->companyUser($c->company->id)->create())->get(route('nr1.empresas'))->assertForbidden();
        $this->actingAs($this->adminWith(AdminPermissionModule::Nr1Oportunidades))->get(route('nr1.empresas'))->assertForbidden();
        $this->actingAs($this->adminWith())->get(route('nr1.empresas'))->assertForbidden();
        auth()->logout();
        $this->get(route('nr1.empresas'))->assertRedirect(route('login'));
    }

    public function test_the_company_picker_runs_a_fixed_number_of_queries(): void
    {
        $c = $this->campaign();
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Nr1ShellCompanyPicker::cards();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $few = $count();
        foreach (['Beta', 'Gama', 'Delta', 'Épsilon'] as $name) {
            $this->otherCompanyWithSurvey($c, $name);
        }
        $this->assertSame($few, $count(), 'Sem N+1: o número de consultas não cresce com as empresas.');
    }

    public function test_the_company_user_cannot_switch_to_another_company_through_the_panel(): void
    {
        $c = $this->campaign();
        $other = $this->otherCompanyWithSurvey($c);
        $other->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $companyAdmin = User::factory()->companyAdmin($c->company->id)->create();

        $this->actingAs($companyAdmin)->get('/nr1/visao-geral?empresa='.$other->company_id.'&campanha='.$other->id)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('context.company.id', $c->company->id)->where('context.survey.id', $c->survey->id)->has('context.companies', 1));
    }

    public function test_the_panel_lives_in_the_standard_layouts_without_the_old_shell_frame(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/Layouts/Nr1Layout.vue'));

        $section = (string) file_get_contents(resource_path('js/Pages/Nr1/Section.vue'));
        $this->assertStringContainsString("import AdminLayout from '@/Layouts/AdminLayout.vue'", $section);
        $this->assertStringContainsString("import ClientLayout from '@/Layouts/ClientLayout.vue'", $section);
        $this->assertStringContainsString('<Nr1Frame', $section);

        $frame = (string) file_get_contents(resource_path('js/Components/Nr1Shell/Nr1Frame.vue'));
        $this->assertStringContainsString('<ToastHost', $frame);
        $this->assertStringContainsString('n1-loading', $frame);
        $this->assertStringContainsString('class="nr1-shell', $frame);

        // Marca, sidebar escura e rodapé próprios do shell antigo não existem mais.
        $sources = $frame.$section
            .file_get_contents(resource_path('js/Components/Nr1Shell/Nr1SubNav.vue'))
            .file_get_contents(resource_path('js/Components/Nr1Shell/ShellHeader.vue'));
        foreach (glob(resource_path('css/nr1-shell*.css')) as $css) {
            $sources .= file_get_contents($css);
        }
        foreach (['n1-sidebar', 'n1-logo', 'Talents · Gestão de Pessoas', 'Gestão de Pessoas', '--n1-sidebar', 'n1-menu-toggle'] as $needle) {
            $this->assertStringNotContainsString($needle, $sources);
        }

        // Dois landmarks distintos, «pular para o conteúdo» e a impressão restrita ao NR-1.
        $layout = (string) file_get_contents(resource_path('js/Components/SidebarLayout.vue'));
        $this->assertStringContainsString('aria-label="Menu principal"', $layout);
        $this->assertStringContainsString('href="#conteudo-principal"', $layout);
        $this->assertStringContainsString('id="conteudo-principal"', $layout);
        $this->assertStringContainsString('sr-only focus:not-sr-only', $layout);
        $pages = (string) file_get_contents(resource_path('css/nr1-shell-pages.css'));
        $this->assertStringContainsString('.app-shell:has(.nr1-shell) [data-shell-sidebar]', $pages);
        $this->assertStringContainsString('.nr1-frame [data-nr1-subnav]', $pages);
    }
}
