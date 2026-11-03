<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Commercial\DeleteLatestCommissionPayment;
use App\Actions\Commercial\RegisterCommissionPayment;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
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
 * Comissões proporcionais ao recebido: o repasse é registrado em pagamentos
 * (vários por comissão), limitados ao liberável. Status é derivado (a_pagar/parcial/paga).
 */
class CommissionController extends Controller
{
    /** Filtro de listagem: a_pagar + parcial (link «Comissões a pagar» do Resumo). */
    public const FILTER_PENDENTES = 'pendentes';

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
            } elseif (array_key_exists($status, CommercialCommission::statusLabels())) {
                $q->where('status', $status);
            }
        }

        $summary = $this->summary(clone $q);

        $canDelete = $this->can($request, PermissionAction::Delete);

        $commissions = $q
            ->with(self::payoutRelations())
            ->orderByRaw("CASE WHEN status = '".CommercialCommission::STATUS_PAGA."' THEN 1 ELSE 0 END")
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CommercialCommission $c) => CommissionPayoutCalculator::present($c, $canDelete));

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
                ...CommercialCommission::statusLabels(),
            ],
            'bankAccounts' => self::bankAccountOptions(),
            'can' => [
                'register_payment' => $this->can($request, PermissionAction::Create),
                'delete_payment' => $canDelete,
            ],
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
            'bank_account_id' => [
                'required',
                Rule::exists('finance_bank_accounts', 'id')->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'bank_account_id.required' => 'Selecione a conta bancária de onde sai o pagamento.',
            'bank_account_id.exists' => 'Selecione uma conta bancária ativa.',
            ...FinancePaymentDate::messages(),
            'amount.required' => 'Informe o valor do pagamento.',
            'amount.numeric' => 'Informe um valor válido.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'paid_at.required' => 'Informe a data do pagamento.',
        ]);

        $register->handle(
            $commission,
            (int) round(((float) $data['amount']) * 100),
            FinancePaymentDate::toPaidAt($data['paid_at']),
            (int) $data['bank_account_id'],
            $data['notes'] ?? null,
            $request->user(),
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
        ];

        if ($withSale) {
            $relations[] = 'sale:id,code,client_name,client_cnpj,sold_at,status,total_cents';
            $relations[] = 'sale.installments';
        }

        return $relations;
    }

    /**
     * @param  Builder<CommercialCommission>  $query
     * @return array{pending_cents: int, paid_cents: int, releasable_cents: int, pending_count: int, loss_assumed_cents: int, loss_assumed_count: int}
     */
    private function summary(Builder $query): array
    {
        return [
            ...CommissionPayoutCalculator::openTotals($query),
            ...CommissionPayoutCalculator::lossAssumedTotals($query),
            'paid_cents' => (int) (clone $query)->sum('paid_cents'),
        ];
    }

    /**
     * Contas ativas para a saída do pagamento de comissão.
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

    private function can(Request $request, PermissionAction $action): bool
    {
        return (bool) $request->user()?->canAccessAdmin(AdminPermissionModule::FinanceiroComissoes, $action);
    }
}
