<?php

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProposalQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposals_index_orders_fifo_when_ordenacao_is_fila(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $oldest = CommercialProposal::create([
            'code' => 'PROP-2026-0001',
            'client_name' => 'Cliente Antigo',
            'employee_count' => 10,
            'total_final_cents' => 1000,
            'is_closed' => false,
            'created_at' => now()->subDays(5),
        ]);

        $middle = CommercialProposal::create([
            'code' => 'PROP-2026-0002',
            'client_name' => 'Cliente Médio',
            'employee_count' => 20,
            'total_final_cents' => 2000,
            'is_closed' => false,
            'created_at' => now()->subDays(2),
        ]);

        $newest = CommercialProposal::create([
            'code' => 'PROP-2026-0003',
            'client_name' => 'Cliente Novo',
            'employee_count' => 30,
            'total_final_cents' => 3000,
            'is_closed' => false,
            'created_at' => now()->subDay(),
        ]);

        CommercialProposal::create([
            'code' => 'PROP-2026-0004',
            'client_name' => 'Cliente Fechado',
            'employee_count' => 5,
            'total_final_cents' => 500,
            'is_closed' => true,
            'closed_at' => now(),
            'created_at' => now()->subDays(10),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index', [
                'status' => 'abertas',
                'ordenacao' => 'fila',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('queue_total', 3)
                ->has('queue', 3)
                ->where('queue.0.id', $oldest->id)
                ->where('queue.0.queue_position', 1)
                ->where('queue.1.id', $middle->id)
                ->where('queue.1.queue_position', 2)
                ->where('queue.2.id', $newest->id)
                ->where('queue.2.queue_position', 3)
                ->has('proposals.data', 3)
                ->where('proposals.data.0.id', $oldest->id)
                ->where('proposals.data.0.queue_position', 1)
                ->where('proposals.data.2.id', $newest->id)
                ->where('proposals.data.2.queue_position', 3)
            );
    }
}
