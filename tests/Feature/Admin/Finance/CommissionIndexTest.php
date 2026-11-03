<?php

namespace Tests\Feature\Admin\Finance;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\FinanceBankAccountType;
use App\Enums\PermissionAction;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\CompanyNotice;
use App\Models\FinanceBankAccount;
use App\Models\User;
use App\Services\Commercial\CommissionPayoutCalculator;
use App\Services\Commercial\ProposalSaleConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Comissão proporcional ao recebido (Fase 1).
 */
class CommissionIndexTest extends TestCase
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
        $this->seller = User::factory()->create(['is_commercial' => true, 'name' => 'Vendedor A']);
        $this->account = FinanceBankAccount::query()->create([
            'name' => 'Conta Comissões',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 0,
            'is_active' => true,
        ]);
    }

    public function test_commissions_index_lists_open_first_with_totals(): void
    {
        $paidSale = $this->sale([5_000]);
        $this->receive($paidSale, 1);
        $paidCommission = $paidSale->commission;
        $this->pay($paidCommission, '5.00')->assertSessionHasNoErrors();

        $pendingSale = $this->sale([10_000]);
        $pendingCommission = $pendingSale->commission;

        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Commissions/Index')
                ->where('summary.pending_cents', 1_000)
                ->where('summary.pending_count', 1)
                ->where('summary.paid_cents', 500)
                ->where('summary.releasable_cents', 0)
                ->has('commissions.data', 2)
                ->where('commissions.data.0.id', $pendingCommission->id)
                ->where('commissions.data.0.releasable_cents', 0)
                ->where('commissions.data.1.id', $paidCommission->id)
                ->where('commissions.data.1.status', CommercialCommission::STATUS_PAGA)
                ->where('commissions.data.1.paid_cents', 500)
                ->has('commissions.data.1.payments', 1)
                ->where('commissions.data.1.payments.0.can_delete', true)
                ->where('can.register_payment', true)
                ->where('can.delete_payment', true)
            );
    }

    public function test_commission_update_only_changes_notes(): void
    {
        $commission = $this->sale([8_000])->commission;

        $this->actingAs($this->admin)
            ->from(route('admin.financeiro.comissoes.index'))
            ->patch(route('admin.financeiro.comissoes.update', $commission), [
                'status' => CommercialCommission::STATUS_PAGA,
                'notes' => 'Repasse PIX',
            ])
            ->assertRedirect(route('admin.financeiro.comissoes.index'))
            ->assertSessionHas('success');

        $commission->refresh();
        $this->assertSame(CommercialCommission::STATUS_A_PAGAR, $commission->status);
        $this->assertSame('Repasse PIX', $commission->notes);
    }

    public function test_commission_can_be_paid_up_to_releasable_before_sale_is_settled(): void
    {
        // Venda de R$ 200,00 em 2x, 10%: teto R$ 20,00. Recebida 1 parcela: liberável R$ 10,00.
        $sale = $this->sale([10_000, 10_000]);
        $commission = $sale->commission;
        $this->receive($sale, 1);
        $this->assertSame(CommercialSale::STATUS_PARCIAL, $sale->fresh()->status);

        $this->pay($commission, '10.01')->assertSessionHasErrors('amount');
        $this->assertSame(0, (int) $commission->fresh()->paid_cents);

        $this->pay($commission, '10.00')->assertSessionHasNoErrors()->assertSessionHas('success');

        $fresh = $commission->fresh();
        $this->assertSame(1_000, (int) $fresh->paid_cents);
        $this->assertSame(CommercialCommission::STATUS_PARCIAL, $fresh->status);
        $this->assertSame(0, $this->releasable($fresh));
    }

    public function test_payment_is_refused_when_nothing_was_received(): void
    {
        $commission = $this->sale([10_000])->commission;

        $this->pay($commission, '1.00')->assertSessionHasErrors('amount');
        $this->assertFalse(CommercialCommissionPayment::query()->exists());
    }

    public function test_payment_rejects_zero_and_future_date(): void
    {
        $sale = $this->sale([10_000]);
        $this->receive($sale, 1);

        $this->pay($sale->commission, '0')->assertSessionHasErrors('amount');
        $this->pay($sale->commission, '1.00', now()->addDay()->toDateString())->assertSessionHasErrors('paid_at');
    }

    public function test_proportional_release_with_several_installments_and_rounding_at_settlement(): void
    {
        // R$ 100,00 em 3x (33,34 + 33,33 + 33,33), 10%: teto R$ 10,00.
        $sale = $this->sale([3_334, 3_333, 3_333], percent: 10);
        $commission = $sale->commission;
        $this->assertSame(1_000, (int) $commission->amount_cents);

        $this->receive($sale, 1);
        $this->assertSame(333, $this->releasable($commission->fresh())); // floor(3,334)
        $this->pay($commission, '3.33')->assertSessionHasNoErrors();

        $this->receive($sale, 2);
        $this->assertSame(333, $this->releasable($commission->fresh())); // floor(6,667) − 3,33
        $this->pay($commission, '3.33')->assertSessionHasNoErrors();

        $this->receive($sale, 3);
        $this->assertSame(CommercialSale::STATUS_QUITADA, $sale->fresh()->status);
        // Quitada: teto − pago = 10,00 − 6,66 = 3,34 (absorve o centavo do floor).
        $this->assertSame(334, $this->releasable($commission->fresh()));
        $this->pay($commission, '3.34')->assertSessionHasNoErrors();

        $fresh = $commission->fresh();
        $this->assertSame(1_000, (int) $fresh->paid_cents);
        $this->assertSame(CommercialCommission::STATUS_PAGA, $fresh->status);
        $this->assertSame(0, $this->releasable($fresh));
        $this->assertSame(3, $fresh->payments()->count());
    }

    public function test_recurring_sale_releases_month_by_month(): void
    {
        $sale = $this->sale(array_fill(0, 12, 50_000), percent: 5, recurring: true);
        $commission = $sale->commission;
        $this->assertSame(30_000, (int) $commission->amount_cents); // 5% de R$ 6.000,00

        foreach ([1, 2, 3] as $month) {
            $this->receive($sale, $month);
            $this->assertSame(2_500, $this->releasable($commission->fresh()));
            $this->pay($commission, '25.00')->assertSessionHasNoErrors();
        }

        $fresh = $commission->fresh();
        $this->assertSame(7_500, (int) $fresh->paid_cents);
        $this->assertSame(CommercialCommission::STATUS_PARCIAL, $fresh->status);
        $this->assertSame(0, $this->releasable($fresh));
    }

    public function test_cancelling_installment_reduces_ceiling_of_all_commissions(): void
    {
        $sale = $this->sale([10_000, 10_000]);
        $extraUser = User::factory()->create(['commission_percent' => 5]);
        $extra = CommercialCommission::query()->create([
            'sale_id' => $sale->id,
            'seller_id' => $extraUser->id,
            'base_cents' => 20_000,
            'percent' => 5,
            'amount_cents' => 1_000,
            'status' => CommercialCommission::STATUS_A_PAGAR,
        ]);
        $second = $sale->installments()->where('number', 2)->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $second), [
                'status' => CommercialSaleInstallment::STATUS_CANCELADO,
            ])
            ->assertSessionHasNoErrors();

        $main = $sale->commission->fresh();
        $this->assertSame(10_000, (int) $main->base_cents);
        $this->assertSame(1_000, (int) $main->amount_cents);
        $this->assertSame(10_000, (int) $extra->fresh()->base_cents);
        $this->assertSame(500, (int) $extra->fresh()->amount_cents);
    }

    public function test_paid_above_ceiling_is_flagged_without_reversal(): void
    {
        $sale = $this->sale([10_000, 10_000]);
        $commission = $sale->commission;
        $this->receive($sale, 1);
        $this->receive($sale, 2);
        $this->pay($commission, '20.00')->assertSessionHasNoErrors();

        // Parcela já recebida é cancelada depois do repasse: teto cai para R$ 10,00.
        $second = $sale->installments()->where('number', 2)->firstOrFail();
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $second), [
                'status' => CommercialSaleInstallment::STATUS_CANCELADO,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $commission->fresh(['sale.installments', 'seller', 'payments.payer']);
        $this->assertSame(1_000, (int) $fresh->amount_cents);
        $this->assertSame(2_000, (int) $fresh->paid_cents);
        $this->assertSame(CommercialCommission::STATUS_PAGA, $fresh->status);
        $this->assertSame(1, $fresh->payments()->count(), 'Nada é estornado automaticamente.');

        $payload = CommissionPayoutCalculator::present($fresh);
        $this->assertTrue($payload['loss_assumed']);
        $this->assertSame(1_000, $payload['loss_assumed_cents']);
        $this->assertSame(0, $payload['releasable_cents']);

        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.loss_assumed_count', 1)
                ->where('summary.loss_assumed_cents', 1_000)
                ->where('commissions.data.0.loss_assumed', true));
    }

    public function test_multiple_payments_derive_status(): void
    {
        $sale = $this->sale([10_000]);
        $commission = $sale->commission;
        $this->assertSame(CommercialCommission::STATUS_A_PAGAR, $commission->status);
        $this->receive($sale, 1);

        $this->pay($commission, '4.00')->assertSessionHasNoErrors();
        $this->assertSame(CommercialCommission::STATUS_PARCIAL, $commission->fresh()->status);

        $this->pay($commission, '6.00')->assertSessionHasNoErrors();
        $fresh = $commission->fresh();
        $this->assertSame(CommercialCommission::STATUS_PAGA, $fresh->status);
        $this->assertSame(1_000, (int) $fresh->paid_cents);
        $this->assertNotNull($fresh->paid_at);

        $this->pay($commission, '0.01')->assertSessionHasErrors('amount');
    }

    public function test_each_payment_publishes_notice_with_paid_and_remaining(): void
    {
        $sale = $this->sale([10_000]);
        $this->receive($sale, 1);

        $this->pay($sale->commission, '4.00')->assertSessionHasNoErrors();
        $this->pay($sale->commission, '6.00')->assertSessionHasNoErrors();

        $notices = CompanyNotice::query()
            ->where('event_kind', CompanyNoticeEventKind::CommissionPaid->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $notices);
        $this->assertStringContainsString('R$ 4,00', $notices[0]->body);
        $this->assertStringContainsString('Restante: R$ 6,00', $notices[0]->body);
        $this->assertStringContainsString('R$ 6,00', $notices[1]->body);
        $this->assertStringContainsString('Comissão quitada', $notices[1]->body);
    }

    public function test_latest_payment_can_be_deleted_and_older_one_cannot(): void
    {
        $sale = $this->sale([10_000]);
        $commission = $sale->commission;
        $this->receive($sale, 1);
        $this->pay($commission, '4.00')->assertSessionHasNoErrors();
        $this->pay($commission, '6.00')->assertSessionHasNoErrors();
        [$older, $latest] = $commission->payments()->orderBy('id')->get()->all();

        $this->actingAs($this->admin)
            ->from(route('admin.financeiro.comissoes.index'))
            ->delete(route('admin.financeiro.comissoes.pagamentos.destroy', [$commission, $older]))
            ->assertSessionHasErrors('payment');
        $this->assertNull($older->fresh()->deleted_at);

        $this->actingAs($this->admin)
            ->from(route('admin.financeiro.comissoes.index'))
            ->delete(route('admin.financeiro.comissoes.pagamentos.destroy', [$commission, $latest]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $trashed = CommercialCommissionPayment::withTrashed()->findOrFail($latest->id);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertSame($this->admin->id, (int) $trashed->deleted_by);

        $fresh = $commission->fresh();
        $this->assertSame(400, (int) $fresh->paid_cents);
        $this->assertSame(CommercialCommission::STATUS_PARCIAL, $fresh->status);
        $this->assertSame(600, $this->releasable($fresh));
    }

    public function test_payment_of_another_commission_returns_not_found(): void
    {
        $saleA = $this->sale([10_000]);
        $this->receive($saleA, 1);
        $this->pay($saleA->commission, '1.00')->assertSessionHasNoErrors();
        $payment = $saleA->commission->payments()->firstOrFail();
        $other = $this->sale([10_000])->commission;

        $this->actingAs($this->admin)
            ->delete(route('admin.financeiro.comissoes.pagamentos.destroy', [$other, $payment]))
            ->assertNotFound();
    }

    public function test_register_and_delete_require_matrix_permissions(): void
    {
        $sale = $this->sale([10_000]);
        $commission = $sale->commission;
        $this->receive($sale, 1);
        $this->pay($commission, '1.00')->assertSessionHasNoErrors();
        $payment = $commission->payments()->firstOrFail();

        $viewer = User::factory()->superAdmin()->create(['is_owner' => false]);
        $this->grant($viewer, [PermissionAction::View]);

        $this->actingAs($viewer)
            ->get(route('admin.financeiro.comissoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.register_payment', false)
                ->where('can.delete_payment', false)
                ->where('commissions.data.0.payments.0.can_delete', false));
        $this->actingAs($viewer)->post(route('admin.financeiro.comissoes.pagamentos.store', $commission), [
            'amount' => '1.00', 'paid_at' => now()->toDateString(), 'bank_account_id' => $this->account->id,
        ])->assertForbidden();
        $this->actingAs($viewer)
            ->delete(route('admin.financeiro.comissoes.pagamentos.destroy', [$commission, $payment]))
            ->assertForbidden();

        $payer = User::factory()->superAdmin()->create(['is_owner' => false]);
        $this->grant($payer, [PermissionAction::View, PermissionAction::Create]);
        $this->actingAs($payer)->post(route('admin.financeiro.comissoes.pagamentos.store', $commission), [
            'amount' => '1.00', 'paid_at' => now()->toDateString(), 'bank_account_id' => $this->account->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($payer->id, (int) $commission->payments()->latest('id')->value('paid_by'));
        $this->actingAs($payer)
            ->delete(route('admin.financeiro.comissoes.pagamentos.destroy', [$commission, $commission->payments()->latest('id')->first()]))
            ->assertForbidden();

        $deleter = User::factory()->superAdmin()->create(['is_owner' => false]);
        $this->grant($deleter, [PermissionAction::View, PermissionAction::Delete]);
        $this->actingAs($deleter)
            ->delete(route('admin.financeiro.comissoes.pagamentos.destroy', [$commission, $commission->payments()->latest('id')->first()]))
            ->assertSessionHasNoErrors();
        $this->assertSame(100, (int) $commission->fresh()->paid_cents);
    }

    public function test_finance_dashboard_kpi_uses_remaining_and_releasable(): void
    {
        $sale = $this->sale([10_000, 10_000]);
        $this->receive($sale, 1);
        $this->pay($sale->commission, '4.00')->assertSessionHasNoErrors();

        // Teto R$ 20,00; pago R$ 4,00 → pendente R$ 16,00; liberável R$ 10,00 − 4,00 = R$ 6,00.
        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.dashboard', ['period' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance/Dashboard')
                ->where('kpis.commissions_pending_cents', 1_600)
                ->where('kpis.commissions_releasable_cents', 600));
    }

    public function test_proposal_conversion_creates_payable_commission(): void
    {
        $proposal = CommercialProposal::create([
            'code' => 'PROP-2026-'.fake()->unique()->numerify('####'),
            'client_name' => 'Cliente Teste',
            'employee_count' => 10,
            'seller_id' => $this->seller->id,
            'total_final_cents' => 8_000,
            'commission_percent' => 10,
            'commission_cents' => 800,
            'is_closed' => true,
            'closed_at' => now(),
        ]);
        $sale = app(ProposalSaleConversionService::class)->convert($proposal, [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->addDays(10)->toDateString(),
        ]);

        $commission = $sale->commission;
        $this->assertSame(0, (int) $commission->fresh()->paid_cents);
        $this->receive($sale, 1);
        $this->pay($commission, '8.00')->assertSessionHasNoErrors();
        $this->assertSame(CommercialCommission::STATUS_PAGA, $commission->fresh()->status);
    }

    /**
     * Venda com parcelas e comissão do vendedor (teto = percent × total).
     *
     * @param  list<int>  $installmentCents
     */
    private function sale(array $installmentCents, float $percent = 10, bool $recurring = false): CommercialSale
    {
        static $seq = 0;
        $seq++;
        $total = array_sum($installmentCents);

        $sale = CommercialSale::query()->create([
            'code' => sprintf('VENDA-2026-T%04d', $seq),
            'client_name' => "Cliente {$seq}",
            'seller_id' => $this->seller->id,
            'total_cents' => $total,
            'commission_percent' => $percent,
            'commission_cents' => CommissionPayoutCalculator::ceilingCents($total, $percent),
            'payment_method' => 'pix',
            'installments_count' => count($installmentCents),
            'is_recurring' => $recurring,
            'recurring_months' => $recurring ? count($installmentCents) : null,
            'recurring_monthly_cents' => $recurring ? $installmentCents[0] : null,
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

    private function pay(CommercialCommission $commission, string $amount, ?string $paidAt = null)
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.financeiro.comissoes.index'))
            ->post(route('admin.financeiro.comissoes.pagamentos.store', $commission), [
                'amount' => $amount,
                'paid_at' => $paidAt ?? now()->toDateString(),
                'bank_account_id' => $this->account->id,
                'notes' => 'PIX',
            ]);
    }

    private function releasable(CommercialCommission $commission): int
    {
        $sale = $commission->sale()->firstOrFail();

        return CommissionPayoutCalculator::releasableCents($commission, $sale, $sale->installments()->get());
    }

    /**
     * @param  list<PermissionAction>  $actions
     */
    private function grant(User &$user, array $actions): void
    {
        app(SyncAdminUserPermissions::class)->execute($user->talentsWorkspace(), array_map(
            fn (PermissionAction $action) => [
                'module' => AdminPermissionModule::FinanceiroComissoes->value,
                'action' => $action->value,
            ],
            $actions,
        ));

        $user = User::query()->findOrFail($user->id);
    }
}
