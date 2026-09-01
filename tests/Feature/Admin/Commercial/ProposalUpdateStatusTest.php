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

class ProposalUpdateStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_close_open_proposal_from_status_endpoint(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-001',
            'client_name' => 'Cliente Status',
            'employee_count' => 8,
            'total_final_cents' => 10_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'approved',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('success');

        $proposal->refresh();
        $this->assertTrue($proposal->is_closed);
        $this->assertNotNull($proposal->closed_at);
        $this->assertSame(ProposalListStatus::CLOSED, $proposal->list_status);
        $this->assertSame(ProposalListStatus::CLOSED, ProposalListStatus::for($proposal));
    }

    public function test_legacy_closed_slug_normalizes_to_closed(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-LEG',
            'client_name' => 'Legado Closed',
            'employee_count' => 8,
            'total_final_cents' => 10_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'closed',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal->refresh();
        $this->assertSame(ProposalListStatus::CLOSED, $proposal->list_status);
        $this->assertTrue($proposal->is_closed);
    }

    public function test_admin_can_set_negotiation_normalizing_to_open(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-IP',
            'client_name' => 'Em Negociação Manual',
            'employee_count' => 6,
            'total_final_cents' => 7_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'negotiation',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('success');

        $proposal->refresh();
        $this->assertFalse($proposal->is_closed);
        $this->assertNull($proposal->closed_at);
        $this->assertSame(ProposalListStatus::OPEN, $proposal->list_status);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', ['view' => 'list']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('proposals.data.0.list_status', ProposalListStatus::OPEN)
                ->where('proposals.data.0.list_status_label', 'Aberta')
            );
    }

    public function test_legacy_in_progress_slug_normalizes_to_open(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-LEG-IP',
            'client_name' => 'Legado IP',
            'employee_count' => 6,
            'total_final_cents' => 7_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'in_progress',
            ])
            ->assertRedirect();

        $proposal->refresh();
        $this->assertSame(ProposalListStatus::OPEN, $proposal->list_status);
        $this->assertFalse($proposal->is_closed);
    }

    public function test_admin_can_end_proposal_without_closing(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-END',
            'client_name' => 'Perdida',
            'employee_count' => 4,
            'total_final_cents' => 5_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'ended',
                'lost_reason' => 'preco',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal->refresh();
        $this->assertFalse($proposal->is_closed);
        $this->assertSame(ProposalListStatus::ENDED, $proposal->list_status);
        $this->assertSame('preco', $proposal->lost_reason);
        $this->assertSame('Perdida', ProposalListStatus::labelFor($proposal));
    }

    public function test_ending_proposal_requires_lost_reason(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-END-REQ',
            'client_name' => 'Sem Motivo',
            'employee_count' => 4,
            'total_final_cents' => 5_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'ended',
            ])
            ->assertSessionHasErrors('lost_reason');

        $this->assertNotSame(ProposalListStatus::ENDED, $proposal->fresh()->list_status);
    }

    public function test_ending_with_outros_requires_notes(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-END-OUTROS',
            'client_name' => 'Outros',
            'employee_count' => 4,
            'total_final_cents' => 5_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'ended',
                'lost_reason' => 'outros',
            ])
            ->assertSessionHasErrors('lost_reason_notes');

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'ended',
                'lost_reason' => 'outros',
                'lost_reason_notes' => 'Cliente mudou de prioridade interna.',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal->refresh();
        $this->assertSame(ProposalListStatus::ENDED, $proposal->list_status);
        $this->assertSame('outros', $proposal->lost_reason);
        $this->assertSame('Cliente mudou de prioridade interna.', $proposal->lost_reason_notes);
    }

    public function test_reopening_clears_lost_reason(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-END-CLEAR',
            'client_name' => 'Limpar Motivo',
            'employee_count' => 4,
            'total_final_cents' => 5_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::ENDED,
            'lost_reason' => 'preco',
            'lost_reason_notes' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'negotiation',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal->refresh();
        $this->assertSame(ProposalListStatus::OPEN, $proposal->list_status);
        $this->assertNull($proposal->lost_reason);
        $this->assertNull($proposal->lost_reason_notes);
    }

    public function test_admin_can_reopen_closed_proposal(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-002',
            'client_name' => 'Cliente Reabrir',
            'employee_count' => 4,
            'total_final_cents' => 5_000,
            'is_closed' => true,
            'closed_at' => now()->subDay(),
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'open',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('success');

        $proposal->refresh();
        $this->assertFalse($proposal->is_closed);
        $this->assertNull($proposal->closed_at);
        $this->assertSame(ProposalListStatus::OPEN, $proposal->list_status);
    }

    public function test_reopen_with_sale_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-003',
            'client_name' => 'Com Venda',
            'employee_count' => 3,
            'total_final_cents' => 8_000,
            'is_closed' => true,
            'closed_at' => now(),
            'list_status' => ProposalListStatus::CLOSED,
        ]);

        CommercialSale::query()->create([
            'code' => 'VENDA-2026-0800',
            'proposal_id' => $proposal->id,
            'client_name' => 'Com Venda',
            'total_cents' => 8_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'open',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHasErrors('status');

        $this->assertTrue($proposal->fresh()->sale()->exists());
        $this->assertTrue($proposal->fresh()->is_closed);
        $this->assertSame(ProposalListStatus::CLOSED, $proposal->fresh()->list_status);
    }

    public function test_setting_approved_normalizes_to_closed_even_with_parcial_sale(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-004',
            'client_name' => 'Parcial',
            'employee_count' => 2,
            'total_final_cents' => 9_000,
            'is_closed' => false,
            'list_status' => ProposalListStatus::OPEN,
        ]);

        CommercialSale::query()->create([
            'code' => 'VENDA-2026-0801',
            'proposal_id' => $proposal->id,
            'client_name' => 'Parcial',
            'total_cents' => 9_000,
            'payment_method' => 'pix',
            'installments_count' => 2,
            'status' => CommercialSale::STATUS_PARCIAL,
            'sold_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'approved',
            ])
            ->assertRedirect();

        $proposal->refresh()->load('sale');
        $this->assertTrue($proposal->is_closed);
        $this->assertSame(ProposalListStatus::CLOSED, $proposal->list_status);
        $this->assertSame(ProposalListStatus::CLOSED, ProposalListStatus::for($proposal));
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-005',
            'client_name' => 'Inválido',
            'employee_count' => 1,
            'total_final_cents' => 1_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'won',
            ])
            ->assertSessionHasErrors('status');

        $this->assertFalse($proposal->fresh()->is_closed);
        $this->assertSame(ProposalListStatus::OPEN, $proposal->fresh()->list_status);
    }

    public function test_guest_cannot_update_status(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-006',
            'client_name' => 'Guest',
            'employee_count' => 1,
            'total_final_cents' => 1_000,
            'is_closed' => false,
        ]);

        $this->patch(route('admin.comercial.propostas.status', $proposal), [
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertFalse($proposal->fresh()->is_closed);
    }
}
