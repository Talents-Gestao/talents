<?php

// Cartão do quadro SUPORTE: «PROPOSTA» (forma de pagamento editável ao criar e editar; criar proposta com prévia do total).
// Já cobertos: forma obrigatória ao criar, opções vindas do Financeiro e PDF com a forma escolhida
// (Admin/Commercial/ProposalPaymentMethodPdfTest).

namespace Tests\Feature\Acceptance\Support;

use App\Enums\CommercialProductPricingType;
use App\Models\CommercialProduct;
use App\Models\CommercialProposal;
use App\Models\FinancePaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProposalTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_creates_a_proposal_with_the_previewed_total_and_changes_its_payment_method_later(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $boleto = FinancePaymentMethod::query()->where('slug', 'boleto')->firstOrFail();
        $pix = FinancePaymentMethod::query()->where('slug', 'pix')->firstOrFail();

        $product = CommercialProduct::query()->create([
            'name' => 'Pesquisa NR-1',
            'slug' => 'pesquisa-nr1-aceite',
            'pricing_type' => CommercialProductPricingType::PerEmployee,
            'pricing_config' => ['cents_per_employee' => 5_000],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $catalog = ['catalog_products' => [['product_id' => $product->id, 'enabled' => true]]];

        $previewTotal = $this->actingAs($admin)
            ->postJson(route('admin.comercial.propostas.preview'), ['employee_count' => 12, ...$catalog])
            ->assertOk()
            ->json('total_final_cents');
        $this->assertSame(60_000, $previewTotal);

        $this->post(route('admin.comercial.propostas.store'), [
            'client_name' => 'Cliente Aceite',
            'employee_count' => 12,
            'is_closed' => false,
            'payment_method_id' => $boleto->id,
            ...$catalog,
        ])->assertRedirect();

        $proposal = CommercialProposal::query()->where('client_name', 'Cliente Aceite')->firstOrFail();
        $this->assertSame($previewTotal, (int) $proposal->total_final_cents);
        $this->assertSame($boleto->id, (int) $proposal->payment_method_id);

        // A edição abre como modal da listagem (edit redireciona para ?form=edit&proposal_id=).
        $this->get(route('admin.comercial.propostas.edit', $proposal))
            ->assertRedirect(route('admin.comercial.propostas.index', ['form' => 'edit', 'proposal_id' => $proposal->id]));
        $this->get(route('admin.comercial.propostas.index', ['form' => 'edit', 'proposal_id' => $proposal->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('formModal.mode', 'edit')
                ->where('formModal.proposal.payment_method_id', $boleto->id)
                ->has('formModal.paymentMethodOptions'));

        $this->put(route('admin.comercial.propostas.update', $proposal), [
            'client_name' => 'Cliente Aceite',
            'employee_count' => 12,
            'is_closed' => false,
            'payment_method_id' => $pix->id,
            ...$catalog,
        ])->assertRedirect();

        $fresh = $proposal->fresh();
        $this->assertSame($pix->id, (int) $fresh->payment_method_id);
        $this->assertSame('pix', $fresh->payment_method);
    }
}
