<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\ExitInterviewStatus;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\ExitInterview;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesligamentoModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_view_exit_interviews_but_cannot_mutate(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Desligamento',
            'desligamento_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = \App\Models\User::factory()->companyAdmin($company->id)->create();

        $interview = ExitInterview::query()->create([
            'company_id' => $company->id,
            'rhid_person_id' => 77,
            'employee_name' => 'Colaborador Saída',
            'employee_email' => 'saida@teste.local',
            'interview_date' => '2026-07-10',
            'status' => ExitInterviewStatus::Completed,
            'answers' => ['q1' => 'Experiência positiva no geral.'],
            'consultant_notes' => ['main_reasons' => 'Oportunidade externa.'],
            'created_by' => $admin->id,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.desligamento.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('client.desligamento.show', $interview))
            ->assertOk();

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('client.desligamento.store'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('client.desligamento.destroy'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('client.desligamento.create'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('client.desligamento.edit'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('client.desligamento.update'));

        $this->assertDatabaseHas('exit_interviews', ['id' => $interview->id]);
    }

    public function test_company_user_with_view_permission_can_read_desligamento(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Desligamento',
            'desligamento_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $user = \App\Models\User::factory()->companyUser($company->id)->create();
        $workspace = $user->workspaces()->first();
        UserPermission::query()->create([
            'user_workspace_id' => $workspace->id,
            'module' => PermissionModule::Desligamento->value,
            'action' => PermissionAction::View->value,
        ]);

        $interview = ExitInterview::query()->create([
            'company_id' => $company->id,
            'rhid_person_id' => 88,
            'employee_name' => 'Colaborador Vista',
            'employee_email' => 'vista@teste.local',
            'interview_date' => '2026-07-11',
            'status' => ExitInterviewStatus::Completed,
            'answers' => null,
            'consultant_notes' => null,
            'created_by' => null,
        ]);

        $this->withoutVite();

        $this->actingAs($user)
            ->get(route('client.desligamento.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('client.desligamento.show', $interview))
            ->assertOk();
    }

    public function test_company_user_without_permission_cannot_access_desligamento(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Desligamento',
            'desligamento_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $user = \App\Models\User::factory()->companyUser($company->id)->create();

        $this->actingAs($user)
            ->get(route('client.desligamento.index'))
            ->assertForbidden();
    }
}
