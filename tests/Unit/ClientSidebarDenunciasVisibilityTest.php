<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mapa da sidebar Cliente (ClientLayout) — foco Denúncias.
 *
 * Item “Denúncias” aparece se can('denuncias', 'view') — ver usePermissions +
 * User::permissionMatrixForFrontend() + Company::hasComplaintsEnabled().
 *
 * Cascata:
 * 1) Módulo ativo na empresa: hasComplaintsEnabled()
 *    - denuncias_access === false → off (mesmo com plano)
 *    - denuncias_access === true → on (mesmo sem chave no plano)
 *    - null → segue chave «denuncias» do plano da assinatura ativa
 * 2) Papel:
 *    - company_admin: todas as actions dos módulos ativos → vê Denúncias se (1)
 *    - company_user: precisa UserPermission denuncias/view no workspace ativo
 *      (e o módulo tem de estar em activePermissionModuleValues)
 * 3) Seção “Voz do Time” só renderiza se pesquisas|denuncias|desligamento view.
 *
 * Caso típico “Isabela não vê Denúncias”: company_user sem permissão granular,
 * ou empresa sem módulo/override, ou denuncias_access=false.
 */
class ClientSidebarDenunciasVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_matrix_includes_denuncias_when_module_enabled(): void
    {
        $company = $this->companyWithDenuncias(planHasDenuncias: true);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->assertTrue($company->hasComplaintsEnabled());
        $this->assertTrue($admin->canAccess(PermissionModule::Denuncias, PermissionAction::View));

        $matrix = $admin->permissionMatrixForFrontend();
        $this->assertArrayHasKey(PermissionModule::Denuncias->value, $matrix);
        $this->assertContains(PermissionAction::View->value, $matrix[PermissionModule::Denuncias->value]);
    }

    public function test_company_admin_matrix_omits_denuncias_when_module_disabled(): void
    {
        $company = $this->companyWithDenuncias(planHasDenuncias: false);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->assertFalse($company->hasComplaintsEnabled());
        $this->assertFalse($admin->canAccess(PermissionModule::Denuncias, PermissionAction::View));
        $this->assertArrayNotHasKey(
            PermissionModule::Denuncias->value,
            $admin->permissionMatrixForFrontend(),
        );
    }

    public function test_company_user_needs_granular_denuncias_view_even_when_module_enabled(): void
    {
        $company = $this->companyWithDenuncias(planHasDenuncias: true);
        $user = User::factory()->companyUser($company->id)->create();

        $this->assertTrue($company->hasComplaintsEnabled());
        $this->assertFalse($user->canAccess(PermissionModule::Denuncias, PermissionAction::View));
        $this->assertArrayNotHasKey(
            PermissionModule::Denuncias->value,
            $user->permissionMatrixForFrontend(),
        );

        $workspace = $user->workspaces()->firstOrFail();
        $user->setActiveWorkspace($workspace);
        UserPermission::query()->create([
            'user_workspace_id' => $workspace->id,
            'module' => PermissionModule::Denuncias->value,
            'action' => PermissionAction::View->value,
        ]);

        $user->unsetRelation('permissions');
        $this->assertTrue($user->canAccess(PermissionModule::Denuncias, PermissionAction::View));
        $this->assertContains(
            PermissionAction::View->value,
            $user->permissionMatrixForFrontend()[PermissionModule::Denuncias->value],
        );
    }

    public function test_denuncias_access_false_hides_module_from_admin_matrix(): void
    {
        $company = $this->companyWithDenuncias(planHasDenuncias: true);
        $company->update(['denuncias_access' => false]);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->assertFalse($company->fresh()->hasComplaintsEnabled());
        $this->assertArrayNotHasKey(
            PermissionModule::Denuncias->value,
            $admin->permissionMatrixForFrontend(),
        );
    }

    private function companyWithDenuncias(bool $planHasDenuncias): Company
    {
        $company = Company::query()->create([
            'name' => 'Empresa Denúncias Test',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $nr1 = Module::query()->firstOrCreate(
            ['key' => Module::KEY_NR1],
            ['name' => 'NR1', 'description' => 'Teste'],
        );
        $denuncias = Module::query()->firstOrCreate(
            ['key' => Module::KEY_DENUNCIAS],
            ['name' => 'Denúncias', 'description' => 'Teste'],
        );

        $plan = Plan::query()->create([
            'name' => 'Plano teste denuncias',
            'slug' => 'denuncias-'.Str::random(8),
            'price_monthly_cents' => 0,
            'is_active' => true,
        ]);
        $plan->modules()->sync(
            $planHasDenuncias ? [$nr1->id, $denuncias->id] : [$nr1->id],
        );

        Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        return $company->fresh();
    }
}
