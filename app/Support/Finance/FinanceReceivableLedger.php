<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Enums\FinanceReceivableStatus;
use App\Models\CommercialSaleInstallment;
use App\Models\FinanceReceivable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Lista unificada: parcelas de venda + contas a receber manuais.
 */
class FinanceReceivableLedger
{
    /**
     * @param  array{q?: string, status?: string, origin?: string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $origin = $filters['origin'] ?? '';
        $items = collect();

        if ($origin === '' || $origin === 'manual') {
            $items = $items->merge($this->manualRows($filters));
        }

        if ($origin === '' || $origin === 'sale') {
            $items = $items->merge($this->saleRows($filters));
        }

        $sorted = $items
            ->sortBy([
                ['due_date', 'desc'],
                ['id', 'desc'],
            ])
            ->values();

        $page = max(1, (int) request()->integer('page', 1));
        $slice = $sorted->forPage($page, $perPage)->values();

        return new Paginator(
            $slice->all(),
            $sorted->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  array{q?: string, status?: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function manualRows(array $filters): Collection
    {
        $query = FinanceReceivable::query()
            ->with(['paymentMethod:id,name', 'bankAccount:id,name'])
            ->orderByDesc('due_date');

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('payer_name', 'like', $term);
            });
        }

        return $query->get()->map(fn (FinanceReceivable $r) => [
            'id' => 'manual-'.$r->id,
            'source' => 'manual',
            'source_label' => 'Manual',
            'receivable_id' => $r->id,
            'installment_id' => null,
            'sale_id' => null,
            'title' => $r->title,
            'counterparty' => $r->payer_name,
            'amount_cents' => $r->amount_cents,
            'due_date' => $r->due_date?->toDateString(),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'method' => null,
            'notes' => $r->notes,
            'is_recurring' => false,
            'recurring_label' => null,
            'payment_method' => $r->paymentMethod?->only(['id', 'name']),
            'payment_method_id' => $r->payment_method_id,
            'bank_account_id' => $r->bank_account_id,
            'bank_account' => $r->bankAccount?->only(['id', 'name']),
            'href' => route('admin.financeiro.contas-a-receber.edit', $r),
            'can_mark_paid' => $r->status === FinanceReceivableStatus::Pending,
            'can_edit' => true,
            'can_delete' => true,
        ]);
    }

    /**
     * @param  array{q?: string, status?: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function saleRows(array $filters): Collection
    {
        $statusMap = [
            'pending' => CommercialSaleInstallment::STATUS_PENDENTE,
            'paid' => CommercialSaleInstallment::STATUS_PAGO,
            'cancelled' => CommercialSaleInstallment::STATUS_CANCELADO,
        ];

            $query = CommercialSaleInstallment::query()
            ->with(['sale:id,code,client_name,is_recurring,recurring_months,recurring_monthly_cents,installments_count'])
            ->orderByDesc('due_date');

        if (($filters['status'] ?? '') !== '' && isset($statusMap[$filters['status']])) {
            $query->where('status', $statusMap[$filters['status']]);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->whereHas('sale', function ($q) use ($term) {
                $q->where('client_name', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        return $query->get()->map(function (CommercialSaleInstallment $i) {
            $status = match ($i->status) {
                CommercialSaleInstallment::STATUS_PAGO => FinanceReceivableStatus::Paid,
                CommercialSaleInstallment::STATUS_CANCELADO => FinanceReceivableStatus::Cancelled,
                default => FinanceReceivableStatus::Pending,
            };

            $methodLabels = ['pix' => 'PIX', 'boleto' => 'Boleto', 'cartao' => 'Cartão'];
            $isRecurring = (bool) ($i->sale?->is_recurring);
            $recurringMonths = (int) ($i->sale?->recurring_months ?? $i->sale?->installments_count ?? 0);

            return [
                'id' => 'sale-'.$i->id,
                'source' => 'sale',
                'source_label' => 'Venda',
                'receivable_id' => null,
                'installment_id' => $i->id,
                'sale_id' => $i->sale_id,
                'title' => ($i->sale?->code ?? 'Venda').' · Parcela '.$i->number,
                'counterparty' => $i->sale?->client_name,
                'amount_cents' => $i->amount_cents,
                'due_date' => $i->due_date?->toDateString(),
                'status' => $status->value,
                'status_label' => $status->label(),
                'method' => $i->method,
                'notes' => $i->notes,
                'installment_status' => $i->status,
                'is_recurring' => $isRecurring,
                'recurring_label' => $isRecurring && $recurringMonths > 0
                    ? 'Recorrente · Mês '.$i->number.'/'.$recurringMonths
                    : null,
                'payment_method' => [
                    'id' => null,
                    'name' => $methodLabels[$i->method] ?? $i->method,
                ],
                'payment_method_id' => null,
                'bank_account_id' => null,
                'bank_account' => null,
                'href' => $i->sale_id
                    ? route('admin.financeiro.vendas.show', $i->sale_id)
                    : null,
                'can_mark_paid' => false,
                'can_edit' => true,
                'can_delete' => false,
            ];
        });
    }
}
