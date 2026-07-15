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

    public function test_company_admin_can_crud_exit_interviews(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Desligamento',
            'desligamento_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = \App\Models\User::factory()->companyAdmin($company->id)->create();

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.desligamento.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('client.desligamento.create'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('client.desligamento.store'), [
                'employee_name' => 'Colaborador Saída',
                'employee_email' => 'saida@teste.local',
                'interview_date' => '2026-07-10',
                'status' => ExitInterviewStatus::Completed->value,
                'answers' => ['q1' => 'Experiência positiva no geral.'],
                'consultant_notes' => ['main_reasons' => 'Oportunidade externa.'],
            ])
            ->assertRedirect(route('client.desligamento.index'));

        $interview = ExitInterview::query()->first();
        $this->assertNotNull($interview);
        $this->assertSame('Colaborador Saída', $interview->employee_name);
        $this->assertNull($interview->rhid_person_id);

        $this->actingAs($admin)
            ->get(route('client.desligamento.show', $interview))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('client.desligamento.edit', $interview))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('client.desligamento.update', $interview), [
                'employee_name' => 'Colaborador Saída Atualizado',
                'employee_email' => 'saida@teste.local',
                'interview_date' => '2026-07-11',
                'status' => ExitInterviewStatus::Draft->value,
                'answers' => ['q1' => 'Atualizado.'],
                'consultant_notes' => ['main_reasons' => 'Mudança de cidade.'],
            ])
            ->assertRedirect(route('client.desligamento.index'));

        $this->assertSame('Colaborador Saída Atualizado', $interview->fresh()->employee_name);

        $this->actingAs($admin)
            ->delete(route('client.desligamento.destroy', $interview))
            ->assertRedirect(route('client.desligamento.index'));

        $this->assertDatabaseMissing('exit_interviews', ['id' => $interview->id]);
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

        $this->actingAs($user)
            ->post(route('client.desligamento.store'), [
                'employee_name' => 'Sem permissão',
                'status' => ExitInterviewStatus::Draft->value,
            ])
            ->assertForbidden();
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
