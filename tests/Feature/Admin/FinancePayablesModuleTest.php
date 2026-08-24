<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FinanceBankAccountType;
use App\Enums\FinancePayableStatus;
use App\Models\FinanceBankAccount;
use App\Models\FinancePayable;
use App\Models\FinancePaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        $account = FinanceBankAccount::query()->create([
            'name' => 'Conta Origem',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 0,
            'is_active' => true,
            'created_by' => $admin->id,
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
            ->patch(route('admin.financeiro.contas-a-pagar.mark-paid', $payable), [
                'bank_account_id' => $account->id,
                'payment_method_id' => $method->id,
            ])
            ->assertRedirect();

        $payable->refresh();
        $this->assertSame(FinancePayableStatus::Paid, $payable->status);
        $this->assertNotNull($payable->paid_at);
        $this->assertSame(150050, $payable->paid_amount_cents);
        $this->assertSame($account->id, $payable->bank_account_id);

        $this->actingAs($admin)
            ->delete(route('admin.financeiro.contas-a-pagar.destroy', $payable))
            ->assertRedirect(route('admin.financeiro.contas-a-pagar.index'));

        $this->assertDatabaseMissing('finance_payables', ['id' => $payable->id]);
    }

    public function test_store_without_recurrence_creates_one_payable(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                'title' => 'Internet',
                'supplier_name' => 'Provedor',
                'amount_reais' => 99.9,
                'due_date' => '2026-08-10',
                'status' => FinancePayableStatus::Pending->value,
                'is_recurring' => false,
            ])
            ->assertRedirect(route('admin.financeiro.contas-a-pagar.index'));

        $this->assertDatabaseCount('finance_payables', 1);
        $payable = FinancePayable::query()->first();
        $this->assertNotNull($payable);
        $this->assertFalse($payable->is_recurring);
        $this->assertNull($payable->recurring_months);
        $this->assertNull($payable->recurring_group_id);
        $this->assertSame(9990, $payable->amount_cents);
        $this->assertSame('2026-08-10', $payable->due_date?->toDateString());
    }

    public function test_store_with_recurrence_creates_n_payables(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $method = FinancePaymentMethod::query()->create([
            'name' => 'PIX',
            'slug' => 'pix-recurring',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                'title' => 'Aluguel escritório',
                'supplier_name' => 'Imobiliária X',
                'amount_reais' => 1500,
                'due_date' => '2026-01-31',
                'status' => FinancePayableStatus::Pending->value,
                'payment_method_id' => $method->id,
                'notes' => 'Contrato anual',
                'is_recurring' => true,
                'recurring_months' => 6,
            ])
            ->assertRedirect(route('admin.financeiro.contas-a-pagar.index'))
            ->assertSessionHas('success', 'Série recorrente cadastrada: 6 lançamentos mensais.');

        $this->assertDatabaseCount('finance_payables', 6);

        $rows = FinancePayable::query()->orderBy('recurring_index')->get();
        $this->assertCount(6, $rows);
        $groupId = $rows->first()?->recurring_group_id;
        $this->assertNotNull($groupId);
        $this->assertTrue($rows->every(fn (FinancePayable $p) => $p->recurring_group_id === $groupId));
        $this->assertTrue($rows->every(fn (FinancePayable $p) => $p->is_recurring));
        $this->assertTrue($rows->every(fn (FinancePayable $p) => $p->recurring_months === 6));
        $this->assertTrue($rows->every(fn (FinancePayable $p) => $p->amount_cents === 150000));
        $this->assertTrue($rows->every(fn (FinancePayable $p) => $p->title === 'Aluguel escritório'));
        $this->assertTrue($rows->every(fn (FinancePayable $p) => $p->supplier_name === 'Imobiliária X'));
        $this->assertTrue($rows->every(fn (FinancePayable $p) => $p->payment_method_id === $method->id));
        $this->assertTrue($rows->every(fn (FinancePayable $p) => $p->notes === 'Contrato anual'));

        $this->assertSame([
            '2026-01-31',
            '2026-02-28',
            '2026-03-31',
            '2026-04-30',
            '2026-05-31',
            '2026-06-30',
        ], $rows->map(fn (FinancePayable $p) => $p->due_date?->toDateString())->all());

        $this->assertSame(
            ['Recorrente · Mês 1/6', 'Recorrente · Mês 2/6', 'Recorrente · Mês 3/6', 'Recorrente · Mês 4/6', 'Recorrente · Mês 5/6', 'Recorrente · Mês 6/6'],
            $rows->map(fn (FinancePayable $p) => $p->recurringLabel())->all(),
        );
    }

    public function test_store_recurrence_requires_at_least_two_months(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-a-pagar.create'))
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                'title' => 'Aluguel',
                'amount_reais' => 1000,
                'due_date' => '2026-08-10',
                'status' => FinancePayableStatus::Pending->value,
                'is_recurring' => true,
                'recurring_months' => 1,
            ])
            ->assertRedirect(route('admin.financeiro.contas-a-pagar.create'))
            ->assertSessionHasErrors(['recurring_months']);

        $this->assertDatabaseCount('finance_payables', 0);
    }

    public function test_update_does_not_regenerate_recurring_series(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $groupId = (string) Str::uuid();

        $first = FinancePayable::query()->create([
            'title' => 'Aluguel',
            'amount_cents' => 100000,
            'due_date' => '2026-08-10',
            'status' => FinancePayableStatus::Pending,
            'is_recurring' => true,
            'recurring_months' => 3,
            'recurring_index' => 1,
            'recurring_group_id' => $groupId,
            'created_by' => $admin->id,
        ]);
        FinancePayable::query()->create([
            'title' => 'Aluguel',
            'amount_cents' => 100000,
            'due_date' => '2026-09-10',
            'status' => FinancePayableStatus::Pending,
            'is_recurring' => true,
            'recurring_months' => 3,
            'recurring_index' => 2,
            'recurring_group_id' => $groupId,
            'created_by' => $admin->id,
        ]);
        FinancePayable::query()->create([
            'title' => 'Aluguel',
            'amount_cents' => 100000,
            'due_date' => '2026-10-10',
            'status' => FinancePayableStatus::Pending,
            'is_recurring' => true,
            'recurring_months' => 3,
            'recurring_index' => 3,
            'recurring_group_id' => $groupId,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $first), [
                'title' => 'Aluguel ajustado',
                'amount_reais' => 1200,
                'due_date' => '2026-08-15',
                'status' => FinancePayableStatus::Pending->value,
                'is_recurring' => true,
                'recurring_months' => 12,
            ])
            ->assertRedirect(route('admin.financeiro.contas-a-pagar.index'));

        $this->assertDatabaseCount('finance_payables', 3);
        $first->refresh();
        $this->assertSame('Aluguel ajustado', $first->title);
        $this->assertSame(120000, $first->amount_cents);
        $this->assertSame('2026-08-15', $first->due_date?->toDateString());
        $this->assertTrue($first->is_recurring);
        $this->assertSame(3, $first->recurring_months);
        $this->assertSame($groupId, $first->recurring_group_id);

        $others = FinancePayable::query()->where('id', '!=', $first->id)->get();
        $this->assertTrue($others->every(fn (FinancePayable $p) => $p->title === 'Aluguel'));
        $this->assertTrue($others->every(fn (FinancePayable $p) => $p->amount_cents === 100000));
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
