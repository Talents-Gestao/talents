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

    public function test_admin_can_approve_open_proposal_from_status_endpoint(): void
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
        $this->assertSame(ProposalListStatus::APPROVED, $proposal->list_status);
        $this->assertSame(ProposalListStatus::APPROVED, ProposalListStatus::for($proposal));
    }

    public function test_legacy_closed_slug_normalizes_to_approved(): void
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
        $this->assertSame(ProposalListStatus::APPROVED, $proposal->list_status);
        $this->assertTrue($proposal->is_closed);
    }

    public function test_admin_can_set_negotiation_without_closing(): void
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
        $this->assertSame(ProposalListStatus::NEGOTIATION, $proposal->list_status);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('proposals.data.0.list_status', ProposalListStatus::NEGOTIATION)
                ->where('proposals.data.0.list_status_label', 'Em negociação')
            );
    }

    public function test_legacy_in_progress_slug_normalizes_to_negotiation(): void
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
        $this->assertSame(ProposalListStatus::NEGOTIATION, $proposal->list_status);
        $this->assertFalse($proposal->is_closed);
    }

    public function test_admin_can_end_proposal_without_closing(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-STATUS-END',
            'client_name' => 'Encerrada',
            'employee_count' => 4,
            'total_final_cents' => 5_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comercial.propostas.status', $proposal), [
                'status' => 'ended',
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'));

        $proposal->refresh();
        $this->assertFalse($proposal->is_closed);
        $this->assertSame(ProposalListStatus::ENDED, $proposal->list_status);
        $this->assertSame('Encerrada', ProposalListStatus::labelFor($proposal));
    }

    public function test_admin_can_reopen_approved_proposal(): void
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
            'list_status' => ProposalListStatus::APPROVED,
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
            'list_status' => ProposalListStatus::APPROVED,
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
        $this->assertSame(ProposalListStatus::APPROVED, $proposal->fresh()->list_status);
    }

    public function test_setting_approved_persists_even_with_parcial_sale(): void
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
        $this->assertSame(ProposalListStatus::APPROVED, $proposal->list_status);
        $this->assertSame(ProposalListStatus::APPROVED, ProposalListStatus::for($proposal));
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
