<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Enums\CommercialProductPricingType;
use App\Models\CommercialProduct;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fluxo ponta a ponta: criar proposta fechada → converter em venda →
 * listar em Financeiro → Vendas → abrir o detalhe da venda.
 */
class ProposalToSaleFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_flow_create_proposal_convert_sale_list_and_show(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $seller = User::factory()->create([
            'is_commercial' => true,
            'is_active' => true,
            'name' => 'Vendedor Fluxo',
        ]);

        $product = CommercialProduct::query()->create([
            'name' => 'Palestras e Treinamentos',
            'slug' => 'palestras-fluxo',
            'pricing_type' => CommercialProductPricingType::Fixed,
            'pricing_config' => ['amount_cents' => 450_000],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $clientName = 'Empresa Fluxo E2E Ltda';
        $clientCnpj = '12.345.678/0001-90';

        // 1) Criar proposta já marcada como fechada (pronta para converter).
        $createResponse = $this->actingAs($admin)->post(route('admin.comercial.propostas.store'), [
            'client_name' => $clientName,
            'client_cnpj' => $clientCnpj,
            'client_email' => 'fluxo@empresa.test',
            'employee_count' => 25,
            'seller_id' => $seller->id,
            'is_closed' => true,
            'payment_method_id' => \App\Models\FinancePaymentMethod::query()->where('slug', 'pix')->value('id'),
            'catalog_products' => [
                [
                    'product_id' => $product->id,
                    'enabled' => true,
                ],
            ],
        ]);

        $createResponse
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('success');

        $proposal = CommercialProposal::query()
            ->where('client_name', $clientName)
            ->first();

        $this->assertNotNull($proposal);
        $this->assertTrue($proposal->is_closed);
        $this->assertNotNull($proposal->closed_at);
        $this->assertSame($seller->id, $proposal->seller_id);
        $this->assertSame(450_000, (int) $proposal->total_final_cents);
        $this->assertFalse($proposal->sale()->exists());

        // 2) Converter proposta em venda.
        $dueDate = now()->addDays(7)->toDateString();

        $convertResponse = $this->actingAs($admin)->post(
            route('admin.comercial.propostas.converter', $proposal),
            [
                'payment_method' => 'pix',
                'installments_count' => 3,
                'first_due_date' => $dueDate,
                'notes' => 'Conversão do fluxo E2E',
            ],
        );

        $sale = CommercialSale::query()
            ->where('proposal_id', $proposal->id)
            ->with(['installments', 'commission', 'proposal'])
            ->first();

        $this->assertNotNull($sale);
        $this->assertSame(450_000, (int) $sale->total_cents);
        $this->assertSame($clientName, $sale->client_name);
        $this->assertSame($clientCnpj, $sale->client_cnpj);
        $this->assertSame($seller->id, $sale->seller_id);
        $this->assertSame($admin->id, $sale->created_by);
        $this->assertSame('pix', $sale->payment_method);
        $this->assertSame(3, $sale->installments_count);
        $this->assertSame(CommercialSale::STATUS_ABERTA, $sale->status);
        $this->assertSame('Conversão do fluxo E2E', $sale->notes);
        $this->assertCount(3, $sale->installments);
        $this->assertSame(450_000, (int) $sale->installments->sum('amount_cents'));
        $this->assertTrue(
            $sale->installments->every(fn ($i) => $i->status === 'pendente' && $i->method === 'pix')
        );
        $this->assertMatchesRegularExpression('/^VENDA-\d{4}-\d{4}$/', $sale->code);

        $convertResponse
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('success', fn ($msg) => is_string($msg)
                && str_contains($msg, $sale->code)
                && str_contains($msg, $proposal->code))
            ->assertSessionHas('sale_id', $sale->id)
            ->assertSessionHas('sale_code', $sale->code);

        // Flash Inertia na lista de propostas (modal de sucesso → CTA para a venda).
        $this->actingAs($admin)
            ->get(route('admin.comercial.propostas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Commercial/Proposals/Index')
                ->where('flash.sale_id', $sale->id)
                ->where('flash.sale_code', $sale->code)
            );

        // 3) Listagem Financeiro → Vendas contém a venda gerada.
        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Sales/Index')
                ->has('sales.data', 1)
                ->where('sales.data.0.id', $sale->id)
                ->where('sales.data.0.code', $sale->code)
                ->where('sales.data.0.client_name', $clientName)
                ->where('sales.data.0.status', CommercialSale::STATUS_ABERTA)
                ->where('sales.data.0.total_cents', 450_000)
                ->where('sales.data.0.installments_count', 3)
                ->where('sales.data.0.pending_installments_count', 3)
                ->where('sales.data.0.proposal.id', $proposal->id)
                ->where('sales.data.0.proposal.code', $proposal->code)
                ->where('sales.data.0.seller.id', $seller->id)
                ->where('sales.data.0.seller.name', 'Vendedor Fluxo')
            );

        // Busca na listagem por código e por nome do cliente.
        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.index', ['search' => $sale->code]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sales.data', 1)
                ->where('sales.data.0.id', $sale->id)
            );

        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.index', ['search' => 'Fluxo E2E']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sales.data', 1)
                ->where('sales.data.0.client_name', $clientName)
            );

        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.index', ['search' => 'inexistente-xyz']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('sales.data', 0));

        // 4) Detalhe da venda (tela que o CTA do modal abre).
        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.show', $sale))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Sales/Show')
                ->where('sale.id', $sale->id)
                ->where('sale.code', $sale->code)
                ->where('sale.client_name', $clientName)
                ->where('sale.client_cnpj', $clientCnpj)
                ->where('sale.client_email', 'fluxo@empresa.test')
                ->where('sale.total_cents', 450_000)
                ->where('sale.status', CommercialSale::STATUS_ABERTA)
                ->where('sale.payment_method', 'pix')
                ->where('sale.installments_count', 3)
                ->where('sale.notes', 'Conversão do fluxo E2E')
                ->where('sale.proposal.id', $proposal->id)
                ->where('sale.proposal.code', $proposal->code)
                ->where('sale.seller.id', $seller->id)
                ->where('sale.seller.name', 'Vendedor Fluxo')
                ->has('sale.installments', 3)
                ->where('sale.installments.0.number', 1)
                ->where('sale.installments.0.method', 'pix')
                ->where('sale.installments.0.status', 'pendente')
                ->where('sale.installments.2.number', 3)
                ->has('paymentMethods.pix')
                ->has('paymentMethods.boleto')
                ->has('paymentMethods.cartao')
            );
    }

    public function test_guest_cannot_create_convert_or_view_sales(): void
    {
        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-GUEST-0001',
            'client_name' => 'Cliente Guest',
            'employee_count' => 1,
            'total_final_cents' => 1_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-2026-9999',
            'proposal_id' => $proposal->id,
            'client_name' => 'Cliente Guest',
            'total_cents' => 1_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        $this->post(route('admin.comercial.propostas.store'), [
            'client_name' => 'X',
            'employee_count' => 1,
            'payment_method_id' => \App\Models\FinancePaymentMethod::query()->where('slug', 'pix')->value('id'),
        ])->assertRedirect();

        $this->post(route('admin.comercial.propostas.converter', $proposal), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->get(route('admin.financeiro.vendas.index'))->assertRedirect();
        $this->get(route('admin.financeiro.vendas.show', $sale))->assertRedirect();
    }

    public function test_convert_twice_is_rejected_and_sale_remains_unique(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-DUP-0001',
            'client_name' => 'Cliente Duplicado',
            'employee_count' => 4,
            'total_final_cents' => 8_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $payload = [
            'payment_method' => 'boleto',
            'installments_count' => 2,
            'first_due_date' => now()->addDay()->toDateString(),
        ];

        $this->actingAs($admin)
            ->post(route('admin.comercial.propostas.converter', $proposal), $payload)
            ->assertRedirect(route('admin.comercial.propostas.index'))
            ->assertSessionHas('sale_id');

        $this->assertSame(1, CommercialSale::query()->where('proposal_id', $proposal->id)->count());

        $this->actingAs($admin)
            ->from(route('admin.comercial.propostas.index'))
            ->post(route('admin.comercial.propostas.converter', $proposal), $payload)
            ->assertSessionHasErrors('proposal');

        $this->assertSame(1, CommercialSale::query()->where('proposal_id', $proposal->id)->count());
    }
}
