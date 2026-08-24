<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Enums\FinanceBankAccountType;
use App\Models\FinanceBankAccount;
use App\Models\FinanceBankTransfer;
use App\Models\FinancePayable;
use App\Models\FinanceReceivable;
use App\Models\User;
use App\Support\Finance\FinanceBankAccountBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceBankTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_moves_balance_and_keeps_active_total(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $from = FinanceBankAccount::query()->create([
            'name' => 'Conta Origem',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 100_000,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $to = FinanceBankAccount::query()->create([
            'name' => 'Conta Destino',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 50_000,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $balances = app(FinanceBankAccountBalance::class);
        $totalBefore = $balances->activeAccountsTotalCents();
        $this->assertSame(150_000, $totalBefore);

        $this->actingAs($admin)
            ->post(route('admin.financeiro.contas-bancarias.transfer'), [
                'from_bank_account_id' => $from->id,
                'to_bank_account_id' => $to->id,
                'amount_reais' => 25.50,
                'transferred_at' => now()->toDateString(),
                'notes' => 'Ajuste interno',
            ])
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('finance_bank_transfers', [
            'from_bank_account_id' => $from->id,
            'to_bank_account_id' => $to->id,
            'amount_cents' => 2550,
            'notes' => 'Ajuste interno',
        ]);

        $this->assertSame(97_450, $balances->currentBalanceCents($from->fresh()));
        $this->assertSame(52_550, $balances->currentBalanceCents($to->fresh()));
        $this->assertSame($totalBefore, $balances->activeAccountsTotalCents());

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-bancarias.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/BankAccounts/Index')
                ->where('summary.active_balance_cents', 150_000)
                ->has('transferAccounts', 2));
    }

    public function test_transfer_rejects_same_account_inactive_and_zero_amount(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $active = FinanceBankAccount::query()->create([
            'name' => 'Ativa',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 10_000,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $other = FinanceBankAccount::query()->create([
            'name' => 'Outra Ativa',
            'type' => FinanceBankAccountType::Cash,
            'initial_balance_cents' => 5_000,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $inactive = FinanceBankAccount::query()->create([
            'name' => 'Inativa',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 80_000,
            'is_active' => false,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-bancarias.index'))
            ->post(route('admin.financeiro.contas-bancarias.transfer'), [
                'from_bank_account_id' => $active->id,
                'to_bank_account_id' => $active->id,
                'amount_reais' => 10,
            ])
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'))
            ->assertSessionHasErrors('to_bank_account_id');

        $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-bancarias.index'))
            ->post(route('admin.financeiro.contas-bancarias.transfer'), [
                'from_bank_account_id' => $active->id,
                'to_bank_account_id' => $inactive->id,
                'amount_reais' => 10,
            ])
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'))
            ->assertSessionHasErrors('to_bank_account_id');

        $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-bancarias.index'))
            ->post(route('admin.financeiro.contas-bancarias.transfer'), [
                'from_bank_account_id' => $active->id,
                'to_bank_account_id' => $other->id,
                'amount_reais' => 0,
            ])
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'))
            ->assertSessionHasErrors('amount_reais');

        $this->assertSame(0, FinanceBankTransfer::query()->count());
        $this->assertSame(0, FinancePayable::query()->count());
        $this->assertSame(0, FinanceReceivable::query()->count());
    }

    public function test_cannot_delete_bank_account_with_transfers(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $from = FinanceBankAccount::query()->create([
            'name' => 'Origem',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 20_000,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $to = FinanceBankAccount::query()->create([
            'name' => 'Destino',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 0,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        FinanceBankTransfer::query()->create([
            'from_bank_account_id' => $from->id,
            'to_bank_account_id' => $to->id,
            'amount_cents' => 5_000,
            'transferred_at' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-bancarias.index'))
            ->delete(route('admin.financeiro.contas-bancarias.destroy', $from))
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('finance_bank_accounts', ['id' => $from->id]);
    }
}
