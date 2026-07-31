<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FinancePayableStatus;
use App\Models\FinancePayable;
use App\Models\FinancePaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePayablesModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_payment_methods(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('admin.financeiro.formas-pagamento.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.financeiro.formas-pagamento.store'), [
                'name' => 'Cheque',
                'slug' => 'cheque',
                'is_active' => true,
                'sort_order' => 10,
            ])
            ->assertRedirect(route('admin.financeiro.formas-pagamento.index'));

        $this->assertDatabaseHas('finance_payment_methods', [
            'name' => 'Cheque',
            'slug' => 'cheque',
        ]);
    }

    public function test_admin_can_crud_payable_and_mark_paid(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $method = FinancePaymentMethod::query()->create([
            'name' => 'PIX',
            'slug' => 'pix-test',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                'title' => 'Aluguel escritório',
                'supplier_name' => 'Imobiliária X',
                'amount_reais' => 1500.5,
                'due_date' => '2026-08-10',
                'status' => FinancePayableStatus::Pending->value,
                'payment_method_id' => $method->id,
                'notes' => 'Referente a agosto',
            ])
            ->assertRedirect(route('admin.financeiro.contas-a-pagar.index'));

        $payable = FinancePayable::query()->first();
        $this->assertNotNull($payable);
        $this->assertSame(150050, $payable->amount_cents);
        $this->assertSame(FinancePayableStatus::Pending, $payable->status);

        $this->actingAs($admin)
            ->patch(route('admin.financeiro.contas-a-pagar.mark-paid', $payable))
            ->assertRedirect();

        $payable->refresh();
        $this->assertSame(FinancePayableStatus::Paid, $payable->status);
        $this->assertNotNull($payable->paid_at);
        $this->assertSame(150050, $payable->paid_amount_cents);

        $this->actingAs($admin)
            ->delete(route('admin.financeiro.contas-a-pagar.destroy', $payable))
            ->assertRedirect(route('admin.financeiro.contas-a-pagar.index'));

        $this->assertDatabaseMissing('finance_payables', ['id' => $payable->id]);
    }

    public function test_cannot_delete_payment_method_with_payables(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $method = FinancePaymentMethod::query()->create([
            'name' => 'Boleto',
            'slug' => 'boleto-test',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        FinancePayable::query()->create([
            'title' => 'Internet',
            'amount_cents' => 9900,
            'due_date' => '2026-08-01',
            'status' => FinancePayableStatus::Pending,
            'payment_method_id' => $method->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.formas-pagamento.index'))
            ->delete(route('admin.financeiro.formas-pagamento.destroy', $method))
            ->assertRedirect(route('admin.financeiro.formas-pagamento.index'));

        $this->assertDatabaseHas('finance_payment_methods', ['id' => $method->id]);
    }
}
