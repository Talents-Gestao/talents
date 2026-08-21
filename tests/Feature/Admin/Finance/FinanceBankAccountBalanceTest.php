<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Enums\FinanceBankAccountType;
use App\Enums\FinancePayableStatus;
use App\Enums\FinanceReceivableStatus;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\FinanceBankAccount;
use App\Models\FinancePayable;
use App\Models\FinanceReceivable;
use App\Models\User;
use App\Support\Finance\FinanceBankAccountBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceBankAccountBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_increases_on_receivable_and_decreases_on_payable(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $account = FinanceBankAccount::query()->create([
            'name' => 'Conta Caixa',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 100_000,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $balances = app(FinanceBankAccountBalance::class);
        $this->assertSame(100_000, $balances->currentBalanceCents($account));

        $receivable = FinanceReceivable::query()->create([
            'title' => 'Recebimento teste',
            'amount_cents' => 25_000,
            'due_date' => now()->toDateString(),
            'status' => FinanceReceivableStatus::Pending,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.financeiro.contas-a-receber.mark-paid', $receivable), [
                'bank_account_id' => $account->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(125_000, $balances->currentBalanceCents($account->fresh()));

        $payable = FinancePayable::query()->create([
            'title' => 'Pagamento teste',
            'amount_cents' => 40_000,
            'due_date' => now()->toDateString(),
            'status' => FinancePayableStatus::Pending,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.financeiro.contas-a-pagar.mark-paid', $payable), [
                'bank_account_id' => $account->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(85_000, $balances->currentBalanceCents($account->fresh()));

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-bancarias.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/BankAccounts/Index')
                ->where('accounts.data.0.current_balance_cents', 85_000)
                ->where('summary.active_balance_cents', 85_000));
    }

    public function test_sale_installment_payment_updates_bank_balance(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $account = FinanceBankAccount::query()->create([
            'name' => 'Conta Vendas',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 50_000,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-BAL-1',
            'client_name' => 'Cliente Saldo',
            'employee_count' => 5,
            'total_final_cents' => 30_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.converter', $proposal), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->toDateString(),
        ])->assertRedirect();

        $installment = CommercialSale::query()
            ->where('proposal_id', $proposal->id)
            ->firstOrFail()
            ->installments()
            ->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => 'pago',
                'paid_at' => now()->toDateString(),
                'paid_amount_cents' => 30_000,
                'bank_account_id' => $account->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($account->id, (int) $installment->fresh()->bank_account_id);
        $this->assertSame(
            80_000,
            app(FinanceBankAccountBalance::class)->currentBalanceCents($account->fresh()),
        );
    }

    public function test_pending_movements_do_not_affect_balance(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $account = FinanceBankAccount::query()->create([
            'name' => 'Conta Pendente',
            'type' => FinanceBankAccountType::Cash,
            'initial_balance_cents' => 10_000,
            'is_active' => true,
        ]);

        FinanceReceivable::query()->create([
            'title' => 'Ainda pendente',
            'amount_cents' => 99_000,
            'due_date' => now()->toDateString(),
            'status' => FinanceReceivableStatus::Pending,
            'bank_account_id' => $account->id,
        ]);

        FinancePayable::query()->create([
            'title' => 'Ainda a pagar',
            'amount_cents' => 5_000,
            'due_date' => now()->toDateString(),
            'status' => FinancePayableStatus::Pending,
            'bank_account_id' => $account->id,
        ]);

        $this->assertSame(
            10_000,
            app(FinanceBankAccountBalance::class)->currentBalanceCents($account),
        );
    }

    public function test_mark_paid_requires_bank_account(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $payable = FinancePayable::query()->create([
            'title' => 'Sem conta',
            'amount_cents' => 1000,
            'due_date' => now()->toDateString(),
            'status' => FinancePayableStatus::Pending,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-a-pagar.index'))
            ->patch(route('admin.financeiro.contas-a-pagar.mark-paid', $payable), [])
            ->assertRedirect(route('admin.financeiro.contas-a-pagar.index'))
            ->assertSessionHasErrors('bank_account_id');

        $this->assertSame(FinancePayableStatus::Pending, $payable->fresh()->status);
    }
}
