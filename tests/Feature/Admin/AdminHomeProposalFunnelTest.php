<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProposalLostReason;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\LandingInterestSubmission;
use App\Models\User;
use App\Support\Admin\AdminHomeDashboardBuilder;
use App\Support\Commercial\ProposalListStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHomeProposalFunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_uses_leads_qualification_proposal_and_closed(): void
    {
        $this->withoutVite();

        LandingInterestSubmission::query()->create([
            'name' => 'Lead A',
            'email' => 'a@example.com',
            'source' => 'site',
            'is_qualified' => true,
        ]);
        LandingInterestSubmission::query()->create([
            'name' => 'Lead B',
            'email' => 'b@example.com',
            'source' => 'site',
            'is_qualified' => false,
        ]);
        LandingInterestSubmission::query()->create([
            'name' => 'Lead C',
            'email' => 'c@example.com',
            'source' => 'site',
            'is_qualified' => null,
        ]);

        // Fora do cohort (mês anterior)
        CommercialProposal::query()->create([
            'code' => 'PROP-OLD',
            'client_name' => 'Antiga',
            'list_status' => ProposalListStatus::CLOSED,
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

        $closed = CommercialProposal::query()->create([
            'code' => 'PROP-OK',
            'client_name' => 'Fechada',
            'list_status' => ProposalListStatus::CLOSED,
            'is_closed' => true,
            'closed_at' => now(),
            'total_final_cents' => 10_000,
        ]);

        CommercialSale::query()->create([
            'code' => 'VENDA-OK',
            'proposal_id' => $closed->id,
            'client_name' => 'Fechada',
            'total_cents' => 10_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-END',
            'client_name' => 'Perdida',
            'list_status' => ProposalListStatus::ENDED,
            'is_closed' => false,
            'lost_reason' => ProposalLostReason::Preco->value,
        ]);

        $home = app(AdminHomeDashboardBuilder::class)->build();
        $funnel = collect($home['funnel'])->keyBy('key');

        $this->assertSame(3, $funnel['leads']['count']);
        $this->assertSame(1, $funnel['qualified']['count']);
        $this->assertSame(3, $funnel['proposal']['count']);
        $this->assertSame(1, $funnel['closed']['count']);
        $this->assertSame(1, $home['funnel_lost']['count']);
        $this->assertSame('Preço', $home['funnel_lost']['items'][0]['responses'][0]['lost_reason_label'] ?? null);
        $this->assertSame('Perdida', $home['funnel_lost']['items'][0]['name'] ?? null);

        $funnelAll = collect($home['funnel_all'])->keyBy('key');
        $this->assertSame(3, $funnelAll['leads']['count']);
        $this->assertSame(1, $funnelAll['qualified']['count']);
        $this->assertSame(4, $funnelAll['proposal']['count']);
        $this->assertSame(2, $funnelAll['closed']['count']);
        $this->assertSame(1, $home['funnel_lost_all']['count']);
        $this->assertStringNotContainsString('created_from=', $home['funnel_lost_all']['href']);

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('funnel.0.key', 'leads')
                ->where('funnel.0.count', 3)
                ->where('funnel.1.key', 'qualified')
                ->where('funnel.1.count', 1)
                ->where('funnel.3.key', 'closed')
                ->where('funnel.3.count', 1)
                ->where('funnelLost.count', 1)
                ->where('funnelAll.2.key', 'proposal')
                ->where('funnelAll.2.count', 4)
                ->where('funnelAll.3.count', 2)
                ->where('funnelLostAll.count', 1));
    }

    public function test_funnel_lost_href_and_items_by_client(): void
    {
        $this->withoutVite();

        CommercialProposal::query()->create([
            'code' => 'PROP-END-1',
            'client_name' => 'Cliente A',
            'list_status' => ProposalListStatus::ENDED,
            'is_closed' => false,
            'lost_reason' => ProposalLostReason::Timing->value,
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-END-2',
            'client_name' => 'Cliente B',
            'list_status' => ProposalListStatus::ENDED,
            'is_closed' => false,
            'lost_reason' => ProposalLostReason::Timing->value,
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-END-NONE',
            'client_name' => 'Sem Motivo',
            'list_status' => ProposalListStatus::ENDED,
            'is_closed' => false,
            'lost_reason' => null,
        ]);

        $home = app(AdminHomeDashboardBuilder::class)->build();
        $from = Carbon::today()->startOfMonth()->toDateString();
        $to = Carbon::today()->endOfMonth()->toDateString();

        $this->assertSame(3, $home['funnel_lost']['count']);
        $this->assertStringContainsString('status=perdidas', $home['funnel_lost']['href']);
        $this->assertStringContainsString('created_from='.$from, $home['funnel_lost']['href']);
        $this->assertStringContainsString('created_to='.$to, $home['funnel_lost']['href']);

        $items = collect($home['funnel_lost']['items'])->keyBy('name');
        $this->assertCount(3, $items);
        $this->assertSame(1, $items['Cliente A']['count']);
        $this->assertSame('Timing', $items['Cliente A']['responses'][0]['lost_reason_label']);
        $this->assertSame(1, $items['Cliente B']['count']);
        $this->assertSame('Sem motivo', $items['Sem Motivo']['responses'][0]['lost_reason_label']);

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('funnelLost.count', 3)
                ->has('funnelLost.items', 3));
    }
}
