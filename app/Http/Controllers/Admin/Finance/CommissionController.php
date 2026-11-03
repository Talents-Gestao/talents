<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Commercial\DeleteLatestCommissionPayment;
use App\Actions\Commercial\RegisterCommissionPayment;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\User;
use App\Services\Commercial\CommissionPayoutCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comissões proporcionais ao recebido: o repasse é registrado em pagamentos
 * (vários por comissão), limitados ao liberável. Status é derivado (a_pagar/parcial/paga).
 */
class CommissionController extends Controller
{
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
            if (array_key_exists($status, CommercialCommission::statusLabels())) {
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
            'statusOptions' => CommercialCommission::statusLabels(),
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
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'amount.required' => 'Informe o valor do pagamento.',
            'amount.numeric' => 'Informe um valor válido.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'paid_at.required' => 'Informe a data do pagamento.',
            'paid_at.date' => 'Informe uma data válida.',
            'paid_at.before_or_equal' => 'A data do pagamento não pode ser futura.',
        ]);

        $register->handle(
            $commission,
            (int) round(((float) $data['amount']) * 100),
            Carbon::parse($data['paid_at'])->startOfDay(),
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
        ];

        if ($withSale) {
            $relations[] = 'sale:id,code,client_name,client_cnpj,sold_at,status,total_cents';
            $relations[] = 'sale.installments';
        }

        return $relations;
    }

    /**
     * @param  Builder<CommercialCommission>  $query
     * @return array{pending_cents: int, paid_cents: int, releasable_cents: int, pending_count: int, over_ceiling_count: int}
     */
    private function summary(Builder $query): array
    {
        return [
            ...CommissionPayoutCalculator::openTotals($query),
            'paid_cents' => (int) (clone $query)->sum('paid_cents'),
            'over_ceiling_count' => (int) (clone $query)->whereColumn('paid_cents', '>', 'amount_cents')->count(),
        ];
    }

    private function can(Request $request, PermissionAction $action): bool
    {
        return (bool) $request->user()?->canAccessAdmin(AdminPermissionModule::FinanceiroComissoes, $action);
    }
}
