<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProposalQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordenacao_fila_is_ignored_and_queue_payload_is_absent(): void
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

        CommercialProposal::create([
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
                'view' => 'list',
                'status' => 'abertas',
                'ordenacao' => 'fila',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->missing('queue')
                ->missing('queue_total')
                ->has('proposals.data', 3)
                ->where('proposals.data.0.id', $newest->id)
                ->where('proposals.data.2.id', $oldest->id)
            );
    }
}
