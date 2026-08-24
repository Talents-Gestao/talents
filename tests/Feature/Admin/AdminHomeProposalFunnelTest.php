<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\User;
use App\Support\Admin\AdminHomeDashboardBuilder;
use App\Support\Commercial\ProposalListStatus;
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

        $funnel = collect(app(AdminHomeDashboardBuilder::class)->build()['funnel'])
            ->keyBy('key');

        $this->assertSame(4, $funnel['proposal']['count']);
        $this->assertSame(1, $funnel['negotiation']['count']);
        $this->assertSame(1, $funnel['approved']['count']);
        $this->assertSame(1, $funnel['sale']['count']);
        $this->assertSame(1, $funnel['ended']['count']);

        $admin = User::factory()->superAdmin()->create();
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
}
