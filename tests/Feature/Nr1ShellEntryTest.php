<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Entrada do shell NR-1: pelo bloco «Pesquisas e parecer» da página da empresa (Talents) e por «Pesquisas NR1» (Empresa).
 * Sem item «NR-1» no menu principal e sem seletor de empresa no cabeçalho do shell.
 */
class Nr1ShellEntryTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_company_page_opens_the_shell_for_every_survey_and_only_with_the_flag_on(): void
    {
        $c = $this->v11Campaign(true);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $legacy = Survey::query()->create(['company_id' => $c->company->id, 'survey_template_id' => $c->template->id, 'title' => 'Pesquisa antiga', 'public_token' => (string) Str::uuid(), 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'status' => 'active']);
        $owner = User::factory()->talentsOwner()->create();

        config(['nr1.shell_enabled' => false]);
        $this->actingAs($owner)->get(route('admin.companies.show', $c->company))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('nr1ShellUrls', []));

        config(['nr1.shell_enabled' => true]);
        $this->actingAs($owner)->get(route('admin.companies.show', $c->company))->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('nr1ShellUrls.'.$c->survey->id, '/nr1/visao-geral?empresa='.$c->company->id.'&campanha='.$c->survey->id)
                ->where('nr1ShellUrls.'.$legacy->id, '/nr1/visao-geral?empresa='.$c->company->id.'&campanha='.$legacy->id));

        // A entrada leva ao contexto certo, sem seletor.
        $this->actingAs($owner)->get('/nr1/visao-geral?empresa='.$c->company->id.'&campanha='.$c->survey->id)
            ->assertInertia(fn (Assert $p) => $p->where('context.company.id', $c->company->id)->where('context.survey.id', $c->survey->id)
                ->where('shell.backLabel', '← Voltar à empresa')->where('shell.backUrl', route('admin.companies.show', $c->company)));
    }

    public function test_the_company_profile_back_button_and_the_commercial_one_use_the_right_label(): void
    {
        config(['nr1.shell_enabled' => true]);
        $c = $this->v11Campaign(true);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();

        $this->actingAs(User::factory()->companyAdmin($c->company->id)->create())->get(route('nr1.visao-geral'))
            ->assertInertia(fn (Assert $p) => $p->where('shell.backLabel', '← Voltar à empresa')->where('shell.backUrl', route('client.dashboard')));
    }

    public function test_menus_have_no_nr1_item_and_the_company_menu_keeps_pesquisas_nr1_pointing_to_the_shell(): void
    {
        $admin = (string) file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));
        $client = (string) file_get_contents(resource_path('js/Layouts/ClientLayout.vue'));
        $layout = (string) file_get_contents(resource_path('js/Layouts/Nr1Layout.vue'));

        $this->assertStringNotContainsString('label="NR-1"', $admin);
        $this->assertStringNotContainsString('label="NR-1"', $client);
        $this->assertStringContainsString('label="Pesquisas NR1"', $client);
        $this->assertStringContainsString("nr1ShellUrl ?? route('client.surveys.index')", $client);
        $this->assertStringNotContainsString('Nr1ContextBar', $layout);
    }

    public function test_the_commercial_menu_item_exists_only_with_the_flag_on_and_the_permission(): void
    {
        $owner = User::factory()->talentsOwner()->create();
        $commercial = User::factory()->superAdmin()->create(['is_owner' => false]);
        AdminUserPermission::query()->create([
            'user_workspace_id' => $commercial->workspaces()->firstOrFail()->id,
            'module' => AdminPermissionModule::Nr1Oportunidades,
            'action' => PermissionAction::View,
        ]);
        $without = User::factory()->superAdmin()->create(['is_owner' => false]);
        $company = $this->v11Campaign(false)->company;
        $companyAdmin = User::factory()->companyAdmin($company->id)->create();

        // Flag desligada: o item não existe para ninguém e a rota responde 404.
        config(['nr1.shell_enabled' => false]);
        foreach ([$owner, $commercial] as $user) {
            $this->actingAs($user)->get(route('admin.dashboard'))->assertInertia(fn (Assert $p) => $p->where('nr1Shell.opportunitiesUrl', null));
        }
        $this->actingAs($commercial)->get('/nr1/oportunidades')->assertNotFound();

        // Flag ligada: aparece para quem tem nr1_oportunidades:view (e para o proprietário), abrindo /nr1/oportunidades.
        config(['nr1.shell_enabled' => true]);
        foreach ([$owner, $commercial] as $user) {
            $this->actingAs($user)->get(route('admin.dashboard'))->assertInertia(fn (Assert $p) => $p->where('nr1Shell.opportunitiesUrl', '/nr1/oportunidades'));
        }
        $this->actingAs($commercial)->get('/nr1/oportunidades')->assertOk();

        // Sem a permissão (ou sem ser do Talents): oculto e a rota é proibida.
        $this->actingAs($without)->get(route('admin.dashboard'))->assertInertia(fn (Assert $p) => $p->where('nr1Shell.opportunitiesUrl', null));
        $this->actingAs($without)->get('/nr1/oportunidades')->assertForbidden();
        $this->actingAs($companyAdmin)->get(route('client.dashboard'))->assertInertia(fn (Assert $p) => $p->where('nr1Shell.opportunitiesUrl', null));
        $this->actingAs($companyAdmin)->get('/nr1/oportunidades')->assertForbidden();

        // O item vive no grupo Comercial do menu.
        $admin = (string) file_get_contents(resource_path('js/Layouts/AdminLayout.vue'));
        $this->assertStringContainsString('label="Oportunidades NR-1"', $admin);
        $this->assertStringContainsString('v-if="nr1OpportunitiesUrl"', $admin);
    }
}
