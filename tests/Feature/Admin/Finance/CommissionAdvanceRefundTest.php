<?php

namespace Tests\Feature\Admin\Finance;

use App\Actions\Commercial\RefreshCommissionPayout;
use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\FinanceBankAccountType;
use App\Enums\PermissionAction;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\CommercialCommissionRefund;
use App\Models\CommercialCommissionRefundEntry;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\CompanyNotice;
use App\Models\FinanceAuditLog;
use App\Models\FinanceBankAccount;
use App\Models\User;
use App\Services\Commercial\CommissionPayoutCalculator;
use App\Support\Finance\FinanceBankAccountBalance;
use App\Support\Finance\FinanceCashflowMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regra nova: comissão paga antes do recebimento (só com a permissão «Pagar antes do recebimento»),
 * adiantado em aberto e devolução pela colaboradora (teto abaixo do pago ou cliente que não pagou).
 *
 * Caso de referência: VENDA-2026-0017, R$ 3.829,00 em 2x de R$ 1.914,50, comissão de 5%
 * (teto R$ 191,45), primeira parcela recebida (ganho floor(95,725) = R$ 95,72).
 */
class CommissionAdvanceRefundTest extends TestCase
{
    use RefreshDatabase;

    private const ADVANCE_TEXT = 'Você está pagando R$ 95,73 acima do que o cliente já pagou. '
        .'Se o cliente não pagar, esse valor deverá ser devolvido pela colaboradora.';

    private User $owner;

    /** Usuária com Comissões (ver/criar) e «Pagar antes do recebimento» (criar), como a Suzane. */
    private User $suzane;

    /** Usuária com Comissões (ver/criar), sem a permissão de adiantar. */
    private User $payer;

    private User $seller;

