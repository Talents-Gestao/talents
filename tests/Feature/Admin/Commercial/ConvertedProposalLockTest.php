<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertedProposalLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_update_proposal_after_sale_conversion(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $proposal = $this->convertedProposal();

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.edit', $proposal))
            ->put(route('admin.comercial.propostas.update', $proposal), [
                'client_name' => 'Nome alterado após venda',
                'employee_count' => 99,
            ])
            ->assertRedirect(route('admin.comercial.propostas.edit', $proposal))
            ->assertSessionHasErrors('proposal');

        $this->assertSame('Cliente Convertido', $proposal->fresh()->client_name);
        $this->assertSame(8, (int) $proposal->fresh()->employee_count);
    }

    public function test_cannot_delete_proposal_after_sale_conversion(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $proposal = $this->convertedProposal();

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->delete(route('admin.comercial.propostas.destroy', $proposal))
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHasErrors('proposal');

        $this->assertTrue(CommercialProposal::query()->whereKey($proposal->id)->exists());
        $this->assertTrue($proposal->fresh()->sale()->exists());
    }

    private function convertedProposal(): CommercialProposal
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-LOCK-0001',
            'client_name' => 'Cliente Convertido',
            'employee_count' => 8,
            'total_final_cents' => 12_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        CommercialSale::query()->create([
            'code' => 'VENDA-2026-LOCK',
            'proposal_id' => $proposal->id,
            'client_name' => $proposal->client_name,
            'total_cents' => 12_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        return $proposal;
    }
}
