<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\User;
use App\Support\Admin\AdminHomeDashboardBuilder;
use App\Support\Commercial\ProposalListStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHomeProposalFunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_uses_proposal_cohort_of_current_month(): void
    {
        $this->withoutVite();

        // Fora do cohort (mês anterior)
        CommercialProposal::query()->create([
            'code' => 'PROP-OLD',
            'client_name' => 'Antiga',
            'list_status' => ProposalListStatus::APPROVED,
            'is_closed' => true,
            'closed_at' => now()->subMonth(),
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-OPEN',
            'client_name' => 'Aberta',
            'list_status' => ProposalListStatus::OPEN,
            'is_closed' => false,
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-NEG',
            'client_name' => 'Negociação',
            'list_status' => ProposalListStatus::NEGOTIATION,
            'is_closed' => false,
        ]);

        $approved = CommercialProposal::query()->create([
            'code' => 'PROP-OK',
            'client_name' => 'Aprovada',
            'list_status' => ProposalListStatus::APPROVED,
            'is_closed' => true,
            'closed_at' => now(),
            'total_final_cents' => 10_000,
        ]);

        CommercialSale::query()->create([
            'code' => 'VENDA-OK',
            'proposal_id' => $approved->id,
            'client_name' => 'Aprovada',
            'total_cents' => 10_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-END',
            'client_name' => 'Encerrada',
            'list_status' => ProposalListStatus::ENDED,
            'is_closed' => false,
        ]);

        $home = app(AdminHomeDashboardBuilder::class)->build();
        $funnel = collect($home['funnel'])->keyBy('key');

        $this->assertSame(4, $funnel['proposal']['count']);
        $this->assertSame(1, $funnel['negotiation']['count']);
        $this->assertSame(1, $funnel['approved']['count']);
        $this->assertSame(1, $funnel['sale']['count']);
        $this->assertSame(1, $funnel['ended']['count']);

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('funnel.0.key', 'proposal')
                ->where('funnel.0.count', 4)
                ->where('funnel.1.key', 'negotiation')
                ->where('funnel.1.count', 1)
                ->where('funnel.3.key', 'sale')
                ->where('funnel.3.count', 1));
    }

    public function test_funnel_stages_include_hrefs_and_ended_closers_by_seller(): void
    {
        $this->withoutVite();

        $seller = User::factory()->superAdmin()->create([
            'name' => 'Vendedora Encerrada',
            'is_owner' => true,
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-END-1',
            'client_name' => 'Cliente A',
            'list_status' => ProposalListStatus::ENDED,
            'is_closed' => false,
            'seller_id' => $seller->id,
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-END-2',
            'client_name' => 'Cliente B',
            'list_status' => ProposalListStatus::ENDED,
            'is_closed' => false,
            'seller_id' => $seller->id,
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-END-NONE',
            'client_name' => 'Sem Vendedor',
            'list_status' => ProposalListStatus::ENDED,
            'is_closed' => false,
            'seller_id' => null,
        ]);

        $home = app(AdminHomeDashboardBuilder::class)->build();
        $funnel = collect($home['funnel'])->keyBy('key');
        $from = Carbon::today()->startOfMonth()->toDateString();
        $to = Carbon::today()->endOfMonth()->toDateString();

        $this->assertStringContainsString('status=encerradas', $funnel['ended']['href']);
        $this->assertStringContainsString('created_from='.$from, $funnel['ended']['href']);
        $this->assertStringContainsString('created_to='.$to, $funnel['ended']['href']);
        $this->assertStringContainsString('sale_situation=with_sale', $funnel['sale']['href']);
        $this->assertStringContainsString('status=em_negociacao', $funnel['negotiation']['href']);
        $this->assertStringContainsString('status=aprovadas', $funnel['approved']['href']);

        $closers = collect($home['funnel_ended_closers']);
        $this->assertCount(2, $closers);

        $sellerRow = $closers->firstWhere('seller_id', $seller->id);
        $this->assertNotNull($sellerRow);
        $this->assertSame('Vendedora Encerrada', $sellerRow['seller_name']);
        $this->assertSame(2, $sellerRow['count']);

        $noneRow = $closers->firstWhere('seller_id', null);
        $this->assertNotNull($noneRow);
        $this->assertSame('Sem vendedor', $noneRow['seller_name']);
        $this->assertSame(1, $noneRow['count']);

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('funnelEndedClosers', 2)
                ->where('funnel.4.key', 'ended')
                ->where('funnel.4.count', 3));
    }
}
