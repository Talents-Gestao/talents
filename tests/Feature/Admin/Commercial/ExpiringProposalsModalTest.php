<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Models\User;
use App\Support\Commercial\ProposalListStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpiringProposalsModalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_index_includes_open_proposals_near_or_past_validity(): void
    {
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        CommercialSetting::current()->update(['pdf_validade_dias' => 7]);

        $expired = CommercialProposal::query()->create([
            'code' => 'PROP-EXP-001',
            'client_name' => 'Empresa Expirada',
            'client_representative' => 'João Contato',
            'employee_count' => 10,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
            'created_at' => Carbon::parse('2026-08-24 12:00:00'),
        ]);

        $soon = CommercialProposal::query()->create([
            'code' => 'PROP-SOON-001',
            'client_name' => 'Empresa A Vencer',
            'employee_count' => 8,
            'total_final_cents' => 800,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
            'created_at' => Carbon::parse('2026-09-05 08:00:00'),
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-FRESH-001',
            'client_name' => 'Empresa Nova',
            'employee_count' => 5,
            'total_final_cents' => 500,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
            'created_at' => Carbon::parse('2026-09-09 09:00:00'),
        ]);

        CommercialProposal::query()->create([
            'code' => 'PROP-CLOSED-001',
            'client_name' => 'Empresa Fechada',
            'employee_count' => 5,
            'total_final_cents' => 500,
            'is_closed' => true,
            'list_status' => ProposalListStatus::CLOSED,
            'created_at' => Carbon::parse('2026-08-01 09:00:00'),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->has('expiringProposals', 2)
                ->where('expiringProposals.0.id', $expired->id)
                ->where('expiringProposals.0.contact_name', 'João Contato')
                ->where('expiringProposals.0.company_name', 'Empresa Expirada')
                ->where('expiringProposals.0.valid_until', '2026-08-31')
                ->where('expiringProposals.0.is_expired', true)
                ->where('expiringProposals.1.id', $soon->id)
                ->where('expiringProposals.1.is_expired', false)
                ->where('expiringProposals.1.valid_until', '2026-09-12'));
    }

    public function test_admin_can_mark_expiring_proposal_as_contacted(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-CONT-001',
            'client_name' => 'Cliente Contato',
            'employee_count' => 4,
            'total_final_cents' => 400,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->patch(route('admin.comercial.propostas.contacted', $proposal))
            ->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal->refresh();
        $this->assertNotNull($proposal->contacted_at);
    }

    public function test_admin_can_update_proposal_notes_from_expiring_modal(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-NOTE-001',
            'client_name' => 'Cliente Observação',
            'employee_count' => 4,
            'total_final_cents' => 400,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
            'notes' => 'Antiga',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->patch(route('admin.comercial.propostas.notes', $proposal), [
                'notes' => 'Cliente pediu retorno na sexta.',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('success');

        $this->assertSame('Cliente pediu retorno na sexta.', $proposal->fresh()->notes);
    }
}
