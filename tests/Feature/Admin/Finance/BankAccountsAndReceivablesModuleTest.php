<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Enums\FinanceBankAccountType;
use App\Enums\FinanceReceivableStatus;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\FinanceBankAccount;
use App\Models\FinanceReceivable;
use App\Models\User;
use App\Support\Admin\AdminHomeDashboardBuilder;
use App\Support\Finance\FinanceCashflowMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BankAccountsAndReceivablesModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_bank_account(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->post(route('admin.financeiro.contas-bancarias.store'), [
                'name' => 'Itaú PJ',
                'bank_name' => 'Itaú',
                'agency' => '1234',
                'account_number' => '56789-0',
                'type' => FinanceBankAccountType::Checking->value,
                'initial_balance_reais' => 1500.5,
                'initial_balance_at' => '2026-08-01',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'));

        $account = FinanceBankAccount::query()->first();
        $this->assertNotNull($account);
        $this->assertSame(150050, $account->initial_balance_cents);
        $this->assertSame(FinanceBankAccountType::Checking, $account->type);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-bancarias.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/BankAccounts/Index')
                ->has('accounts.data', 1)
                ->where('summary.active_count', 1)
                ->where('summary.active_balance_cents', 150050)
            );

        $this->actingAs($admin)
            ->put(route('admin.financeiro.contas-bancarias.update', $account), [
                'name' => 'Itaú PJ Principal',
                'bank_name' => 'Itaú',
                'agency' => '1234',
                'account_number' => '56789-0',
                'type' => FinanceBankAccountType::Checking->value,
                'initial_balance_reais' => 2000,
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'));

        $this->assertSame('Itaú PJ Principal', $account->fresh()->name);
        $this->assertSame(200000, $account->fresh()->initial_balance_cents);

        $this->actingAs($admin)
            ->delete(route('admin.financeiro.contas-bancarias.destroy', $account))
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'));

        $this->assertDatabaseMissing('finance_bank_accounts', ['id' => $account->id]);
    }

    public function test_cannot_delete_bank_account_with_receivables(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $account = FinanceBankAccount::query()->create([
            'name' => 'Caixa',
            'type' => FinanceBankAccountType::Cash,
            'initial_balance_cents' => 0,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        FinanceReceivable::query()->create([
            'title' => 'Aluguel sala',
            'amount_cents' => 50000,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => FinanceReceivableStatus::Pending,
            'bank_account_id' => $account->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-bancarias.index'))
            ->delete(route('admin.financeiro.contas-bancarias.destroy', $account))
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('finance_bank_accounts', ['id' => $account->id]);
    }

    public function test_admin_can_crud_manual_receivable_and_mark_paid(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $account = FinanceBankAccount::query()->create([
            'name' => 'Conta Destino',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 10000,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.financeiro.contas-a-receber.store'), [
                'title' => 'Consultoria pontual',
                'payer_name' => 'Cliente Avulso',
                'amount_reais' => 350.25,
                'due_date' => now()->addDays(3)->toDateString(),
                'status' => FinanceReceivableStatus::Pending->value,
                'bank_account_id' => $account->id,
                'notes' => 'Fora do funil comercial',
            ])
            ->assertRedirect(route('admin.financeiro.contas-a-receber.index'));

        $receivable = FinanceReceivable::query()->first();
        $this->assertNotNull($receivable);
        $this->assertSame(35025, $receivable->amount_cents);
        $this->assertSame($account->id, $receivable->bank_account_id);

        $this->actingAs($admin)
            ->patch(route('admin.financeiro.contas-a-receber.mark-paid', $receivable), [
                'bank_account_id' => $account->id,
            ])
            ->assertRedirect();

        $receivable->refresh();
        $this->assertSame(FinanceReceivableStatus::Paid, $receivable->status);
        $this->assertSame(35025, $receivable->paid_amount_cents);
        $this->assertNotNull($receivable->paid_at);
    }

    public function test_receivables_index_unifies_sale_installments_and_manuals(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-LEDGER-1',
            'client_name' => 'Cliente Venda',
            'employee_count' => 5,
            'total_final_cents' => 10000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-2026-0100',
            'proposal_id' => $proposal->id,
            'client_name' => 'Cliente Venda',
            'total_cents' => 10000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 1,
            'amount_cents' => 10000,
            'due_date' => now()->addDays(10)->toDateString(),
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);

        FinanceReceivable::query()->create([
            'title' => 'Receita manual',
            'payer_name' => 'Pagador Manual',
            'amount_cents' => 5000,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => FinanceReceivableStatus::Pending,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-a-receber.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Receivables/Index')
                ->has('items.data', 2)
            );

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-a-receber.index', ['origin' => 'manual']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.source', 'manual')
            );

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-a-receber.index', ['origin' => 'sale']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.source', 'sale')
                ->where('items.data.0.can_mark_paid', true)
            );
    }

    public function test_cashflow_metrics_and_dashboards_include_manual_receivables(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        FinanceBankAccount::query()->create([
            'name' => 'Banco KPI',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 80_000,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        FinanceReceivable::query()->create([
            'title' => 'Manual pendente',
            'amount_cents' => 12_000,
            'due_date' => now()->toDateString(),
            'status' => FinanceReceivableStatus::Pending,
            'created_by' => $admin->id,
        ]);

        FinanceReceivable::query()->create([
            'title' => 'Manual pago',
            'amount_cents' => 7_000,
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => FinanceReceivableStatus::Paid,
            'paid_at' => now(),
            'paid_amount_cents' => 7_000,
            'created_by' => $admin->id,
        ]);

        $metrics = app(FinanceCashflowMetrics::class);
        $this->assertSame(12_000, $metrics->toReceiveCents());
        $this->assertSame(7_000, $metrics->receivedBetweenCents(now()->startOfMonth(), now()->endOfMonth()));
        $this->assertSame(80_000, $metrics->activeBankAccountsBalanceCents());

        $home = app(AdminHomeDashboardBuilder::class)->build();
        $this->assertSame(12_000, $home['finance']['to_receive_cents']);
        $this->assertSame(7_000, $home['finance']['received_cents']);
        $this->assertGreaterThanOrEqual(12_000, $home['finance']['receive_this_month_cents']);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Dashboard')
                ->where('kpis.receivable_cents', 12_000)
                ->where('kpis.bank_balance_cents', 80_000)
                ->where('kpis.bank_accounts_count', 1)
            );
    }

    public function test_admin_can_update_sale_installment_from_receivables(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-EDIT-INST',
            'client_name' => 'Cliente Parcela',
            'employee_count' => 3,
            'total_final_cents' => 20_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-2026-0200',
            'proposal_id' => $proposal->id,
            'client_name' => 'Cliente Parcela',
            'total_cents' => 20_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        $installment = CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 1,
            'amount_cents' => 20_000,
            'due_date' => now()->addDays(10)->toDateString(),
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
            'notes' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-a-receber.index'))
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                'due_date' => '2026-09-01',
                'amount_reais' => 250.5,
                'method' => 'boleto',
                'status' => CommercialSaleInstallment::STATUS_PENDENTE,
                'notes' => 'Cobrança ajustada',
            ])
            ->assertRedirect(route('admin.financeiro.contas-a-receber.index'));

        $installment->refresh();
        $this->assertSame('2026-09-01', $installment->due_date?->toDateString());
        $this->assertSame(25050, $installment->amount_cents);
        $this->assertSame(25050, (int) $sale->fresh()->total_cents);
        $this->assertSame('boleto', $installment->method);
        $this->assertSame('Cobrança ajustada', $installment->notes);
    }

    public function test_admin_can_update_sale_installment_from_sale_show(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-2026-0201',
            'client_name' => 'Cliente Show',
            'total_cents' => 10_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        $installment = CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 1,
            'amount_cents' => 10_000,
            'due_date' => now()->addDays(5)->toDateString(),
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.vendas.show', $sale))
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                'due_date' => '2026-10-15',
                'amount_reais' => 120,
                'method' => 'cartao',
                'status' => CommercialSaleInstallment::STATUS_PENDENTE,
                'notes' => 'Ajuste no detalhe',
            ])
            ->assertRedirect(route('admin.financeiro.vendas.show', $sale));

        $installment->refresh();
        $this->assertSame('2026-10-15', $installment->due_date?->toDateString());
        $this->assertSame(12_000, $installment->amount_cents);
        $this->assertSame('cartao', $installment->method);
        $this->assertSame(12_000, (int) $sale->fresh()->total_cents);
    }

    public function test_paid_installment_amount_cannot_change_on_update(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-2026-0202',
            'client_name' => 'Cliente Pago',
            'total_cents' => 15_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_QUITADA,
            'sold_at' => now(),
        ]);

        $account = FinanceBankAccount::query()->create([
            'name' => 'Conta Pago',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 0,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $installment = CommercialSaleInstallment::query()->create([
            'sale_id' => $sale->id,
            'number' => 1,
            'amount_cents' => 15_000,
            'due_date' => now()->toDateString(),
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PAGO,
            'paid_at' => now(),
            'paid_amount_cents' => 15_000,
            'bank_account_id' => $account->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.vendas.show', $sale))
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                'due_date' => now()->addDay()->toDateString(),
                'amount_reais' => 999,
                'method' => 'boleto',
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'bank_account_id' => $account->id,
                'notes' => null,
            ])
            ->assertRedirect(route('admin.financeiro.vendas.show', $sale));

        $installment->refresh();
        $this->assertSame(15_000, $installment->amount_cents);
        $this->assertSame('boleto', $installment->method);
    }

    public function test_admin_can_create_manual_sale_without_proposal(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Sales/Form')
            );

        $response = $this->actingAs($admin)->post(route('admin.financeiro.vendas.store'), [
            'client_name' => 'Cliente Avulso LTDA',
            'client_cnpj' => '12.345.678/0001-99',
            'total_reais' => 1000,
            'commission_percent' => 0,
            'payment_method' => 'pix',
            'installments_count' => 2,
            'first_due_date' => '2026-08-20',
            'notes' => 'Venda manual',
        ]);

        $sale = CommercialSale::query()->whereNull('proposal_id')->where('client_name', 'Cliente Avulso LTDA')->first();
        $this->assertNotNull($sale);
        $this->assertSame(100_000, $sale->total_cents);
        $this->assertSame(2, $sale->installments_count);
        $this->assertCount(2, $sale->installments);
        $this->assertSame('Venda manual', $sale->notes);

        $response->assertRedirect(route('admin.financeiro.vendas.show', $sale));
    }

    public function test_receivables_index_exposes_edit_options_for_modal(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-a-receber.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Receivables/Index')
                ->has('paymentMethods')
                ->has('bankAccounts')
                ->has('installmentMethodOptions')
                ->has('installmentStatusOptions')
                ->has('sortOptions', 2)
                ->where('filters.sort', 'due_date')
            );
    }

    public function test_receivables_index_can_filter_by_pending_and_paid_status(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $pending = FinanceReceivable::query()->create([
            'title' => 'A receber',
            'amount_cents' => 1500,
            'due_date' => '2026-08-10',
            'status' => FinanceReceivableStatus::Pending,
            'created_by' => $admin->id,
        ]);
        $received = FinanceReceivable::query()->create([
            'title' => 'Já recebida',
            'amount_cents' => 2500,
            'due_date' => '2026-08-05',
            'status' => FinanceReceivableStatus::Paid,
            'paid_at' => '2026-08-06 10:00:00',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-a-receber.index', [
                'origin' => 'manual',
                'status' => FinanceReceivableStatus::Pending->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', FinanceReceivableStatus::Pending->value)
                ->has('items.data', 1)
                ->where('items.data.0.receivable_id', $pending->id));

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-a-receber.index', [
                'origin' => 'manual',
                'status' => FinanceReceivableStatus::Paid->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', FinanceReceivableStatus::Paid->value)
                ->has('items.data', 1)
                ->where('items.data.0.receivable_id', $received->id));
    }

    public function test_coming_soon_redirects_to_new_finance_modules(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->get(route('admin.coming-soon.show', 'contas-bancarias'))
            ->assertRedirect(route('admin.financeiro.contas-bancarias.index'));

        $this->actingAs($admin)
            ->get(route('admin.coming-soon.show', 'contas-a-receber'))
            ->assertRedirect(route('admin.financeiro.contas-a-receber.index'));
    }

    public function test_receivables_index_can_sort_by_receipt_date(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $pending = FinanceReceivable::query()->create([
            'title' => 'Pendente com vencimento novo',
            'amount_cents' => 1000,
            'due_date' => '2026-08-20',
            'status' => FinanceReceivableStatus::Pending,
            'created_by' => $admin->id,
        ]);
        $received = FinanceReceivable::query()->create([
            'title' => 'Recebida',
            'amount_cents' => 2000,
            'due_date' => '2026-08-01',
            'status' => FinanceReceivableStatus::Paid,
            'paid_at' => '2026-08-18 10:00:00',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-a-receber.index', ['origin' => 'manual']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.receivable_id', $received->id)
            );

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-a-receber.index', [
                'origin' => 'manual',
                'sort' => 'paid_at',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'paid_at')
                ->where('items.data.0.receivable_id', $received->id)
                ->where('items.data.1.receivable_id', $pending->id)
            );
    }

    public function test_receivables_index_lists_nearest_due_dates_first_for_recurring_sale(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-REC-SORT',
            'client_name' => 'Cliente Recorrente Sort',
            'total_cents' => 120_000,
            'installments_count' => 3,
            'payment_method' => 'pix',
            'status' => CommercialSale::STATUS_ABERTA,
            'is_recurring' => true,
            'recurring_months' => 3,
            'recurring_monthly_cents' => 40_000,
            'sold_at' => now(),
            'created_by' => $admin->id,
            'seller_id' => $admin->id,
        ]);

        $month3 = $sale->installments()->create([
            'number' => 3,
            'amount_cents' => 40_000,
            'due_date' => '2026-10-01',
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);
        $month1 = $sale->installments()->create([
            'number' => 1,
            'amount_cents' => 40_000,
            'due_date' => '2026-08-01',
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);
        $month2 = $sale->installments()->create([
            'number' => 2,
            'amount_cents' => 40_000,
            'due_date' => '2026-09-01',
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.financeiro.contas-a-receber.index', [
                'origin' => 'sale',
                'sort' => 'due_date',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.installment_id', $month1->id)
                ->where('items.data.1.installment_id', $month2->id)
                ->where('items.data.2.installment_id', $month3->id)
            );
    }

    public function test_guest_cannot_access_new_finance_modules(): void
    {
        $this->get(route('admin.financeiro.contas-bancarias.index'))->assertRedirect();
        $this->get(route('admin.financeiro.contas-a-receber.index'))->assertRedirect();
    }
}
