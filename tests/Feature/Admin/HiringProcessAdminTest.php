<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\HiringProcessStage;
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
                ->component('Admin/HiringFollowUp/Index')
                ->has('stages', 8)
                ->has('processes')
                ->where('active_stage', HiringProcessStage::EngenhariaCargo->value));
    }

    public function test_admin_can_reorder_processes_in_stage_list(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Lista', 'is_active' => true]);
        $first = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Primeiro',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
            'sort_order' => 1,
        ]);
        $second = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Segundo',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.acompanhamento.index'))
            ->post(route('admin.acompanhamento.reorder'), [
                'stage' => HiringProcessStage::AnaliseCurriculo->value,
                'ordered_ids' => [$second->id, $first->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_admin_talents_can_access_acompanhamento_without_granular_grants(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => false]);

        $this->actingAs($admin)
            ->get(route('admin.acompanhamento.index'))
            ->assertOk();
    }

    public function test_admin_can_create_process_with_notes_datetime_and_candidates(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Candidatos', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.store'), [
                'company_id' => $company->id,
                'title' => 'Analista com candidatos',
                'current_stage' => HiringProcessStage::AnaliseCurriculo->value,
                'notes' => 'Primeira triagem',
                'candidates_count' => 8,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hiring_processes', [
            'title' => 'Analista com candidatos',
            'candidates_count' => 8,
            'notes' => 'Primeira triagem',
        ]);

        $process = HiringProcess::query()->where('title', 'Analista com candidatos')->first();
        $this->assertNotNull($process?->notes_at);
        $this->assertNotNull($process?->candidates_count_at);
    }

    public function test_update_upserts_stage_entry_and_advance_archives_then_clears_draft(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Persist', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.store'), [
                'company_id' => $company->id,
                'title' => 'Vaga Persistente',
                'current_stage' => HiringProcessStage::AnaliseCurriculo->value,
                'notes' => 'Comentário inicial',
                'candidates_count' => 5,
            ])
            ->assertRedirect();

        $process = HiringProcess::query()->where('title', 'Vaga Persistente')->firstOrFail();
        $this->assertDatabaseHas('hiring_process_stage_entries', [
            'hiring_process_id' => $process->id,
            'stage' => HiringProcessStage::AnaliseCurriculo->value,
            'notes' => 'Comentário inicial',
            'candidates_count' => 5,
        ]);

        $this->travel(1)->seconds();

        $this->actingAs($admin)
            ->patch(route('admin.acompanhamento.update', $process), [
                'candidates_count' => 6,
                'notes' => 'Comentário guardado na etapa A',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hiring_process_stage_entries', [
            'hiring_process_id' => $process->id,
            'stage' => HiringProcessStage::AnaliseCurriculo->value,
            'notes' => 'Comentário guardado na etapa A',
            'candidates_count' => 6,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.advance', $process), [
                'notes' => 'Comentário final da etapa A',
                'candidates_count' => 7,
            ])
            ->assertRedirect();

        $process->refresh();
        $this->assertSame(HiringProcessStage::AnaliseComportamental, $process->current_stage);
        $this->assertNull($process->notes);
        $this->assertNull($process->candidates_count);

        $this->assertDatabaseHas('hiring_process_stage_entries', [
            'hiring_process_id' => $process->id,
            'stage' => HiringProcessStage::AnaliseCurriculo->value,
            'notes' => 'Comentário final da etapa A',
            'candidates_count' => 7,
        ]);

        $this->assertDatabaseMissing('hiring_process_stage_entries', [
            'hiring_process_id' => $process->id,
            'stage' => HiringProcessStage::AnaliseComportamental->value,
        ]);

        $this->travel(1)->seconds();

        $this->actingAs($admin)
            ->patch(route('admin.acompanhamento.update', $process), [
                'candidates_count' => 9,
                'notes' => 'Comentário da etapa B',
            ])
            ->assertRedirect();

        $process->refresh();
        $this->assertSame(9, $process->candidates_count);
        $this->assertSame('Comentário da etapa B', $process->notes);

        $this->assertDatabaseHas('hiring_process_stage_entries', [
            'hiring_process_id' => $process->id,
            'stage' => HiringProcessStage::AnaliseComportamental->value,
            'notes' => 'Comentário da etapa B',
            'candidates_count' => 9,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('admin.acompanhamento.index', [
                'stage' => HiringProcessStage::AnaliseComportamental->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('processes', 1)
                ->where('processes.0.candidates_count', 9)
                ->where('processes.0.notes', 'Comentário da etapa B')
                ->where('processes.0.stage_entries.0.stage', HiringProcessStage::AnaliseCurriculo->value)
                ->where('processes.0.stage_entries.0.notes', 'Comentário final da etapa A')
                ->where('processes.0.stage_entries.1.stage', HiringProcessStage::AnaliseComportamental->value)
                ->where('processes.0.stage_entries.1.notes', 'Comentário da etapa B'));
    }

    public function test_retreat_restores_previous_stage_draft_without_deleting_history(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Retreat', 'is_active' => true]);
        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga Retreat',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
            'notes' => 'Etapa A',
            'candidates_count' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.advance', $process), [
                'notes' => 'Etapa A',
                'candidates_count' => 2,
            ])
            ->assertRedirect();

        $process->refresh();
        $this->assertSame(HiringProcessStage::AnaliseComportamental, $process->current_stage);

        $this->actingAs($admin)
            ->patch(route('admin.acompanhamento.update', $process), [
                'notes' => 'Etapa B',
                'candidates_count' => 4,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.retreat', $process))
            ->assertRedirect();

        $process->refresh();
        $this->assertSame(HiringProcessStage::AnaliseCurriculo, $process->current_stage);
        $this->assertSame('Etapa A', $process->notes);
        $this->assertSame(2, $process->candidates_count);

        $this->assertDatabaseHas('hiring_process_stage_entries', [
            'hiring_process_id' => $process->id,
            'stage' => HiringProcessStage::AnaliseComportamental->value,
            'notes' => 'Etapa B',
            'candidates_count' => 4,
        ]);
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

    public function test_admin_can_add_observation_that_persists_across_stages(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Obs', 'is_active' => true]);
        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga Obs',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.acompanhamento.index'))
            ->post(route('admin.acompanhamento.comments.store', $process), [
                'body' => 'Segue feedback do gestor',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.advance', $process))
            ->assertRedirect();

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('admin.acompanhamento.index', [
                'stage' => HiringProcessStage::AnaliseComportamental->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('processes', 1)
                ->where('processes.0.comments.0.body', 'Segue feedback do gestor')
                ->where('processes.0.comments.0.author_role', 'talents'));
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

    public function test_admin_can_rename_process_title(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create(['name' => 'Empresa Rename', 'is_active' => true]);
        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'COORDENADOR DE LOJA | ANTIGO',
            'current_stage' => HiringProcessStage::EntrevistaPresencial,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.acompanhamento.update', $process), [
                'title' => 'COORDENADOR DE LOJA | GELATO BORELLI',
            ])
            ->assertRedirect();

        $process->refresh();
        $this->assertSame('COORDENADOR DE LOJA | GELATO BORELLI', $process->title);
        $this->assertSame(HiringProcessStage::EntrevistaPresencial, $process->current_stage);
        $this->assertSame($admin->id, $process->updated_by);
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
