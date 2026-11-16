<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Commercial\ChargeCommissionRefund;
use App\Actions\Commercial\DeleteLatestCommissionPayment;
use App\Actions\Commercial\RegisterCommissionPayment;
use App\Actions\Commercial\ResolveCommissionRefund;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\CommercialCommissionRefund;
use App\Models\FinanceBankAccount;
use App\Models\User;
use App\Services\Commercial\CommissionPayoutCalculator;
use App\Support\Finance\FinancePaymentDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comissões: pagamentos (vários por comissão) e devoluções. Sem a permissão de adiantar, o pagamento
 * fica limitado ao liberável proporcional ao recebido; com ela, ao saldo da comissão (teto − pago).
 * Status é derivado (a_pagar/parcial/paga).
 */
class CommissionController extends Controller
{
    /** Filtro de listagem: a_pagar + parcial (link «Comissões a pagar» do Resumo). */
    public const FILTER_PENDENTES = 'pendentes';

    /** Filtro de listagem: comissões com devolução pendente (atalho do Resumo). */
    public const FILTER_DEVOLUCOES = 'devolucoes';

    public function index(Request $request): Response
    {
        $q = CommercialCommission::query();

        if ($request->filled('search')) {
            $s = (string) $request->string('search');
            $q->whereHas('sale', function ($saleQuery) use ($s) {
                $saleQuery->where(function ($inner) use ($s) {
                    $inner->where('client_name', 'like', '%'.$s.'%')
                        ->orWhere('code', 'like', '%'.$s.'%')
                        ->orWhere('client_cnpj', 'like', '%'.$s.'%');
                });
            });
        }

        if ($request->filled('seller_id')) {
            $q->where('seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === self::FILTER_PENDENTES) {
                $q->whereIn('status', [CommercialCommission::STATUS_A_PAGAR, CommercialCommission::STATUS_PARCIAL]);
            } elseif ($status === self::FILTER_DEVOLUCOES) {
                $q->whereHas('refunds', fn ($r) => $r->where('status', CommercialCommissionRefund::STATUS_PENDING));
            } elseif (array_key_exists($status, CommercialCommission::statusLabels())) {
                $q->where('status', $status);
            }
        }

        $summary = $this->summary(clone $q);

        $can = self::abilities($request->user());

        $commissions = $q
            ->with(self::payoutRelations())
            ->orderByRaw("CASE WHEN status = '".CommercialCommission::STATUS_PAGA."' THEN 1 ELSE 0 END")
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CommercialCommission $c) => CommissionPayoutCalculator::present($c, $can));

        return Inertia::render('Admin/Finance/Commissions/Index', [
            'commissions' => $commissions,
            'summary' => $summary,
            'filters' => $request->only(['search', 'seller_id', 'status']),
            'sellers' => User::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->where('is_commercial', true)
                        ->orWhere('commission_percent', '>', 0);
                })
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'statusOptions' => [
                self::FILTER_PENDENTES => 'Pendentes (a pagar + parcial)',
                self::FILTER_DEVOLUCOES => 'Devoluções pendentes',
                ...CommercialCommission::statusLabels(),
            ],
            'bankAccounts' => self::bankAccountOptions(),
            'can' => $can,
        ]);
    }

    /**
     * Só observações: valor e status vêm dos pagamentos.
     */
    public function update(Request $request, CommercialCommission $commission): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $commission->update(['notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null]);

        return redirect()
            ->back()
            ->with('success', 'Comissão atualizada.');
    }

    public function storePayment(
        Request $request,
        CommercialCommission $commission,
        RegisterCommissionPayment $register,
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => FinancePaymentDate::rules(required: true),
            'bank_account_id' => self::activeBankAccountRules(),
            'notes' => ['nullable', 'string', 'max:2000'],
            'confirm_advance' => ['sometimes', 'boolean'],
        ], [
            ...self::bankAccountMessages(),
            ...FinancePaymentDate::messages(),
            'amount.required' => 'Informe o valor do pagamento.',
            'amount.numeric' => 'Informe um valor válido.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'paid_at.required' => 'Informe a data do pagamento.',
        ]);

        $register->handle(
            $commission,
            self::toCents($data['amount']),
            FinancePaymentDate::toPaidAt($data['paid_at']),
            (int) $data['bank_account_id'],
            $data['notes'] ?? null,
            $request->user(),
            canAdvance: self::canAdvance($request->user()),
            advanceConfirmed: (bool) ($data['confirm_advance'] ?? false),
        );

        return redirect()
            ->back()
            ->with('success', 'Pagamento de comissão registrado.');
    }

    public function destroyPayment(
        Request $request,
        CommercialCommission $commission,
        CommercialCommissionPayment $payment,
        DeleteLatestCommissionPayment $delete,
    ): RedirectResponse {
        $delete->handle($commission, $payment, $request->user());

        return redirect()
            ->back()
            ->with('success', 'Pagamento de comissão excluído.');
    }

    /** «Cobrar devolução»: o cliente não pagou; cobra (parte do) adiantado em aberto. */
    public function chargeRefund(
        Request $request,
        CommercialCommission $commission,
        ChargeCommissionRefund $charge,
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'justification' => ['required', 'string', 'max:2000'],
        ], [
            'amount.required' => 'Informe o valor a devolver.',
            'amount.numeric' => 'Informe um valor válido.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'justification.required' => 'Informe a justificativa da cobrança.',
        ]);

        $charge->handle($commission, self::toCents($data['amount']), $data['justification'], $request->user());

        return redirect()
            ->back()
            ->with('success', 'Devolução cobrada.');
    }

    /** «Registrar devolução»: valor devolvido pela colaboradora entra na conta bancária. */
    public function returnRefund(
        Request $request,
        CommercialCommission $commission,
        CommercialCommissionRefund $refund,
        ResolveCommissionRefund $resolve,
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'returned_at' => FinancePaymentDate::rules(required: true),
            'bank_account_id' => self::activeBankAccountRules(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            ...self::bankAccountMessages(),
            ...FinancePaymentDate::messages('returned_at'),
            'returned_at.required' => 'Informe a data da devolução.',
            'returned_at.before_or_equal' => 'A data da devolução não pode ser futura.',
            'amount.required' => 'Informe o valor devolvido.',
            'amount.numeric' => 'Informe um valor válido.',
            'amount.gt' => 'Informe um valor maior que zero.',
        ]);

        $resolve->returned(
            $refund,
            self::toCents($data['amount']),
            FinancePaymentDate::toPaidAt($data['returned_at']),
            (int) $data['bank_account_id'],
            $data['notes'] ?? null,
            $request->user(),
        );

        return redirect()
            ->back()
            ->with('success', 'Devolução registrada.');
    }

    /** «Assumir como perda»: a empresa assume (parte da) devolução pendente. */
    public function assumeRefundLoss(
        Request $request,
        CommercialCommission $commission,
        CommercialCommissionRefund $refund,
        ResolveCommissionRefund $resolve,
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'justification' => ['required', 'string', 'max:2000'],
        ], [
            'amount.required' => 'Informe o valor assumido como perda.',
            'amount.numeric' => 'Informe um valor válido.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'justification.required' => 'Informe a justificativa para assumir a perda.',
        ]);

        $resolve->assumeLoss($refund, self::toCents($data['amount']), $data['justification'], $request->user());

        return redirect()
            ->back()
            ->with('success', 'Perda assumida pela empresa.');
    }

    /**
     * Relações que CommissionPayoutCalculator::present espera.
     *
     * @return array<int|string, mixed>
     */
    public static function payoutRelations(bool $withSale = true): array
    {
        $relations = [
            'seller:id,name',
            'payments' => fn ($query) => $query->orderBy('id'),
            'payments.payer:id,name',
            'payments.payable:id,commission_payment_id,bank_account_id',
            'payments.payable.bankAccount:id,name',
            'refunds' => fn ($query) => $query->orderBy('id'),
            'refunds.requester:id,name',
            'refunds.entries' => fn ($query) => $query->orderBy('id'),
            'refunds.entries.bankAccount:id,name',
            'refunds.entries.user:id,name',
        ];

        if ($withSale) {
            $relations[] = 'sale:id,code,client_name,client_cnpj,sold_at,status,total_cents';
            $relations[] = 'sale.installments';
        }

        return $relations;
    }

    /**
     * O que a usuária pode fazer em Comissões (telas e validações usam o mesmo mapa).
     *
     * @return array{register_payment: bool, pay_advance: bool, delete_payment: bool, charge_refund: bool, register_refund: bool, assume_loss: bool}
     */
    public static function abilities(?User $user): array
    {
        $create = (bool) $user?->canAccessAdmin(AdminPermissionModule::FinanceiroComissoes, PermissionAction::Create);
        $advance = self::canAdvance($user);

        return [
            'register_payment' => $create,
            'pay_advance' => $advance,
            'delete_payment' => (bool) $user?->canAccessAdmin(AdminPermissionModule::FinanceiroComissoes, PermissionAction::Delete),
            'charge_refund' => $create,
            'register_refund' => $create,
            'assume_loss' => $advance,
        ];
    }

    /** Pagar acima do liberável: Comissões (criar) + «Pagar antes do recebimento» (criar). */
    public static function canAdvance(?User $user): bool
    {
        return (bool) $user?->canAccessAdmin(AdminPermissionModule::FinanceiroComissoes, PermissionAction::Create)
            && (bool) $user?->canAccessAdmin(AdminPermissionModule::FinanceiroComissoesAdiantamento, PermissionAction::Create);
    }

    /**
     * Saldo da comissão = pending_cents; dele, releasable_cents pode ser pago agora (proporcional) e
     * awaiting_receipt_cents depende do recebimento da venda.
     *
     * @param  Builder<CommercialCommission>  $query
     * @return array<string, int>
     */
    private function summary(Builder $query): array
    {
        $open = CommissionPayoutCalculator::openTotals($query);

        return [
            ...$open,
            'awaiting_receipt_cents' => max(0, $open['pending_cents'] - $open['releasable_cents']),
            ...CommissionPayoutCalculator::refundTotals($query),
            ...CommissionPayoutCalculator::lossAssumedTotals($query),
            'paid_cents' => (int) (clone $query)->sum('paid_cents'),
        ];
    }

    /**
     * Contas ativas para a saída do pagamento de comissão e a entrada da devolução.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function bankAccountOptions(): array
    {
        return FinanceBankAccount::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FinanceBankAccount $a) => ['id' => (int) $a->id, 'name' => (string) $a->name])
            ->values()
            ->all();
    }

    /**
     * @return list<mixed>
     */
    private static function activeBankAccountRules(): array
    {
        return ['required', Rule::exists('finance_bank_accounts', 'id')->where('is_active', true)];
    }

    /**
     * @return array<string, string>
     */
    private static function bankAccountMessages(): array
    {
        return [
            'bank_account_id.required' => 'Selecione a conta bancária.',
            'bank_account_id.exists' => 'Selecione uma conta bancária ativa.',
        ];
    }

    private static function toCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
