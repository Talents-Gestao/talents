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

    public function test_index_exposes_status_counts_respecting_other_filters(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $seller = User::factory()->create(['is_commercial' => true, 'is_active' => true]);

        CommercialProposal::create([
            'code' => 'PROP-CNT-0001',
            'client_name' => 'Aberta Seller',
            'employee_count' => 5,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'seller_id' => $seller->id,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        CommercialProposal::create([
            'code' => 'PROP-CNT-0002',
            'client_name' => 'Fechada Seller',
            'employee_count' => 5,
            'total_final_cents' => 2000,
            'is_closed' => true,
            'closed_at' => now(),
            'seller_id' => $seller->id,
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        CommercialProposal::create([
            'code' => 'PROP-CNT-0003',
            'client_name' => 'Outro vendedor',
            'employee_count' => 5,
            'total_final_cents' => 3000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', [
                'seller_id' => $seller->id,
                'status' => 'abertas',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 1)
                ->where('statusCounts.all', 2)
                ->where('statusCounts.abertas', 1)
                ->where('statusCounts.fechadas', 1)
                ->where('statusCounts.em_andamento', 0)
            );
    }

    public function test_index_filters_by_sale_situation(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $without = CommercialProposal::create([
            'code' => 'PROP-SALE-0001',
            'client_name' => 'Sem venda',
            'employee_count' => 5,
            'total_final_cents' => 1000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        $with = CommercialProposal::create([
            'code' => 'PROP-SALE-0002',
            'client_name' => 'Com venda',
            'employee_count' => 5,
            'total_final_cents' => 2000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        CommercialSale::create([
            'proposal_id' => $with->id,
            'code' => 'VENDA-SALE-0002',
            'client_name' => $with->client_name,
            'total_cents' => $with->total_final_cents,
            'status' => CommercialSale::STATUS_QUITADA,
            'installments_count' => 1,
            'sold_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['sale_situation' => 'without_sale']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 1)
                ->where('proposals.data.0.id', $without->id)
                ->where('filters.sale_situation', 'without_sale')
            );

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['sale_situation' => 'with_sale']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 1)
                ->where('proposals.data.0.id', $with->id)
            );
    }

    public function test_index_filters_by_created_period(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $inside = CommercialProposal::create([
            'code' => 'PROP-DATE-0001',
            'client_name' => 'Dentro',
            'employee_count' => 5,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'created_at' => '2026-03-15 10:00:00',
            'updated_at' => '2026-03-15 10:00:00',
        ]);

        CommercialProposal::create([
            'code' => 'PROP-DATE-0002',
            'client_name' => 'Fora',
            'employee_count' => 5,
            'total_final_cents' => 2000,
            'is_closed' => false,
            'created_at' => '2026-01-05 10:00:00',
            'updated_at' => '2026-01-05 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', [
                'created_from' => '2026-03-01',
                'created_to' => '2026-03-31',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 1)
                ->where('proposals.data.0.id', $inside->id)
            );
    }

    public function test_index_rejects_invalid_created_period(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->get(route('admin.comercial.propostas.index', [
                'created_from' => '2026-04-10',
                'created_to' => '2026-04-01',
            ]))
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHasErrors(['created_to']);
    }
}
