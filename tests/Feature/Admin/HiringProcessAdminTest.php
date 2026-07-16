<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\HiringProcessStage;
use App\Enums\PermissionAction;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HiringProcessAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_owner_can_open_acompanhamento(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('admin.acompanhamento.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Acompanhamento/Index')
                ->has('stages', 6)
                ->has('columns', 6)
                ->where('active_stage', HiringProcessStage::AnaliseCurriculo->value));
    }

    public function test_admin_can_move_stage_from_board_and_stay_on_page(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Board', 'is_active' => true]);
        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga Drag',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.acompanhamento.index'))
            ->patch(route('admin.acompanhamento.update', $process), [
                'current_stage' => HiringProcessStage::EntrevistaPresencial->value,
                'from_board' => true,
            ])
            ->assertRedirect(route('admin.acompanhamento.index'));

        $process->refresh();
        $this->assertSame(HiringProcessStage::EntrevistaPresencial, $process->current_stage);
    }

    public function test_admin_without_solides_permission_is_forbidden(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);

        app(SyncAdminUserPermissions::class)->execute($admin->talentsWorkspace(), [
            [
                'module' => AdminPermissionModule::Dashboard->value,
                'action' => PermissionAction::View->value,
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.acompanhamento.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_and_advance_process(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Acompanhamento', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.store'), [
                'company_id' => $company->id,
                'title' => 'Analista de RH',
                'current_stage' => HiringProcessStage::AnaliseCurriculo->value,
            ])
            ->assertRedirect();

        $process = HiringProcess::query()->first();
        $this->assertNotNull($process);
        $this->assertSame('Analista de RH', $process->title);
        $this->assertSame(HiringProcessStage::AnaliseCurriculo, $process->current_stage);

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.advance', $process))
            ->assertRedirect(route('admin.acompanhamento.index', [
                'stage' => HiringProcessStage::AnaliseComportamental->value,
            ]));

        $process->refresh();
        $this->assertSame(HiringProcessStage::AnaliseComportamental, $process->current_stage);
        $this->assertSame($admin->id, $process->updated_by);
    }

    public function test_admin_can_move_stage_via_update_and_retreat(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa X', 'is_active' => true]);
        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga Dev',
            'current_stage' => HiringProcessStage::EntrevistaPresencial,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.acompanhamento.update', $process), [
                'current_stage' => HiringProcessStage::Contratacao->value,
            ])
            ->assertRedirect();

        $process->refresh();
        $this->assertSame(HiringProcessStage::Contratacao, $process->current_stage);

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.retreat', $process))
            ->assertRedirect();

        $process->refresh();
        $this->assertSame(HiringProcessStage::VisitaEmpresa, $process->current_stage);
    }

    public function test_admin_can_destroy_process(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Y', 'is_active' => true]);
        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Remover',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.acompanhamento.destroy', $process))
            ->assertRedirect();

        $this->assertDatabaseMissing('hiring_processes', ['id' => $process->id]);
    }
}
