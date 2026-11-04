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

    public function test_dashboard_without_range_does_not_send_range_props(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('funnel')
                ->has('funnelAll')
                ->missing('funnelRange')
                ->missing('funnelLostRange')
                ->missing('funnelRangePeriod'));
    }

    public function test_funnel_range_counts_records_created_between_dates_inclusive(): void
    {
        $this->withoutVite();

        $lead = fn (string $name, string $at, bool $qualified) => LandingInterestSubmission::query()->forceCreate([
            'name' => $name,
            'email' => mb_strtolower($name).'@example.com',
            'source' => 'site',
            'is_qualified' => $qualified,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $lead('Antes', '2026-03-31 23:59:59', true);
        $lead('Inicio', '2026-04-01 00:00:00', true);
        $lead('Meio', '2026-04-15 12:00:00', false);
        $lead('Fim', '2026-04-30 23:59:59', true);
        $lead('Depois', '2026-05-01 00:00:00', true);

        $proposal = fn (string $code, string $status, string $at) => CommercialProposal::query()->forceCreate([
            'code' => $code,
            'client_name' => $code,
            'list_status' => $status,
            'is_closed' => $status === ProposalListStatus::CLOSED,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $proposal('PROP-ANTES', ProposalListStatus::CLOSED, '2026-03-20 10:00:00');
        $proposal('PROP-ABERTA', ProposalListStatus::OPEN, '2026-04-01 08:00:00');
        $proposal('PROP-FECHADA', ProposalListStatus::CLOSED, '2026-04-10 08:00:00');
        $proposal('PROP-PERDIDA', ProposalListStatus::ENDED, '2026-04-30 18:00:00');
        $proposal('PROP-DEPOIS', ProposalListStatus::ENDED, '2026-05-02 09:00:00');

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['data_inicio' => '2026-04-01', 'data_fim' => '2026-04-30']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('funnelRangePeriod', ['start' => '2026-04-01', 'end' => '2026-04-30'])
                ->where('funnelRange.0.key', 'leads')
                ->where('funnelRange.0.count', 3)
                ->where('funnelRange.1.count', 2)
                ->where('funnelRange.2.count', 3)
                ->where('funnelRange.3.count', 1)
                ->where('funnelLostRange.count', 1)
                ->where('funnelLostRange.items.0.name', 'PROP-PERDIDA')
                ->where('funnelLostRange.href', fn ($href) => str_contains($href, 'created_from=2026-04-01') && str_contains($href, 'created_to=2026-04-30'))
                // Visões atuais continuam no payload, sem alteração.
                ->where('funnelAll.0.count', 5)
                ->where('funnelAll.2.count', 5));
    }

    public function test_funnel_range_validates_dates(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->get(route('admin.dashboard', ['data_inicio' => '2026-04-30', 'data_fim' => '2026-04-01']))
            ->assertSessionHasErrors(['data_fim']);

        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->get(route('admin.dashboard', ['data_inicio' => '2026-04-01']))
            ->assertSessionHasErrors(['data_fim']);
    }

    public function test_funnel_range_requires_dashboard_access(): void
    {
        $companyUser = User::factory()->create();

        $this->actingAs($companyUser)
            ->get(route('admin.dashboard', ['data_inicio' => '2026-04-01', 'data_fim' => '2026-04-30']))
            ->assertForbidden();
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
