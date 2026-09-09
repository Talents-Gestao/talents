<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialContract;
use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Models\User;
use App\Support\Commercial\ProposalListStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProposalBoardMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_index_exposes_board_metrics_above_kanban(): void
    {
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        CommercialSetting::current()->update(['pdf_validade_dias' => 7]);

        CommercialProposal::query()->create([
            'code' => 'PROP-MET-WON',
            'client_name' => 'Fechada no mês',
            'employee_count' => 10,
            'total_final_cents' => 10_000,
            'is_closed' => true,
            'closed_at' => Carbon::parse('2026-09-03 12:00:00'),
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-MET-OPEN',
            'client_name' => 'Aberta recente',
            'employee_count' => 8,
            'total_final_cents' => 4_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
            'created_at' => Carbon::parse('2026-09-08 09:00:00'),
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-MET-NOCONTACT',
            'client_name' => 'Sem contato',
            'employee_count' => 5,
            'total_final_cents' => 2_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
            'created_at' => Carbon::parse('2026-08-20 09:00:00'),
        ]);

        $pendingZap = CommercialProposal::query()->create([
            'code' => 'PROP-MET-ZAP',
            'client_name' => 'Zap pendente',
            'employee_count' => 5,
            'total_final_cents' => 3_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
            'created_at' => Carbon::parse('2026-09-07 09:00:00'),
        ]);

        CommercialContract::query()->create([
            'proposal_id' => $pendingZap->id,
            'code' => 'CTR-MET-ZAP',
            'template_name_snapshot' => 'Template demo',
            'pdf_path' => 'contracts/met-zap.pdf',
            'html_snapshot' => '<p>demo</p>',
            'generated_at' => now(),
            'zapsign_document_token' => 'tok-met',
            'zapsign_status' => 'pending',
            'zapsign_sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('boardMetrics.won_month_count', 1)
                ->where('boardMetrics.won_month_cents', 10_000)
                ->where('boardMetrics.total_count', 4)
                ->where('boardMetrics.leads_count', 0)
                ->where('boardMetrics.pipeline_open_cents', 9_000)
                ->where('boardMetrics.open_count', 3)
                ->where('boardMetrics.avg_ticket_open_cents', 3_000)
                ->where('boardMetrics.expiring_count', 1)
                ->where('boardMetrics.expired_count', 1)
                ->where('boardMetrics.no_contact_count', 1)
                ->where('boardMetrics.zapsign_pending_count', 1));
    }

    public function test_commercial_dashboard_redirects_to_proposals_index(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.dashboard'))
            ->assertRedirect(route('admin.comercial.propostas.index'));
    }
}
