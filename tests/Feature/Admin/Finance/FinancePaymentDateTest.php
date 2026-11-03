<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Enums\AdminPermissionModule;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\FinanceBankAccountType;
use App\Enums\FinancePayableStatus;
use App\Enums\FinanceReceivableStatus;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\CompanyNotice;
use App\Models\FinanceAuditLog;
use App\Models\FinanceBankAccount;
use App\Models\FinancePayable;
use App\Models\FinanceReceivable;
use App\Models\User;
use App\Support\Admin\AdminHomeDashboardBuilder;
use App\Support\Finance\FinanceBankAccountBalance;
use App\Support\Finance\FinanceCashflowMetrics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Data de pagamento: informada na baixa e editável depois dela, nas três origens
 * (parcela de venda, conta a receber manual, conta a pagar), com auditoria.
 */
class FinancePaymentDateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FinanceBankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        // 20/08/2026 12:00 em São Paulo: «hoje» fixo para as regras de data futura e de mês.
        Carbon::setTestNow(Carbon::parse('2026-08-20 12:00:00', 'America/Sao_Paulo'));

        $this->admin = User::factory()->superAdmin()->create(['is_owner' => true, 'is_commercial' => true]);
        $this->bank = FinanceBankAccount::query()->create([
            'name' => 'Banco Principal',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- Baixa com data informada ---------------------------------------------------------

    public function test_installment_payment_uses_the_informed_date(): void
    {
        $installment = $this->pendingInstallment();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => '2026-08-10',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-10 00:00:00', $installment->fresh()->paid_at->toDateTimeString());
        $this->assertSame(0, FinanceAuditLog::query()->count(), 'a primeira baixa não é uma correção');
    }

    public function test_manual_receivable_payment_uses_the_informed_date_or_today(): void
    {
        $withDate = $this->pendingReceivable();
        $withoutDate = $this->pendingReceivable('Sem data');

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.contas-a-receber.mark-paid', $withDate), [
                'paid_at' => '2026-08-05',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.contas-a-receber.mark-paid', $withoutDate), [
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-05 00:00:00', $withDate->fresh()->paid_at->toDateTimeString());
        $this->assertSame(FinanceReceivableStatus::Paid, $withDate->fresh()->status);
        $this->assertSame('2026-08-20', $withoutDate->fresh()->paid_at->toDateString());
    }

    public function test_payable_payment_uses_the_informed_date(): void
    {
        $payable = $this->pendingPayable();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.contas-a-pagar.mark-paid', $payable), [
                'paid_at' => '2026-08-01',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-01 00:00:00', $payable->fresh()->paid_at->toDateTimeString());
        $this->assertSame(FinancePayableStatus::Paid, $payable->fresh()->status);
    }

    public function test_payment_with_future_date_is_rejected_in_all_three_origins(): void
    {
        $installment = $this->pendingInstallment();
        $receivable = $this->pendingReceivable();
        $payable = $this->pendingPayable();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => '2026-08-21',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasErrors('paid_at');
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.contas-a-receber.mark-paid', $receivable), [
                'paid_at' => '2026-08-21',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasErrors('paid_at');
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.contas-a-pagar.mark-paid', $payable), [
                'paid_at' => '2026-08-21',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasErrors('paid_at');

        $this->assertNull($installment->fresh()->paid_at);
        $this->assertNull($receivable->fresh()->paid_at);
        $this->assertNull($payable->fresh()->paid_at);
    }

    public function test_mark_paid_does_not_overwrite_the_date_of_an_already_paid_entry(): void
    {
        $receivable = $this->paidReceivable('2026-08-03');
        $payable = $this->paidPayable('2026-08-03');

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.contas-a-receber.mark-paid', $receivable), [
                'paid_at' => '2026-08-19',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHas('error');
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.contas-a-pagar.mark-paid', $payable), [
                'paid_at' => '2026-08-19',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHas('error');

        $this->assertSame('2026-08-03', $receivable->fresh()->paid_at->toDateString());
        $this->assertSame('2026-08-03', $payable->fresh()->paid_at->toDateString());
    }

    // ---- Edição da data depois da baixa -----------------------------------------------------

    public function test_paid_installment_date_can_be_edited_and_is_audited(): void
    {
        $installment = $this->paidInstallment('2026-08-10');

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'paid_at' => '2026-07-25',
                'payment_date_reason' => 'Cliente pagou em julho, baixa lançada depois',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $installment->fresh();
        $this->assertSame('2026-07-25 00:00:00', $fresh->paid_at->toDateTimeString());
        $this->assertSame(CommercialSaleInstallment::STATUS_PAGO, $fresh->status);
        $this->assertSame(10_000, $fresh->paid_amount_cents);

        $log = FinanceAuditLog::query()->sole();
        $this->assertSame($installment->getMorphClass(), $log->auditable_type);
        $this->assertSame($installment->id, (int) $log->auditable_id);
        $this->assertSame('paid_at', $log->field);
        $this->assertSame('2026-08-10 00:00:00', $log->old_value);
        $this->assertSame('2026-07-25 00:00:00', $log->new_value);
        $this->assertSame($this->admin->id, (int) $log->user_id);
        $this->assertSame('Cliente pagou em julho, baixa lançada depois', $log->reason);
    }

    public function test_paid_manual_receivable_date_can_be_edited_and_is_audited(): void
    {
        $receivable = $this->paidReceivable('2026-08-10');

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable),
                'paid_at' => '2026-08-02',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-02', $receivable->fresh()->paid_at->toDateString());
        $log = FinanceAuditLog::query()->sole();
        $this->assertSame($receivable->getMorphClass(), $log->auditable_type);
        $this->assertNull($log->reason);
    }

    public function test_paid_payable_date_can_be_edited_and_is_audited(): void
    {
        $payable = $this->paidPayable('2026-08-10');

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload($payable),
                'paid_at' => '2026-08-09',
                'payment_date_reason' => 'Débito caiu um dia antes',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-09', $payable->fresh()->paid_at->toDateString());
        $this->assertSame('Débito caiu um dia antes', FinanceAuditLog::query()->sole()->reason);
    }

    public function test_saving_without_changing_the_date_does_not_audit_nor_move_it(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $receivable = $this->paidReceivable('2026-08-10');

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'paid_at' => '2026-08-10',
                'notes' => 'Só a observação',
            ])
            ->assertSessionHasNoErrors();
        // Sem paid_at no payload: mantém a data (não vira «hoje»).
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), $this->receivablePayload($receivable))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-10', $installment->fresh()->paid_at->toDateString());
        $this->assertSame('Só a observação', $installment->fresh()->notes);
        $this->assertSame('2026-08-10', $receivable->fresh()->paid_at->toDateString());
        $this->assertSame(0, FinanceAuditLog::query()->count());
    }

    public function test_editing_to_a_future_date_is_rejected_in_all_three_origins(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $receivable = $this->paidReceivable('2026-08-10');
        $payable = $this->paidPayable('2026-08-10');

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'paid_at' => '2026-08-21',
            ])
            ->assertSessionHasErrors('paid_at');
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable),
                'paid_at' => '2026-09-01',
            ])
            ->assertSessionHasErrors('paid_at');
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload($payable),
                'paid_at' => '2027-01-01',
            ])
            ->assertSessionHasErrors('paid_at');

        $this->assertSame('2026-08-10', $installment->fresh()->paid_at->toDateString());
        $this->assertSame('2026-08-10', $receivable->fresh()->paid_at->toDateString());
        $this->assertSame('2026-08-10', $payable->fresh()->paid_at->toDateString());
        $this->assertSame(0, FinanceAuditLog::query()->count());
    }

    public function test_past_months_are_not_blocked(): void
    {
        $installment = $this->paidInstallment('2026-08-10');

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'paid_at' => '2025-12-31',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2025-12-31', $installment->fresh()->paid_at->toDateString());
    }

    // ---- Permissões e acesso ---------------------------------------------------------------

    public function test_view_only_admin_gets_403_when_editing_the_date(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $receivable = $this->paidReceivable('2026-08-10');
        $payable = $this->paidPayable('2026-08-10');

        $viewer = $this->adminWith([
            [AdminPermissionModule::FinanceiroContasAReceber, PermissionAction::View],
            [AdminPermissionModule::FinanceiroContasAPagar, PermissionAction::View],
        ]);

        $this->actingAs($viewer)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'paid_at' => '2026-08-01',
            ])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable),
                'paid_at' => '2026-08-01',
            ])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload($payable),
                'paid_at' => '2026-08-01',
            ])
            ->assertForbidden();

        $this->assertSame('2026-08-10', $installment->fresh()->paid_at->toDateString());
        $this->assertSame(0, FinanceAuditLog::query()->count());
    }

    public function test_existing_edit_permission_is_enough_to_change_the_date(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $editor = $this->adminWith([[AdminPermissionModule::FinanceiroContasAReceber, PermissionAction::Edit]]);

        $this->actingAs($editor)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'paid_at' => '2026-08-01',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-01', $installment->fresh()->paid_at->toDateString());
        $this->assertSame($editor->id, (int) FinanceAuditLog::query()->sole()->user_id);
    }

    public function test_edit_permission_on_one_module_does_not_reach_the_other(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $receivable = $this->paidReceivable('2026-08-10');
        $payable = $this->paidPayable('2026-08-10');

        $payablesEditor = $this->adminWith([[AdminPermissionModule::FinanceiroContasAPagar, PermissionAction::Edit]]);
        $this->actingAs($payablesEditor)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'paid_at' => '2026-08-01',
            ])
            ->assertForbidden();
        $this->actingAs($payablesEditor)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable),
                'paid_at' => '2026-08-01',
            ])
            ->assertForbidden();

        $receivablesEditor = $this->adminWith([[AdminPermissionModule::FinanceiroContasAReceber, PermissionAction::Edit]]);
        $this->actingAs($receivablesEditor)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload($payable),
                'paid_at' => '2026-08-01',
            ])
            ->assertForbidden();

        $this->assertSame(0, FinanceAuditLog::query()->count());
    }

    public function test_company_users_cannot_reach_finance_entries_by_id(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $receivable = $this->paidReceivable('2026-08-10');
        $payable = $this->paidPayable('2026-08-10');
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'paid_at' => '2026-08-01',
            ])
            ->assertForbidden();
        $this->actingAs($companyAdmin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => '2026-08-01',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertForbidden();
        $this->actingAs($companyAdmin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable),
                'paid_at' => '2026-08-01',
            ])
            ->assertForbidden();
        $this->actingAs($companyAdmin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload($payable),
                'paid_at' => '2026-08-01',
            ])
            ->assertForbidden();

        $this->assertSame('2026-08-10', $installment->fresh()->paid_at->toDateString());
        $this->assertSame(0, FinanceAuditLog::query()->count());
    }

    public function test_editing_one_entry_by_id_never_touches_another_origin_with_the_same_id(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $receivable = $this->paidReceivable('2026-08-10');
        $payable = $this->paidPayable('2026-08-10');
        $this->assertSame($installment->id, $receivable->id);
        $this->assertSame($receivable->id, $payable->id);

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable),
                'paid_at' => '2026-08-01',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-01', $receivable->fresh()->paid_at->toDateString());
        $this->assertSame('2026-08-10', $installment->fresh()->paid_at->toDateString());
        $this->assertSame('2026-08-10', $payable->fresh()->paid_at->toDateString());

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', 999_999), [
                ...$this->receivablePayload($receivable),
                'paid_at' => '2026-08-01',
            ])
            ->assertNotFound();
    }

    // ---- Efeitos colaterais -----------------------------------------------------------------

    public function test_changing_the_month_moves_received_totals_and_home_goal(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $metrics = app(FinanceCashflowMetrics::class);
        $august = [Carbon::parse('2026-08-01')->startOfMonth(), Carbon::parse('2026-08-01')->endOfMonth()];
        $july = [Carbon::parse('2026-07-01')->startOfMonth(), Carbon::parse('2026-07-01')->endOfMonth()];

        $this->assertSame(10_000, $metrics->receivedBetweenCents(...$august));
        $this->assertSame(0, $metrics->receivedBetweenCents(...$july));
        $this->assertSame(10_000, app(AdminHomeDashboardBuilder::class)->build()['monthly_goal']['current_cents']);

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'paid_at' => '2026-07-31',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $metrics->receivedBetweenCents(...$august));
        $this->assertSame(10_000, $metrics->receivedBetweenCents(...$july));
        $this->assertSame(10_000, $metrics->receivedInPeriodCents(...$july));
        $home = app(AdminHomeDashboardBuilder::class)->build();
        $this->assertSame(0, $home['monthly_goal']['current_cents']);
        $this->assertSame(0, $home['finance']['received_cents']);
    }

    public function test_the_last_day_of_the_month_stays_in_that_month(): void
    {
        $installment = $this->pendingInstallment();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => '2026-07-31',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasNoErrors();

        $metrics = app(FinanceCashflowMetrics::class);
        $this->assertSame(10_000, $metrics->receivedBetweenCents(
            Carbon::parse('2026-07-01')->startOfMonth(),
            Carbon::parse('2026-07-01')->endOfMonth(),
        ));
        $this->assertSame(0, $metrics->receivedBetweenCents(
            Carbon::parse('2026-08-01')->startOfMonth(),
            Carbon::parse('2026-08-01')->endOfMonth(),
        ));
    }

    public function test_editing_the_date_keeps_balance_receipt_and_does_not_resend_the_paid_notice(): void
    {
        Storage::fake('local');
        $installment = $this->pendingInstallment();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => '2026-08-10',
                'bank_account_id' => $this->bank->id,
                'receipt' => UploadedFile::fake()->create('comprovante.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $receiptPath = $installment->fresh()->receipt_path;
        $this->assertNotNull($receiptPath);
        Storage::disk('local')->assertExists($receiptPath);
        $this->assertSame(1, $this->paidNoticesFor($installment));
        $balance = app(FinanceBankAccountBalance::class)->currentBalanceCents($this->bank);
        $this->assertSame(10_000, $balance);

        // Fora da janela de deduplicação dos avisos, para o teste não passar por acaso.
        Carbon::setTestNow(now()->addHour());

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment->fresh()),
                'paid_at' => '2026-08-01',
            ])
            ->assertSessionHasNoErrors();
        // Pelo modal de recebimento, já paga e sem arquivo novo.
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => '2026-08-02',
                'bank_account_id' => $this->bank->id,
            ])
            ->assertSessionHasNoErrors();

        $fresh = $installment->fresh();
        $this->assertSame('2026-08-02', $fresh->paid_at->toDateString());
        $this->assertSame($receiptPath, $fresh->receipt_path);
        Storage::disk('local')->assertExists($receiptPath);
        $this->assertSame(1, $this->paidNoticesFor($installment));
        $this->assertSame($balance, app(FinanceBankAccountBalance::class)->currentBalanceCents($this->bank));
        $this->assertSame(2, FinanceAuditLog::query()->count());
    }

    // ---- Pago sem conta bancária (legado) -----------------------------------------------------

    public function test_paid_entries_without_bank_account_can_have_only_the_date_edited(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $installment->forceFill(['bank_account_id' => null])->save();
        $receivable = $this->paidReceivable('2026-08-10');
        $receivable->forceFill(['bank_account_id' => null])->save();
        $payable = $this->paidPayable('2026-08-10');
        $payable->forceFill(['bank_account_id' => null])->save();

        // Modal de edição da parcela e de «Receber» (já paga), sem conta.
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment->fresh()),
                'bank_account_id' => '',
                'paid_at' => '2026-08-01',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => '2026-08-02',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable->fresh()),
                'bank_account_id' => '',
                'paid_at' => '2026-08-03',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload($payable->fresh()),
                'bank_account_id' => '',
                'paid_at' => '2026-08-04',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-02', $installment->fresh()->paid_at->toDateString());
        $this->assertNull($installment->fresh()->bank_account_id);
        $this->assertSame(CommercialSaleInstallment::STATUS_PAGO, $installment->fresh()->status);
        $this->assertSame('2026-08-03', $receivable->fresh()->paid_at->toDateString());
        $this->assertNull($receivable->fresh()->bank_account_id);
        $this->assertSame('2026-08-04', $payable->fresh()->paid_at->toDateString());
        $this->assertNull($payable->fresh()->bank_account_id);
        $this->assertSame(4, FinanceAuditLog::query()->count());
    }

    public function test_paid_entry_keeps_its_bank_account_when_the_form_sends_none(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $receivable = $this->paidReceivable('2026-08-10');
        $payable = $this->paidPayable('2026-08-10');

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'bank_account_id' => '',
                'paid_at' => '2026-08-01',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable),
                'bank_account_id' => '',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload($payable),
                'bank_account_id' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->bank->id, $installment->fresh()->bank_account_id);
        $this->assertSame($this->bank->id, $receivable->fresh()->bank_account_id);
        $this->assertSame($this->bank->id, $payable->fresh()->bank_account_id);
    }

    public function test_a_new_payment_still_requires_the_bank_account(): void
    {
        $installment = $this->pendingInstallment();
        $receivable = $this->pendingReceivable();
        $payable = $this->pendingPayable();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => '2026-08-10',
            ])
            ->assertSessionHasErrors('bank_account_id');
        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment),
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'bank_account_id' => '',
                'paid_at' => '2026-08-10',
            ])
            ->assertSessionHasErrors('bank_account_id');
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable),
                'status' => 'paid',
                'paid_at' => '2026-08-10',
            ])
            ->assertSessionHasErrors('bank_account_id');
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload($payable),
                'status' => 'paid',
                'paid_at' => '2026-08-10',
            ])
            ->assertSessionHasErrors('bank_account_id');

        $this->assertSame(CommercialSaleInstallment::STATUS_PENDENTE, $installment->fresh()->status);
        $this->assertSame(FinanceReceivableStatus::Pending, $receivable->fresh()->status);
        $this->assertSame(FinancePayableStatus::Pending, $payable->fresh()->status);
    }

    // ---- Parcela cancelada -------------------------------------------------------------------

    public function test_cancelled_installment_cannot_be_paid_through_the_payment_endpoint(): void
    {
        Storage::fake('local');
        [$sale, $cancelled] = $this->saleWithPaidAndCancelledInstallments();

        $this->actingAs($this->admin)
            ->from(route('admin.financeiro.contas-a-receber.index'))
            ->patch(route('admin.financeiro.parcelas.pagamento', $cancelled), [
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'paid_at' => '2026-08-10',
                'bank_account_id' => $this->bank->id,
                'receipt' => UploadedFile::fake()->create('comprovante.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.financeiro.contas-a-receber.index'))
            ->assertSessionHas('error', 'Não é possível receber uma parcela cancelada.');

        $fresh = $cancelled->fresh();
        $this->assertSame(CommercialSaleInstallment::STATUS_CANCELADO, $fresh->status);
        $this->assertNull($fresh->paid_at);
        $this->assertNull($fresh->receipt_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(10_000, (int) $sale->fresh()->total_cents);
        $this->assertSame(CommercialSale::STATUS_QUITADA, $sale->fresh()->status);
        $this->assertSame(0, $this->paidNoticesFor($cancelled));
        $this->assertSame(0, FinanceAuditLog::query()->count());
    }

    public function test_cancelled_installment_is_reactivated_and_paid_through_the_edit(): void
    {
        [$sale, $cancelled] = $this->saleWithPaidAndCancelledInstallments();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $cancelled), [
                ...$this->installmentPayload($cancelled),
                'status' => CommercialSaleInstallment::STATUS_PAGO,
                'bank_account_id' => $this->bank->id,
                'paid_at' => '2026-08-12',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $cancelled->fresh();
        $this->assertSame(CommercialSaleInstallment::STATUS_PAGO, $fresh->status);
        $this->assertSame('2026-08-12', $fresh->paid_at->toDateString());
        $this->assertSame(20_000, (int) $sale->fresh()->total_cents);
        $this->assertSame(CommercialSale::STATUS_QUITADA, $sale->fresh()->status);
    }

    public function test_sale_total_sync_runs_in_the_same_transaction_as_the_status_change(): void
    {
        [$sale, $cancelled] = $this->saleWithPaidAndCancelledInstallments();

        // Falha ao gravar o novo total: a reativação da parcela tem de ser desfeita junto.
        CommercialSale::updating(function (CommercialSale $s): void {
            if ($s->isDirty('total_cents')) {
                throw new \RuntimeException('falha simulada ao gravar o total');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin)
                ->patch(route('admin.financeiro.parcelas.pagamento', $cancelled), [
                    'status' => CommercialSaleInstallment::STATUS_PENDENTE,
                ]);
            $this->fail('a falha simulada deveria interromper a requisição');
        } catch (\RuntimeException $e) {
            $this->assertSame('falha simulada ao gravar o total', $e->getMessage());
        }

        $this->assertSame(CommercialSaleInstallment::STATUS_CANCELADO, $cancelled->fresh()->status);
        $this->assertSame(10_000, (int) $sale->fresh()->total_cents);
        $this->assertSame(CommercialSale::STATUS_QUITADA, $sale->fresh()->status);
    }

    // ---- Série recorrente criada como paga ----------------------------------------------------

    public function test_recurring_series_created_as_paid_uses_the_informed_payment_date(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                ...$this->newPayablePayload(),
                'paid_at' => '2026-08-15',
            ])
            ->assertSessionHasNoErrors();

        $series = FinancePayable::query()->orderBy('recurring_index')->get();
        $this->assertCount(3, $series);
        foreach ($series as $payable) {
            $this->assertSame(FinancePayableStatus::Paid, $payable->status);
            $this->assertSame('2026-08-15 00:00:00', $payable->paid_at?->toDateTimeString());
            $this->assertSame(3_000, $payable->paid_amount_cents);
            $this->assertSame($this->bank->id, $payable->bank_account_id);
        }
    }

    public function test_recurring_series_created_as_paid_without_date_uses_each_due_date_capped_at_today(): void
    {
        // Hoje = 20/08/2026; vencimentos 10/07 … 10/11: os futuros ficam com paid_at = hoje.
        $this->actingAs($this->admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                ...$this->newPayablePayload(),
                'due_date' => '2026-07-10',
                'recurring_months' => 5,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['2026-07-10', '2026-08-10', '2026-08-20', '2026-08-20', '2026-08-20'],
            FinancePayable::query()->orderBy('recurring_index')->get()
                ->map(fn (FinancePayable $p) => $p->paid_at?->toDateString())
                ->all(),
        );
        $this->assertSame(0, FinancePayable::query()->where('status', 'paid')->whereNull('paid_at')->count());
        $this->assertSame(0, FinanceAuditLog::query()->count());
    }

    // ---- Legado: pago sem paid_at ----------------------------------------------------------

    public function test_legacy_paid_entries_without_date_get_one_from_the_edit_form_audited_from_null(): void
    {
        $installment = $this->paidInstallment('2026-08-10');
        $installment->forceFill(['paid_at' => null])->save();
        $receivable = $this->paidReceivable('2026-08-10');
        $receivable->forceFill(['paid_at' => null])->save();
        $payable = $this->paidPayable('2026-08-10');
        $payable->forceFill(['paid_at' => null])->save();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                ...$this->installmentPayload($installment->fresh()),
                'paid_at' => '2026-07-01',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload($receivable->fresh()),
                'paid_at' => '2026-07-02',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload($payable->fresh()),
                'paid_at' => '2026-07-03',
                'payment_date_reason' => 'Saneamento de legado',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-07-01', $installment->fresh()->paid_at?->toDateString());
        $this->assertSame('2026-07-02', $receivable->fresh()->paid_at?->toDateString());
        $this->assertSame('2026-07-03', $payable->fresh()->paid_at?->toDateString());

        $logs = FinanceAuditLog::query()->orderBy('id')->get();
        $this->assertCount(3, $logs);
        $this->assertSame([null, null, null], $logs->pluck('old_value')->all());
        $this->assertSame(
            ['2026-07-01 00:00:00', '2026-07-02 00:00:00', '2026-07-03 00:00:00'],
            $logs->pluck('new_value')->all(),
        );
    }

    public function test_legacy_paid_entry_saved_without_a_date_stays_without_one(): void
    {
        $receivable = $this->paidReceivable('2026-08-10');
        $receivable->forceFill(['paid_at' => null])->save();

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), $this->receivablePayload($receivable->fresh()))
            ->assertSessionHasNoErrors();

        $this->assertNull($receivable->fresh()->paid_at, 'o backend não inventa data');
        $this->assertSame(0, FinanceAuditLog::query()->count());
    }

    // ---- Helpers ----------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function newPayablePayload(): array
    {
        return [
            'title' => 'Software mensal',
            'amount_reais' => '30.00',
            'due_date' => '2026-06-10',
            'status' => 'paid',
            'bank_account_id' => $this->bank->id,
            'is_recurring' => true,
            'recurring_months' => 3,
        ];
    }

    private function sale(): CommercialSale
    {
        return CommercialSale::query()->create([
            'code' => 'VENDA-DATA-'.CommercialSale::query()->count(),
            'client_name' => 'Cliente Data',
            'total_cents' => 10_000,
            'installments_count' => 1,
            'payment_method' => 'pix',
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
            'created_by' => $this->admin->id,
            'seller_id' => $this->admin->id,
        ]);
    }

    /**
     * Venda quitada: parcela 1 paga (R$ 100) + parcela 2 cancelada (R$ 100, fora do total).
     *
     * @return array{0: CommercialSale, 1: CommercialSaleInstallment}
     */
    private function saleWithPaidAndCancelledInstallments(): array
    {
        $sale = $this->sale();
        $sale->update(['installments_count' => 2, 'status' => CommercialSale::STATUS_QUITADA]);
        $sale->installments()->create([
            'number' => 1,
            'amount_cents' => 10_000,
            'due_date' => '2026-07-05',
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PAGO,
            'paid_at' => '2026-07-05 00:00:00',
            'paid_amount_cents' => 10_000,
            'bank_account_id' => $this->bank->id,
        ]);
        $cancelled = $sale->installments()->create([
            'number' => 2,
            'amount_cents' => 10_000,
            'due_date' => '2026-08-05',
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_CANCELADO,
        ]);

        return [$sale->fresh(), $cancelled];
    }

    private function pendingInstallment(): CommercialSaleInstallment
    {
        return $this->sale()->installments()->create([
            'number' => 1,
            'amount_cents' => 10_000,
            'due_date' => '2026-08-05',
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);
    }

    private function paidInstallment(string $day): CommercialSaleInstallment
    {
        return $this->sale()->installments()->create([
            'number' => 1,
            'amount_cents' => 10_000,
            'due_date' => '2026-08-05',
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PAGO,
            'paid_at' => $day.' 00:00:00',
            'paid_amount_cents' => 10_000,
            'bank_account_id' => $this->bank->id,
        ]);
    }

    private function pendingReceivable(string $title = 'Receita manual'): FinanceReceivable
    {
        return FinanceReceivable::query()->create([
            'title' => $title,
            'amount_cents' => 5_000,
            'due_date' => '2026-08-05',
            'status' => FinanceReceivableStatus::Pending,
            'created_by' => $this->admin->id,
        ]);
    }

    private function paidReceivable(string $day): FinanceReceivable
    {
        return FinanceReceivable::query()->create([
            'title' => 'Receita paga',
            'amount_cents' => 5_000,
            'due_date' => '2026-08-05',
            'status' => FinanceReceivableStatus::Paid,
            'paid_at' => $day.' 00:00:00',
            'paid_amount_cents' => 5_000,
            'bank_account_id' => $this->bank->id,
            'created_by' => $this->admin->id,
        ]);
    }

    private function pendingPayable(): FinancePayable
    {
        return FinancePayable::query()->create([
            'title' => 'Aluguel',
            'amount_cents' => 3_000,
            'due_date' => '2026-08-05',
            'status' => FinancePayableStatus::Pending,
            'created_by' => $this->admin->id,
        ]);
    }

    private function paidPayable(string $day): FinancePayable
    {
        return FinancePayable::query()->create([
            'title' => 'Aluguel pago',
            'amount_cents' => 3_000,
            'due_date' => '2026-08-05',
            'status' => FinancePayableStatus::Paid,
            'paid_at' => $day.' 00:00:00',
            'paid_amount_cents' => 3_000,
            'bank_account_id' => $this->bank->id,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function installmentPayload(CommercialSaleInstallment $installment): array
    {
        return [
            'due_date' => $installment->due_date->toDateString(),
            'amount_reais' => number_format($installment->amount_cents / 100, 2, '.', ''),
            'method' => $installment->method,
            'status' => $installment->status,
            'bank_account_id' => $installment->bank_account_id,
            'notes' => $installment->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receivablePayload(FinanceReceivable $receivable): array
    {
        return [
            'title' => $receivable->title,
            'amount_reais' => number_format($receivable->amount_cents / 100, 2, '.', ''),
            'due_date' => $receivable->due_date->toDateString(),
            'status' => $receivable->status->value,
            'bank_account_id' => $receivable->bank_account_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payablePayload(FinancePayable $payable): array
    {
        return [
            'title' => $payable->title,
            'amount_reais' => number_format($payable->amount_cents / 100, 2, '.', ''),
            'due_date' => $payable->due_date->toDateString(),
            'status' => $payable->status->value,
            'bank_account_id' => $payable->bank_account_id,
        ];
    }

    /**
     * @param  list<array{0: AdminPermissionModule, 1: PermissionAction}>  $grants
     */
    private function adminWith(array $grants): User
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        $workspaceId = $user->workspaces()->firstOrFail()->id;

        foreach ($grants as [$module, $action]) {
            AdminUserPermission::query()->create([
                'user_workspace_id' => $workspaceId,
                'module' => $module,
                'action' => $action,
            ]);
        }

        return $user;
    }

    private function paidNoticesFor(CommercialSaleInstallment $installment): int
    {
        return CompanyNotice::query()
            ->where('source_type', 'commercial_sale_installment')
            ->where('source_id', $installment->id)
            ->where('event_kind', CompanyNoticeEventKind::InstallmentPaid->value)
            ->count();
    }
}