    private FinanceBankAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->suzane = $this->adminWith([
            [AdminPermissionModule::FinanceiroComissoes, PermissionAction::View],
            [AdminPermissionModule::FinanceiroComissoes, PermissionAction::Create],
            [AdminPermissionModule::FinanceiroComissoesAdiantamento, PermissionAction::Create],
        ]);
        $this->payer = $this->adminWith([
            [AdminPermissionModule::FinanceiroComissoes, PermissionAction::View],
            [AdminPermissionModule::FinanceiroComissoes, PermissionAction::Create],
        ]);
        $this->seller = User::factory()->create(['is_commercial' => true, 'name' => 'Karen Cristina']);
        $this->account = FinanceBankAccount::query()->create([
            'name' => 'Conta Comissões',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 100_000,
            'is_active' => true,
        ]);
    }

    public function test_sale_0017_values_including_floor_cent(): void
    {
        $sale = $this->sale0017();
        $commission = $sale->commission;
        $this->assertSame(19_145, (int) $commission->amount_cents);
        $this->receive($sale, 1);

        $this->assertSame(9_572, CommissionPayoutCalculator::earnedOnReceivedCents(191_450, 5));
        $this->pay($this->payer, $commission, '95.72')->assertSessionHasNoErrors();

        $this->actingAs($this->suzane)
            ->get(route('admin.financeiro.comissoes.index', ['search' => 'VENDA-2026-0017']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Commissions/Index')
                ->where('commissions.data.0.amount_cents', 19_145)
                ->where('commissions.data.0.paid_cents', 9_572)
                ->where('commissions.data.0.earned_on_received_cents', 9_572)
                ->where('commissions.data.0.releasable_cents', 0)
                ->where('commissions.data.0.payment_limit_cents', 9_573)
                ->where('commissions.data.0.advance_cents', 0)
                ->where('commissions.data.0.awaiting_receipt_cents', 9_573)
                ->where('commissions.data.0.refund_pending_cents', 0)
                ->where('commissions.data.0.sale.total_cents', 382_900)
                ->where('commissions.data.0.sale.received_cents', 191_450)
                ->where('commissions.data.0.sale.outstanding_cents', 191_450)
                ->has('commissions.data.0.sale.pending_installments', 1)
                ->where('commissions.data.0.sale.pending_installments.0.amount_cents', 191_450)
                ->where('summary.pending_cents', 9_573)
                ->where('summary.releasable_cents', 0)
                ->where('summary.advance_cents', 0)
                ->where('summary.awaiting_receipt_cents', 9_573)
                ->where('can.pay_advance', true)
            );
    }

    public function test_user_with_permission_pays_above_releasable_only_with_confirmation(): void
    {
        $sale = $this->sale0017();
        $commission = $sale->commission;
        $this->receive($sale, 1);

        // Sem confirmação: recusa com o texto do aviso, nada é pago.
        $this->pay($this->suzane, $commission, '191.45')
            ->assertSessionHasErrors(['confirm_advance' => self::ADVANCE_TEXT.' Confirme o adiantamento para continuar.']);
        $this->assertFalse(CommercialCommissionPayment::query()->exists());

        $this->pay($this->suzane, $commission, '191.45', confirm: true, notes: 'Adiantado a pedido da Suzane')
            ->assertSessionHasNoErrors();

        $payment = CommercialCommissionPayment::query()->sole();
        $this->assertSame(19_145, $payment->amount_cents);
        $this->assertSame(9_573, $payment->advance_cents);
        $this->assertSame(19_145, (int) $commission->fresh()->paid_cents);
        $this->assertSame(CommercialCommission::STATUS_PAGA, $commission->fresh()->status);
        $this->assertNotNull($payment->payable, 'Continua gerando a conta a pagar baixada.');
        $this->assertSame(100_000 - 19_145, $this->balance());

        // Auditoria: autor, data, valor e se foi adiantado.
        $log = FinanceAuditLog::query()->where('auditable_id', $payment->id)->sole();
        $this->assertSame(FinanceAuditLog::FIELD_COMMISSION_ADVANCE, $log->field);
        $this->assertSame((string) 9_572, $log->old_value);
        $this->assertSame((string) 19_145, $log->new_value);
        $this->assertSame($this->suzane->id, (int) $log->user_id);
        $this->assertSame('Adiantado a pedido da Suzane', $log->reason);
        $this->assertNotNull($log->created_at);

        // Aviso «comissão paga» continua, com o adiantado.
        $notice = CompanyNotice::query()->where('event_kind', CompanyNoticeEventKind::CommissionPaid->value)->sole();
        $this->assertStringContainsString('Adiantado antes do recebimento: R$ 95,73', $notice->body);

        $this->actingAs($this->suzane)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('commissions.data.0.advance_cents', 9_573)
                ->where('commissions.data.0.chargeable_advance_cents', 9_573)
                ->where('commissions.data.0.payments.0.advance_cents', 9_573)
                ->where('summary.advance_cents', 9_573)
                ->where('summary.advance_count', 1));
    }

    public function test_regular_payment_is_audited_as_not_advanced(): void
    {
        $sale = $this->sale0017();
        $this->receive($sale, 1);
        $this->pay($this->suzane, $sale->commission, '95.72')->assertSessionHasNoErrors();

        $payment = CommercialCommissionPayment::query()->sole();
        $this->assertSame(0, $payment->advance_cents);
        $this->assertSame(
            FinanceAuditLog::FIELD_COMMISSION_PAYMENT,
            FinanceAuditLog::query()->where('auditable_id', $payment->id)->value('field'),
        );
    }

    public function test_user_without_permission_cannot_pay_above_releasable_even_confirming(): void
    {
        $sale = $this->sale0017();
        $this->receive($sale, 1);

        $this->pay($this->payer, $sale->commission, '95.73', confirm: true)->assertSessionHasErrors([
            'amount' => 'O valor liberável agora é R$ 95,72; o restante depende do recebimento da venda.',
        ]);
        $this->assertFalse(CommercialCommissionPayment::query()->exists());

        $this->actingAs($this->payer)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.register_payment', true)
                ->where('can.pay_advance', false)
                ->where('can.assume_loss', false));
    }

    public function test_never_above_ceiling_even_with_permission(): void
    {
        $sale = $this->sale0017();

        $this->pay($this->suzane, $sale->commission, '191.46', confirm: true)->assertSessionHasErrors([
            'amount' => 'O valor não pode passar do saldo da comissão: R$ 191,45.',
        ]);

        // Nada recebido: adianta o teto inteiro com confirmação.
        $this->pay($this->suzane, $sale->commission, '191.45', confirm: true)->assertSessionHasNoErrors();
        $this->pay($this->suzane, $sale->commission, '0.01', confirm: true)->assertSessionHasErrors([
            'amount' => 'O valor não pode passar do saldo da comissão: R$ 0,00.',
        ]);
        $this->assertSame(1, CommercialCommissionPayment::query()->count());
    }

    public function test_advance_decreases_as_client_pays(): void
    {
        $sale = $this->sale([10_000, 10_000, 10_000], percent: 10); // teto R$ 30,00
        $commission = $sale->commission;
        $this->pay($this->suzane, $commission, '30.00', confirm: true)->assertSessionHasNoErrors();
        $this->assertSame(3_000, $this->advance($commission));

        $this->receive($sale, 1);
        $this->assertSame(2_000, $this->advance($commission));

        $this->receive($sale, 2);
        $this->assertSame(1_000, $this->advance($commission));

        $this->receive($sale, 3);
        $this->assertSame(0, $this->advance($commission));
    }

    public function test_charge_refund_when_client_does_not_pay_and_auto_cancel_when_client_pays(): void
    {
        $sale = $this->sale0017();
        $commission = $sale->commission;
        $this->receive($sale, 1);
        $this->pay($this->suzane, $commission, '191.45', confirm: true)->assertSessionHasNoErrors();

        $this->charge($this->suzane, $commission, '50.00', '')->assertSessionHasErrors('justification');
        $this->charge($this->suzane, $commission, '95.74', 'Cliente inadimplente')->assertSessionHasErrors([
            'amount' => 'Informe um valor entre R$ 0,01 e o adiantado em aberto: R$ 95,73.',
        ]);

        $this->charge($this->suzane, $commission, '50.00', 'Cliente não pagou a 2ª parcela')->assertSessionHasNoErrors();
        $refund = CommercialCommissionRefund::query()->sole();
        $this->assertSame(CommercialCommissionRefund::ORIGIN_CLIENT_DEFAULT, $refund->origin);
        $this->assertSame(5_000, $refund->pendingCents());
        $this->assertSame('Cliente não pagou a 2ª parcela', $refund->justification);
        $this->assertSame($this->suzane->id, (int) $refund->requested_by);
        $this->assertSame(1, $this->notices(CompanyNoticeEventKind::CommissionRefundDue));
        $this->assertSame(
            FinanceAuditLog::FIELD_COMMISSION_REFUND_CHARGED,
            FinanceAuditLog::query()
                ->where('auditable_type', $refund->getMorphClass())
                ->where('auditable_id', $refund->id)
                ->value('field'),
        );

        // Só o que ainda não está em cobrança pode ser cobrado.
        $this->charge($this->suzane, $commission, '45.74', 'De novo')->assertSessionHasErrors('amount');

        // O cliente paga: o adiantado zera e a cobrança é cancelada automaticamente.
        $this->receive($sale, 2);
        $refund->refresh();
        $this->assertSame(0, $refund->pendingCents());
        $this->assertSame(5_000, $refund->cancelled_cents);
        $this->assertSame(CommercialCommissionRefund::STATUS_RESOLVED, $refund->status);
        $this->assertSame(
            CommercialCommissionRefundEntry::TYPE_CANCELLED,
            $refund->entries()->value('type'),
        );
    }

    public function test_register_partial_and_total_refund_moves_bank_account_not_cashflow(): void
    {
        [$sale, $commission] = $this->ceilingDropScenario(); // pago R$ 20,00, teto R$ 10,00
        $refund = CommercialCommissionRefund::query()->sole();
        $this->assertSame(1_000, $refund->pendingCents());
        $balanceBefore = $this->balance();
        $receivedBefore = app(FinanceCashflowMetrics::class)->receivedInPeriodCents(null, null);

        $this->returnRefund($this->payer, $commission, $refund, '10.01')->assertSessionHasErrors([
            'amount' => 'Informe um valor entre R$ 0,01 e o pendente da devolução: R$ 10,00.',
        ]);

        $this->returnRefund($this->payer, $commission, $refund, '4.00')->assertSessionHasNoErrors();
        $refund->refresh();
        $this->assertSame(600, $refund->pendingCents());
        $this->assertSame(CommercialCommissionRefund::STATUS_PENDING, $refund->status);
        $this->assertSame($balanceBefore + 400, $this->balance());
        $this->assertSame(1_600, (int) $commission->fresh()->paid_cents);

        $this->returnRefund($this->payer, $commission, $refund, '6.00')->assertSessionHasNoErrors();
        $refund->refresh();
        $this->assertSame(0, $refund->pendingCents());
        $this->assertSame(CommercialCommissionRefund::STATUS_RESOLVED, $refund->status);
        $this->assertSame($balanceBefore + 1_000, $this->balance());
        $this->assertSame(1_000, (int) $commission->fresh()->paid_cents);
        $this->assertSame(1, CommercialCommissionRefund::query()->count(), 'Devolver não cria nova cobrança.');

        // Não entra no fluxo de caixa (recebido no período).
        $this->assertSame($receivedBefore, app(FinanceCashflowMetrics::class)->receivedInPeriodCents(null, null));

        $entries = $refund->entries()->orderBy('id')->get();
        $this->assertSame([400, 600], $entries->pluck('amount_cents')->all());
        $this->assertSame($this->payer->id, (int) $entries[0]->user_id);
        $this->assertSame($this->account->id, (int) $entries[0]->bank_account_id);
        $this->assertSame(2, FinanceAuditLog::query()
            ->where('field', FinanceAuditLog::FIELD_COMMISSION_REFUND_RETURNED)->count());

        // Pagamento com devolução registrada não pode mais ser excluído.
        $payment = $commission->payments()->firstOrFail();
        $this->actingAs($this->owner)
            ->from(route('admin.financeiro.comissoes.index'))
            ->delete(route('admin.financeiro.comissoes.pagamentos.destroy', [$commission, $payment]))
            ->assertSessionHasErrors('payment');
    }

    public function test_assume_loss_requires_justification_and_advance_permission(): void
    {
        [$sale, $commission] = $this->ceilingDropScenario();
        $refund = CommercialCommissionRefund::query()->sole();

        $this->assumeLoss($this->payer, $commission, $refund, '10.00', 'Sem permissão')->assertForbidden();

        $this->assumeLoss($this->suzane, $commission, $refund, '10.00', '')->assertSessionHasErrors('justification');

        $this->assumeLoss($this->suzane, $commission, $refund, '10.00', 'Colaboradora desligada; valor baixo')
            ->assertSessionHasNoErrors();

        $refund->refresh();
        $this->assertSame(0, $refund->pendingCents());
        $this->assertSame(1_000, $refund->written_off_cents);
        $this->assertSame(2_000, (int) $commission->fresh()->paid_cents, 'Perda não muda o pago.');
        $this->assertSame(1, $this->notices(CompanyNoticeEventKind::CommissionLossAssumed));
        $notice = CompanyNotice::query()->where('event_kind', CompanyNoticeEventKind::CommissionLossAssumed->value)->sole();
        $this->assertStringContainsString('R$ 10,00', $notice->body);
        $this->assertStringContainsString('Colaboradora desligada; valor baixo', $notice->body);

        $this->actingAs($this->owner)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.loss_assumed_cents', 1_000)
                ->where('summary.refund_pending_cents', 0)
                ->where('commissions.data.0.loss_assumed', true)
                ->where('commissions.data.0.loss_assumed_cents', 1_000)
                ->where('commissions.data.0.refunds.0.entries.0.type', CommercialCommissionRefundEntry::TYPE_LOSS_ASSUMED)
                ->where('commissions.data.0.refunds.0.entries.0.user', $this->suzane->name));

        $this->actingAs($this->owner)
            ->get(route('admin.financeiro.dashboard', ['period' => 'all']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpis.commissions_loss_assumed_cents', 1_000)
                ->where('kpis.commissions_refund_pending_cents', 0));

        // A perda assumida não volta a virar devolução no próximo recálculo.
        $sale->fresh()->update(['notes' => 'recalcula']);
        app(RefreshCommissionPayout::class)->forSale($sale->fresh());
        $this->assertSame(1, CommercialCommissionRefund::query()->count());
    }

    public function test_refund_routes_respect_permissions_and_commission_scope(): void
    {
        [, $commission] = $this->ceilingDropScenario();
        $refund = CommercialCommissionRefund::query()->sole();
        $viewer = $this->adminWith([[AdminPermissionModule::FinanceiroComissoes, PermissionAction::View]]);

        $this->charge($viewer, $commission, '1.00', 'x')->assertForbidden();
        $this->returnRefund($viewer, $commission, $refund, '1.00')->assertForbidden();
        $this->assumeLoss($viewer, $commission, $refund, '1.00', 'x')->assertForbidden();

        // Devolução de outra comissão: 404.
        $other = $this->sale([10_000])->commission;
        $this->returnRefund($this->owner, $other, $refund, '1.00')->assertNotFound();
    }

    public function test_undo_latest_payment_still_works_and_pending_refund_follows(): void
    {
        [, $commission] = $this->ceilingDropScenario();
        $this->assertSame(1_000, CommercialCommissionRefund::query()->sole()->pendingCents());
        $payment = $commission->payments()->firstOrFail();
        $balanceBefore = $this->balance();

        $this->actingAs($this->owner)
            ->from(route('admin.financeiro.comissoes.index'))
            ->delete(route('admin.financeiro.comissoes.pagamentos.destroy', [$commission, $payment]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, (int) $commission->fresh()->paid_cents);
        $this->assertSame($balanceBefore + 2_000, $this->balance());
        $this->assertSame(0, CommercialCommissionRefund::query()->sole()->pendingCents(), 'Sem pagamento, nada a devolver.');
    }

    /**
     * Venda de R$ 200,00 em 2x, 10%: pago R$ 20,00 com as duas recebidas; a 2ª é cancelada → teto R$ 10,00.
     *
     * @return array{0: CommercialSale, 1: CommercialCommission}
     */
    private function ceilingDropScenario(): array
    {
        $sale = $this->sale([10_000, 10_000]);
        $this->receive($sale, 1);
        $this->receive($sale, 2);
        $this->pay($this->payer, $sale->commission, '20.00')->assertSessionHasNoErrors();

        $second = $sale->installments()->where('number', 2)->firstOrFail();
        $this->actingAs($this->owner)
            ->patch(route('admin.financeiro.parcelas.pagamento', $second), [
                'status' => CommercialSaleInstallment::STATUS_CANCELADO,
            ])
            ->assertSessionHasNoErrors();

        return [$sale->fresh(), $sale->commission->fresh()];
    }

    private function sale0017(): CommercialSale
    {
        return $this->sale([191_450, 191_450], percent: 5, code: 'VENDA-2026-0017');
    }

    /**
     * @param  list<int>  $installmentCents
     */
    private function sale(array $installmentCents, float $percent = 10, ?string $code = null): CommercialSale
    {
        static $seq = 0;
        $seq++;
        $total = array_sum($installmentCents);

        $sale = CommercialSale::query()->create([
            'code' => $code ?? sprintf('VENDA-2026-A%04d', $seq),
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
        $sale->fresh()->recalculateStatus();
    }

    private function pay(User $actor, CommercialCommission $commission, string $amount, bool $confirm = false, ?string $notes = null)
    {
        return $this->actingAs($actor)
            ->from(route('admin.financeiro.comissoes.index'))
            ->post(route('admin.financeiro.comissoes.pagamentos.store', $commission), [
                'amount' => $amount,
                'paid_at' => now()->toDateString(),
                'bank_account_id' => $this->account->id,
                'notes' => $notes,
                'confirm_advance' => $confirm,
            ]);
    }

    private function charge(User $actor, CommercialCommission $commission, string $amount, string $justification)
    {
        return $this->actingAs($actor)
            ->from(route('admin.financeiro.comissoes.index'))
            ->post(route('admin.financeiro.comissoes.devolucoes.store', $commission), [
                'amount' => $amount,
                'justification' => $justification,
            ]);
    }

    private function returnRefund(User $actor, CommercialCommission $commission, CommercialCommissionRefund $refund, string $amount)
    {
        return $this->actingAs($actor)
            ->from(route('admin.financeiro.comissoes.index'))
            ->post(route('admin.financeiro.comissoes.devolucoes.registros.store', [$commission, $refund]), [
                'amount' => $amount,
                'returned_at' => now()->toDateString(),
                'bank_account_id' => $this->account->id,
                'notes' => 'PIX da colaboradora',
            ]);
    }

    private function assumeLoss(User $actor, CommercialCommission $commission, CommercialCommissionRefund $refund, string $amount, string $justification)
    {
        return $this->actingAs($actor)
            ->from(route('admin.financeiro.comissoes.index'))
            ->post(route('admin.financeiro.comissoes.devolucoes.perda.store', [$commission, $refund]), [
                'amount' => $amount,
                'justification' => $justification,
            ]);
    }

    private function advance(CommercialCommission $commission): int
    {
        $fresh = $commission->fresh();
        $sale = $fresh->sale()->firstOrFail();

        return CommissionPayoutCalculator::advanceCents($fresh, $sale, $sale->installments()->get());
    }

    private function balance(): int
    {
        return app(FinanceBankAccountBalance::class)->currentBalanceCents($this->account->fresh());
    }

    private function notices(CompanyNoticeEventKind $kind): int
    {
        return CompanyNotice::query()->where('event_kind', $kind->value)->count();
    }

    /**
     * @param  list<array{0: AdminPermissionModule, 1: PermissionAction}>  $grants
     */
    private function adminWith(array $grants): User
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        app(SyncAdminUserPermissions::class)->execute($user->talentsWorkspace(), array_map(
            fn (array $g) => ['module' => $g[0]->value, 'action' => $g[1]->value],
            $grants,
        ));

        return User::query()->findOrFail($user->id);
    }
}
