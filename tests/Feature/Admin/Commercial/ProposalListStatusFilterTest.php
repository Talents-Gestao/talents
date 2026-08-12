<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\User;
use App\Support\Commercial\ProposalListStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProposalListStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_exposes_list_status_and_filters_em_andamento(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $open = CommercialProposal::create([
            'code' => 'PROP-2026-0101',
            'client_name' => 'Aberto',
            'employee_count' => 5,
            'total_final_cents' => 1000,
            'is_closed' => false,
        ]);

        $closedOnly = CommercialProposal::create([
            'code' => 'PROP-2026-0102',
            'client_name' => 'Fechada sem venda',
            'employee_count' => 5,
            'total_final_cents' => 2000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $partial = CommercialProposal::create([
            'code' => 'PROP-2026-0103',
            'client_name' => 'Parcial',
            'employee_count' => 5,
            'total_final_cents' => 3000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => ProposalListStatus::IN_PROGRESS,
        ]);

        CommercialSale::create([
            'proposal_id' => $partial->id,
            'code' => 'VENDA-2026-0103',
            'client_name' => $partial->client_name,
            'total_cents' => $partial->total_final_cents,
            'status' => CommercialSale::STATUS_PARCIAL,
            'installments_count' => 2,
            'sold_at' => now(),
        ]);

        $quitada = CommercialProposal::create([
            'code' => 'PROP-2026-0104',
            'client_name' => 'Quitada',
            'employee_count' => 5,
            'total_final_cents' => 4000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        CommercialSale::create([
            'proposal_id' => $quitada->id,
            'code' => 'VENDA-2026-0104',
            'client_name' => $quitada->client_name,
            'total_cents' => $quitada->total_final_cents,
            'status' => CommercialSale::STATUS_QUITADA,
            'installments_count' => 2,
            'sold_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['status' => 'em_andamento']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->has('proposals.data', 1)
                ->where('proposals.data.0.id', $partial->id)
                ->where('proposals.data.0.list_status', ProposalListStatus::IN_PROGRESS)
                ->where('proposals.data.0.list_status_label', 'Em andamento')
            );

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['status' => 'abertas']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 1)
                ->where('proposals.data.0.id', $open->id)
                ->where('proposals.data.0.list_status', ProposalListStatus::OPEN)
            );

        $expectedClosedIds = collect([$closedOnly->id, $quitada->id])->sort()->values()->all();

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['status' => 'fechadas']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 2)
                ->where(
                    'proposals.data',
                    fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === $expectedClosedIds,
                )
                ->where('proposals.data', fn ($rows) => collect($rows)->every(
                    fn ($row) => ($row['list_status'] ?? null) === ProposalListStatus::CLOSED,
                ))
            );
    }
}
