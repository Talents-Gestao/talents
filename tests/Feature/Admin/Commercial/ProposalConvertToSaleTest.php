<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProposalConvertToSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_convert_redirects_to_proposals_index_with_sale_flash(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::create([
            'code' => 'PROP-CONV-0001',
            'client_name' => 'Cliente Conversão',
            'employee_count' => 12,
            'total_final_cents' => 10_000,
            'commission_percent' => 10,
            'commission_cents' => 1_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.comercial.propostas.converter', $proposal),
            [
                'payment_method' => 'pix',
                'installments_count' => 2,
                'first_due_date' => now()->toDateString(),
                'notes' => 'Teste de conversão',
            ],
        );

        $sale = CommercialSale::query()->where('proposal_id', $proposal->id)->first();
        $this->assertNotNull($sale);
        $this->assertSame('pix', $sale->payment_method);
        $this->assertSame(2, $sale->installments_count);
        $this->assertCount(2, $sale->installments);

        $response
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('sale_id', $sale->id)
            ->assertSessionHas('sale_code', $sale->code);

        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('flash.sale_id', $sale->id)
                ->where('flash.sale_code', $sale->code)
                ->where('flash.success', fn ($msg) => is_string($msg) && str_contains($msg, $sale->code))
            );
    }

    public function test_convert_misto_creates_parts_and_exposes_flash(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::create([
            'code' => 'PROP-CONV-MIX',
            'client_name' => 'Cliente Misto',
            'employee_count' => 8,
            'total_final_cents' => 10_001,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.comercial.propostas.converter', $proposal),
            [
                'payment_method' => 'misto',
                'first_due_date' => now()->toDateString(),
                'mix_parts' => [
                    ['method' => 'pix', 'percent' => 50],
                    ['method' => 'cartao', 'percent' => 50],
                ],
            ],
        );

        $sale = CommercialSale::query()->where('proposal_id', $proposal->id)->first();
        $this->assertNotNull($sale);
        $this->assertSame('misto', $sale->payment_method);
        $this->assertSame(2, $sale->installments_count);
        $this->assertSame('pix', $sale->installments[0]->method);
        $this->assertSame('cartao', $sale->installments[1]->method);
        $this->assertSame(10_001, (int) $sale->installments->sum('amount_cents'));

        $response
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('sale_id', $sale->id)
            ->assertSessionHas('sale_code', $sale->code);
    }

    public function test_convert_rejects_misto_when_percent_sum_is_not_100(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::create([
            'code' => 'PROP-CONV-BAD',
            'client_name' => 'Cliente Inválido',
            'employee_count' => 5,
            'total_final_cents' => 5_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->post(route('admin.comercial.propostas.converter', $proposal), [
                'payment_method' => 'misto',
                'first_due_date' => now()->toDateString(),
                'mix_parts' => [
                    ['method' => 'pix', 'percent' => 40],
                    ['method' => 'boleto', 'percent' => 40],
                ],
            ])
            ->assertSessionHasErrors('mix_parts');

        $this->assertFalse(
            CommercialSale::query()->where('proposal_id', $proposal->id)->exists()
        );
    }

    public function test_convert_rejects_open_proposal(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::create([
            'code' => 'PROP-CONV-OPEN',
            'client_name' => 'Ainda Aberta',
            'employee_count' => 3,
            'total_final_cents' => 3_000,
            'is_closed' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->post(route('admin.comercial.propostas.converter', $proposal), [
                'payment_method' => 'pix',
                'installments_count' => 1,
                'first_due_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('proposal');

        $this->assertFalse(
            CommercialSale::query()->where('proposal_id', $proposal->id)->exists()
        );
    }

    public function test_convert_rejects_negotiation_and_ended_proposals(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        foreach ([
            ['code' => 'PROP-CONV-NEG', 'list_status' => 'negotiation'],
            ['code' => 'PROP-CONV-END', 'list_status' => 'ended'],
        ] as $row) {
            $proposal = CommercialProposal::create([
                'code' => $row['code'],
                'client_name' => 'Sem conversão',
                'employee_count' => 3,
                'total_final_cents' => 3_000,
                'is_closed' => false,
                'list_status' => $row['list_status'],
            ]);

            $this->actingAs($admin)
                ->from(route('admin.comercial.propostas.index'))
                ->post(route('admin.comercial.propostas.converter', $proposal), [
                    'payment_method' => 'pix',
                    'installments_count' => 1,
                    'first_due_date' => now()->toDateString(),
                ])
                ->assertSessionHasErrors('proposal');

            $this->assertFalse(
                CommercialSale::query()->where('proposal_id', $proposal->id)->exists()
            );
        }
    }

    public function test_convert_allows_first_due_date_on_2026_08_07(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::create([
            'code' => 'PROP-CONV-0708',
            'client_name' => 'Cliente 07/08/2026',
            'employee_count' => 5,
            'total_final_cents' => 450_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $dueDate = '2026-08-07';

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->post(route('admin.comercial.propostas.converter', $proposal), [
                'payment_method' => 'pix',
                'installments_count' => 1,
                'first_due_date' => $dueDate,
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('sale_id');

        $sale = CommercialSale::query()->where('proposal_id', $proposal->id)->first();
        $this->assertNotNull($sale);
        $this->assertSame($dueDate, $sale->installments->first()?->due_date?->toDateString());
    }

    public function test_convert_allows_past_first_due_date(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::create([
            'code' => 'PROP-CONV-PAST',
            'client_name' => 'Cliente Retroativo',
            'employee_count' => 5,
            'total_final_cents' => 5_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $pastDue = now()->subDays(5)->toDateString();

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->post(route('admin.comercial.propostas.converter', $proposal), [
                'payment_method' => 'pix',
                'installments_count' => 1,
                'first_due_date' => $pastDue,
            ])
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('sale_id');

        $sale = CommercialSale::query()->where('proposal_id', $proposal->id)->first();
        $this->assertNotNull($sale);
        $this->assertSame($pastDue, $sale->installments->first()?->due_date?->toDateString());
    }

    public function test_convert_validation_messages_are_in_portuguese(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::create([
            'code' => 'PROP-CONV-PT',
            'client_name' => 'Cliente Validação',
            'employee_count' => 5,
            'total_final_cents' => 5_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->post(route('admin.comercial.propostas.converter', $proposal), [
                'payment_method' => 'pix',
                'installments_count' => 1,
            ])
            ->assertSessionHasErrors([
                'first_due_date' => 'Informe a data do primeiro vencimento.',
            ]);

        $this->assertFalse(
            CommercialSale::query()->where('proposal_id', $proposal->id)->exists()
        );
    }
}
