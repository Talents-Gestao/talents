<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Actions\Commercial\RefreshCommissionPayout;
use App\Actions\Finance\ChangeFinancePaymentDate;
use App\Actions\Finance\RegisterFinancePayment;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\FinanceBankAccountType;
use App\Enums\FinancePayableStatus;
use App\Http\Controllers\Admin\Finance\PayableController;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\CompanyNotice;
use App\Models\FinanceBankAccount;
use App\Models\FinancePayable;
use App\Models\User;
use App\Services\Commercial\CommissionPayoutCalculator;
use App\Support\Finance\FinanceBankAccountBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fase 2: pagamento de comissão move o caixa (conta a pagar baixada) e perda assumida.
 */
class CommissionCashMovementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private FinanceBankAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->seller = User::factory()->create(['is_commercial' => true, 'name' => 'Vendedora B']);
        $this->account = $this->bankAccount('Conta Principal', 100_000);
    }

    public function test_payment_requires_an_active_bank_account(): void
    {
        $sale = $this->sale([10_000]);
        $this->receive($sale, 1);
        $inactive = $this->bankAccount('Conta Inativa', 0, active: false);

        $this->pay($sale->commission, '5.00', null)->assertSessionHasErrors('bank_account_id');
        $this->pay($sale->commission, '5.00', $inactive->id)->assertSessionHasErrors('bank_account_id');

        $this->assertFalse(CommercialCommissionPayment::query()->exists());
        $this->assertFalse(FinancePayable::query()->exists());
    }

    public function test_payment_creates_paid_payable_and_lowers_bank_balance(): void
    {
        $sale = $this->sale([10_000]);
        $this->receive($sale, 1);

        $this->pay($sale->commission, '6.00')->assertSessionHasNoErrors();

        $payment = CommercialCommissionPayment::query()->firstOrFail();
        $payable = FinancePayable::query()->where('commission_payment_id', $payment->id)->firstOrFail();
        $this->assertSame(FinancePayableStatus::Paid, $payable->status);
        $this->assertSame(600, (int) $payable->paid_amount_cents);
        $this->assertSame($this->account->id, (int) $payable->bank_account_id);
        $this->assertStringContainsString('Vendedora B', $payable->title);
        $this->assertStringContainsString($sale->code, $payable->title);

        $this->assertSame(100_000 - 600, $this->balance($this->account));

        // Aparece em Contas a pagar, travado (sem «Pagar»), com link para a venda.
        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.contas-a-pagar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payables.data.0.id', $payable->id)
                ->where('payables.data.0.can_mark_paid', false)
                ->where('payables.data.0.commission_href', route('admin.financeiro.vendas.show', $sale->id)));

        // Histórico mostra a conta usada.
        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('commissions.data.0.payments.0.bank_account', 'Conta Principal')
                ->where('commissions.data.0.payments.0.is_legacy', false)
                ->has('bankAccounts', 1));
    }

    public function test_deleting_latest_payment_undoes_payable_and_balance(): void
    {
        $sale = $this->sale([10_000]);
        $this->receive($sale, 1);
        $this->pay($sale->commission, '4.00')->assertSessionHasNoErrors();
        $this->pay($sale->commission, '6.00')->assertSessionHasNoErrors();
        $this->assertSame(100_000 - 1_000, $this->balance($this->account));

        $latest = CommercialCommissionPayment::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)
            ->delete(route('admin.financeiro.comissoes.pagamentos.destroy', [$sale->commission, $latest]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(FinancePayable::query()->where('commission_payment_id', $latest->id)->exists());
        $this->assertSame(1, FinancePayable::query()->count());
        $this->assertSame(100_000 - 400, $this->balance($this->account));
        $this->assertSame(400, (int) $sale->commission->fresh()->paid_cents);
    }

    public function test_commission_payable_is_locked_in_payables(): void
    {
        $sale = $this->sale([10_000]);
        $this->receive($sale, 1);
        $this->pay($sale->commission, '10.00')->assertSessionHasNoErrors();
        $payable = FinancePayable::query()->firstOrFail();
        $index = route('admin.financeiro.contas-a-pagar.index');

        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.contas-a-pagar.edit', $payable))
            ->assertRedirect($index)
            ->assertSessionHas('error', PayableController::COMMISSION_ORIGIN_LOCKED_MESSAGE);

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                'title' => 'Alterado',
                'amount_reais' => '1.00',
                'due_date' => now()->toDateString(),
                'status' => FinancePayableStatus::Pending->value,
            ])
            ->assertRedirect($index)
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->delete(route('admin.financeiro.contas-a-pagar.destroy', $payable))
            ->assertRedirect($index)
            ->assertSessionHas('error');

        $fresh = $payable->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(1_000, (int) $fresh->amount_cents);
        $this->assertSame(FinancePayableStatus::Paid, $fresh->status);
        $this->assertSame(100_000 - 1_000, $this->balance($this->account));

        $this->expectException(ValidationException::class);
        app(ChangeFinancePaymentDate::class)->handle($fresh, now()->subDays(3), $this->admin);
    }

    public function test_commission_payable_is_not_recurring_and_other_paths_leave_it_untouched(): void
    {
        $sale = $this->sale([10_000]);
        $this->receive($sale, 1);
        $this->pay($sale->commission, '10.00')->assertSessionHasNoErrors();
        $payable = FinancePayable::query()->firstOrFail();

        $this->assertFalse((bool) $payable->is_recurring);
        $this->assertNull($payable->recurring_months);
        $this->assertNull($payable->recurring_index);
        $this->assertNull($payable->recurring_group_id);

        // Série recorrente nova em Contas a pagar cria só os próprios lançamentos.
        $this->actingAs($this->admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                'title' => 'Aluguel',
                'amount_reais' => '50.00',
                'due_date' => now()->toDateString(),
                'status' => FinancePayableStatus::Pending->value,
                'is_recurring' => true,
                'recurring_months' => 3,
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(4, FinancePayable::query()->count());
        $this->assertSame(1, FinancePayable::query()->whereNotNull('commission_payment_id')->count());
        $this->assertSame(0, FinancePayable::query()->whereNotNull('commission_payment_id')->where('is_recurring', true)->count());

        // Baixa genérica (RegisterFinancePayment) não mexe no lançamento já baixado.
        $other = $this->bankAccount('Outra', 0);
        app(RegisterFinancePayment::class)->handle($payable->fresh(), now()->subDay(), $other->id, actor: $this->admin);
        $this->assertSame($this->account->id, (int) $payable->fresh()->bank_account_id);

        // Conta bancária com o lançamento não pode ser excluída (o saldo do repasse não some).
        $this->actingAs($this->admin)
            ->delete(route('admin.financeiro.contas-bancarias.destroy', $this->account))
            ->assertSessionHas('error');
        $this->assertNotNull($this->account->fresh());
    }

    public function test_mark_paid_is_blocked_for_commission_payable(): void
    {
        $sale = $this->sale([10_000]);
        $this->receive($sale, 1);
        $this->pay($sale->commission, '10.00')->assertSessionHasNoErrors();
        $payable = FinancePayable::query()->firstOrFail();
        $other = $this->bankAccount('Outra', 0);

        $this->actingAs($this->admin)
            ->from(route('admin.financeiro.contas-a-pagar.index'))
            ->patch(route('admin.financeiro.contas-a-pagar.mark-paid', $payable), [
                'bank_account_id' => $other->id,
            ])
            ->assertSessionHas('error');

        $this->assertSame($this->account->id, (int) $payable->fresh()->bank_account_id);
    }

    public function test_legacy_payment_has_no_cash_movement(): void
    {
        $sale = $this->sale([10_000]);
        $this->receive($sale, 1);
        // Como a migração da Fase 1 e os pagamentos anteriores à Fase 2: sem conta a pagar.
        $sale->commission->payments()->create(['amount_cents' => 1_000, 'paid_at' => now()->subMonth()]);
        app(RefreshCommissionPayout::class)->forCommission($sale->commission);

        $migration = require database_path('migrations/2026_11_04_100000_link_commission_payments_to_finance_payables.php');
        $migration->up();

        $this->assertFalse(FinancePayable::query()->exists());
        $this->assertSame(100_000, $this->balance($this->account));

        $payload = CommissionPayoutCalculator::present(
            $sale->commission->fresh(['sale.installments', 'seller', 'payments.payer', 'payments.payable.bankAccount']),
        );
        $this->assertTrue($payload['payments'][0]['is_legacy']);
        $this->assertNull($payload['payments'][0]['bank_account']);
    }

    public function test_loss_assumed_is_flagged_and_notified_once(): void
    {
        $sale = $this->sale([10_000, 10_000]);
        $this->receive($sale, 1);
        $this->receive($sale, 2);
        $this->pay($sale->commission, '20.00')->assertSessionHasNoErrors();

        $this->cancelInstallment($sale, 2);

        $commission = $sale->commission->fresh();
        $this->assertSame(CommercialCommission::STATUS_PAGA, $commission->status);
        $this->assertNotNull($commission->loss_notified_at);
        $this->assertSame(1, $this->lossNotices());
        $notice = CompanyNotice::query()->where('event_kind', CompanyNoticeEventKind::CommissionLossAssumed->value)->firstOrFail();
        $this->assertStringContainsString($sale->code, $notice->body);
        $this->assertStringContainsString('Vendedora B', $notice->body);
        $this->assertStringContainsString('R$ 10,00', $notice->body);

        // Recalcular de novo (outra mudança na venda) não repete o aviso.
        app(RefreshCommissionPayout::class)->forSale($sale->fresh());
        $sale->fresh()->update(['total_cents' => 10_001]);
        $this->assertSame(1, $this->lossNotices());

        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.dashboard', ['period' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->where('kpis.commissions_loss_assumed_cents', 1_000));

        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.loss_assumed_cents', 1_000)
                ->where('commissions.data.0.loss_assumed_cents', 1_000));
    }

    public function test_loss_notice_rearms_after_commission_returns_within_ceiling(): void
    {
        $sale = $this->sale([10_000, 10_000]);
        $this->receive($sale, 1);
        $this->receive($sale, 2);
        $this->pay($sale->commission, '20.00')->assertSessionHasNoErrors();

        $this->cancelInstallment($sale, 2);
        $this->assertSame(1, $this->lossNotices());

        // Parcela reativada: volta ao teto, aviso «rearma».
        $second = $sale->installments()->where('number', 2)->firstOrFail();
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $second), ['status' => CommercialSaleInstallment::STATUS_PENDENTE])
            ->assertSessionHasNoErrors();
        $this->assertNull($sale->commission->fresh()->loss_notified_at);

        $this->cancelInstallment($sale, 2);
        $this->assertSame(2, $this->lossNotices());
    }

    public function test_pending_filter_includes_a_pagar_and_parcial(): void
    {
        $open = $this->sale([10_000]);

        $partial = $this->sale([10_000, 10_000]);
        $this->receive($partial, 1);
        $this->pay($partial->commission, '5.00')->assertSessionHasNoErrors();

        $paid = $this->sale([10_000]);
        $this->receive($paid, 1);
        $this->pay($paid->commission, '10.00')->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.comissoes.index', ['status' => 'pendentes']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('commissions.data', 2)
                ->where('commissions.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all()
                    === collect([$open->commission->id, $partial->commission->id])->sort()->values()->all())
                ->where('statusOptions.pendentes', 'Pendentes (a pagar + parcial)'));
    }

    public function test_recalculate_command_refreshes_ceiling_after_query_builder_total_update(): void
    {
        // Legado: parcela cancelada ainda somada no total; comissão desalinhada.
        $sale = $this->sale([10_000, 10_000]);
        CommercialSaleInstallment::query()->where('sale_id', $sale->id)->where('number', 2)
            ->update(['status' => CommercialSaleInstallment::STATUS_CANCELADO]);
        $this->assertSame(20_000, (int) $sale->fresh()->total_cents);
        $this->assertSame(2_000, (int) $sale->commission->fresh()->amount_cents);

        $this->artisan('commercial:recalculate-sale-statuses', ['--apply' => true])->assertSuccessful();

        $this->assertSame(10_000, (int) $sale->fresh()->total_cents);
        $commission = $sale->commission->fresh();
        $this->assertSame(10_000, (int) $commission->base_cents);
        $this->assertSame(1_000, (int) $commission->amount_cents);
    }

    /**
     * @param  list<int>  $installmentCents
     */
    private function sale(array $installmentCents, float $percent = 10): CommercialSale
    {
        static $seq = 0;
        $seq++;
        $total = array_sum($installmentCents);

        $sale = CommercialSale::query()->create([
            'code' => sprintf('VENDA-2026-F2%03d', $seq),
            'client_name' => "Cliente {$seq}",
            'seller_id' => $this->seller->id,
            'total_cents' => $total,
            'commission_percent' => $percent,
            'commission_cents' => CommissionPayoutCalculator::ceilingCents($total, $percent),
            'payment_method' => 'pix',
            'installments_count' => count($installmentCents),
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
        ]);

        foreach ($installmentCents as $index => $amount) {
            CommercialSaleInstallment::query()->create([
                'sale_id' => $sale->id,
                'number' => $index + 1,
                'amount_cents' => $amount,
                'due_date' => now()->addMonths($index)->toDateString(),
                'method' => 'pix',
                'status' => CommercialSaleInstallment::STATUS_PENDENTE,
            ]);
        }

        CommercialCommission::query()->create([
            'sale_id' => $sale->id,
            'seller_id' => $this->seller->id,
            'base_cents' => $total,
            'percent' => $percent,
            'amount_cents' => CommissionPayoutCalculator::ceilingCents($total, $percent),
            'status' => CommercialCommission::STATUS_A_PAGAR,
        ]);

        return $sale->fresh(['commission', 'installments']);
    }

    private function receive(CommercialSale $sale, int $number): void
    {
        $installment = $sale->installments()->where('number', $number)->firstOrFail();
        $installment->update([
            'status' => CommercialSaleInstallment::STATUS_PAGO,
            'paid_at' => now(),
            'paid_amount_cents' => $installment->amount_cents,
        ]);
        $sale->recalculateStatus();
    }

    private function cancelInstallment(CommercialSale $sale, int $number): void
    {
        $installment = $sale->installments()->where('number', $number)->firstOrFail();
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => CommercialSaleInstallment::STATUS_CANCELADO,
            ])
            ->assertSessionHasNoErrors();
    }

    private function pay(CommercialCommission $commission, string $amount, int|false|null $bankAccountId = false)
    {
        $payload = [
            'amount' => $amount,
            'paid_at' => now()->toDateString(),
        ];
        if ($bankAccountId !== null) {
            $payload['bank_account_id'] = $bankAccountId === false ? $this->account->id : $bankAccountId;
        }

        return $this->actingAs($this->admin)
            ->from(route('admin.financeiro.comissoes.index'))
            ->post(route('admin.financeiro.comissoes.pagamentos.store', $commission), $payload);
    }

    private function bankAccount(string $name, int $initialCents, bool $active = true): FinanceBankAccount
    {
        return FinanceBankAccount::query()->create([
            'name' => $name,
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => $initialCents,
            'is_active' => $active,
        ]);
    }

    private function balance(FinanceBankAccount $account): int
    {
        return app(FinanceBankAccountBalance::class)->currentBalanceCents($account->fresh());
    }

    private function lossNotices(): int
    {
        return CompanyNotice::query()
            ->where('event_kind', CompanyNoticeEventKind::CommissionLossAssumed->value)
            ->count();
    }
}
