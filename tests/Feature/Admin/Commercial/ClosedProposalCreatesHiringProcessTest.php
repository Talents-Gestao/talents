<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Enums\HiringProcessStage;
use App\Models\CommercialProposal;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\User;
use App\Support\Commercial\ProposalListStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosedProposalCreatesHiringProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_proposal_creates_hiring_process_in_engenharia_cargo(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Empresa Match CNPJ',
            'cnpj' => '12.345.678/0001-95',
            'is_active' => true,
        ]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-HIRE-001',
            'client_name' => 'Empresa Match CNPJ',
            'client_cnpj' => '12345678000195',
            'employee_count' => 10,
            'total_final_cents' => 50_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('info');

        $this->assertDatabaseCount('hiring_processes', 1);

        $process = HiringProcess::query()->first();
        $this->assertNotNull($process);
        $this->assertSame($company->id, $process->company_id);
        $this->assertSame($proposal->id, $process->commercial_proposal_id);
        $this->assertSame(HiringProcessStage::EngenhariaCargo, $process->current_stage);
        $this->assertSame($admin->id, $process->updated_by);
        $this->assertStringContainsString('Contratação —', $process->title);
        $this->assertStringContainsString('PROP-HIRE-001', $process->title);
    }

    public function test_approving_again_does_not_duplicate_hiring_process(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        Company::query()->create([
            'name' => 'Empresa Idempotente',
            'cnpj' => '11.222.333/0001-81',
            'is_active' => true,
        ]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-HIRE-002',
            'client_name' => 'Empresa Idempotente',
            'client_cnpj' => '11.222.333/0001-81',
            'employee_count' => 5,
            'total_final_cents' => 20_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('hiring_processes', 1);

        // Já fechada: re-salvar status aprovado não deve criar outro.
        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'approved',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('hiring_processes', 1);
        $this->assertSame(1, HiringProcess::query()->where('commercial_proposal_id', $proposal->id)->count());
    }

    public function test_approving_without_matching_company_does_not_create_process_and_flashes_info(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-HIRE-003',
            'client_name' => 'Cliente Sem Empresa',
            'client_cnpj' => '99.888.777/0001-66',
            'employee_count' => 3,
            'total_final_cents' => 10_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('info', 'Proposta fechada, mas falta empresa cadastrada com este CNPJ para abrir o acompanhamento.');

        $proposal->refresh();
        $this->assertTrue($proposal->is_closed);
        $this->assertDatabaseCount('hiring_processes', 0);
    }
}
