<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\HiringProcessStage;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AcompanhamentoModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_view_acompanhamento_when_enabled(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Acompanhamento',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Coordenador Comercial',
            'current_stage' => HiringProcessStage::EntrevistaGestor,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.acompanhamento.index', [
                'stage' => HiringProcessStage::EntrevistaGestor->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Acompanhamento/Index')
                ->where('active_stage', HiringProcessStage::EntrevistaGestor->value)
                ->has('processes', 1)
                ->where('processes.0.title', 'Coordenador Comercial'));
    }

    public function test_company_admin_forbidden_when_module_disabled(): void
    {
        $company = Company::query()->create([
            'name' => 'Sem Acompanhamento',
            'acompanhamento_access' => false,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.acompanhamento.index'))
            ->assertForbidden();
    }

    public function test_client_only_sees_own_company_processes(): void
    {
        $company = Company::query()->create([
            'name' => 'Minha Empresa',
            'acompanhamento_access' => true,
        ]);
        $other = Company::query()->create([
            'name' => 'Outra Empresa',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);

        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga própria',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);
        HiringProcess::query()->create([
            'company_id' => $other->id,
            'title' => 'Vaga alheia',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $admin = User::factory()->companyAdmin($company->id)->create();
        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.acompanhamento.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('all_processes', 1)
                ->where('all_processes.0.title', 'Vaga própria')
                ->has('processes', 1)
                ->where('processes.0.title', 'Vaga própria'));
    }

    public function test_company_user_needs_permission(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Perm',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $user = User::factory()->companyUser($company->id)->create();

        $this->actingAs($user)
            ->get(route('client.acompanhamento.index'))
            ->assertForbidden();

        $user->permissions()->create([
            'module' => PermissionModule::Acompanhamento->value,
            'action' => PermissionAction::View->value,
        ]);

        $this->withoutVite();

        $this->actingAs($user)
            ->get(route('client.acompanhamento.index'))
            ->assertOk();
    }

    public function test_client_cannot_mutate_via_admin_routes(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Client',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();
        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.advance', $process))
            ->assertForbidden();
    }
}
