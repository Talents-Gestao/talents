<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Enums\FinanceReceivableStatus;
use App\Models\CommercialSaleInstallment;
use App\Models\FinanceReceivable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contas a receber: parcelas de venda + recebimentos manuais numa lista só.
 *
 * A lista é montada no banco (UNION ALL das duas origens), ordenada e paginada
 * em SQL; só as linhas da página são hidratadas com os models. Desempate
 * determinístico por (origem, id).
 *
 * Modo padrão: lista corrida, na ordem do critério ativo.
 * Modo «agrupar por venda» (group=sale): parcelas da mesma venda juntas e na
 * mesma página. Ver groupedPage().
 */
class FinanceReceivableLedger
{
    public const GROUP_SALE = 'sale';

    private const STATUS_FROM_INSTALLMENT = [
        'pending' => CommercialSaleInstallment::STATUS_PENDENTE,
        'paid' => CommercialSaleInstallment::STATUS_PAGO,
        'cancelled' => CommercialSaleInstallment::STATUS_CANCELADO,
    ];

    /**
     * @param  array{q?: string, status?: string, origin?: string, sort?: string, group?: string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, FinanceListSort::values(), true)
            ? (string) $filters['sort']
            : FinanceListSort::defaultForStatus($filters['status'] ?? null);

        $union = $this->unionQuery($filters);
        if ($union === null) {
            return $this->emptyPage($perPage);
        }

        if (($filters['group'] ?? '') === self::GROUP_SALE) {
            return $this->groupedPage($union, $sort, $perPage);
        }

        $query = DB::query()->fromSub($union, 'ledger')->select(['source', 'entry_id']);
        foreach ($this->orderExpressions($sort) as $expression) {
            $query->orderByRaw($expression);
        }

        $paginator = $query->paginate($perPage)
            ->withPath(RelativePagination::path(request()))
            ->withQueryString();

        return $paginator->setCollection($this->hydrate($paginator->getCollection()));
    }

    /**
     * Agrupado por venda, inteiramente em SQL (window functions, Postgres e SQLite):
     *
     * 1. row_rank    = posição da linha na ordem ativa (a mesma da lista corrida);
     * 2. group_rank  = MIN(row_rank) do grupo → o grupo entra na posição da sua
     *                  melhor linha; dentro do grupo, as linhas seguem row_rank;
     * 3. pos         = posição final (group_rank, row_rank);
     * 4. group_start = MIN(pos) do grupo;
     * 5. page_no     = DENSE_RANK de (group_start - 1) / perPage → o grupo inteiro
     *                  cai na página em que começa (nunca é cortado) e não sobram
     *                  páginas vazias. Uma página pode passar de perPage quando um
     *                  grupo grande começa perto do fim dela.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function groupedPage(Builder $union, string $sort, int $perPage): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        $order = implode(', ', $this->orderExpressions($sort));
        $groupKey = "CASE WHEN source = 'sale' AND sale_id IS NOT NULL THEN 'sale-' || sale_id ELSE source || '-' || entry_id END";

        $ranked = DB::query()->fromSub($union, 'u')
            ->selectRaw("source, entry_id, {$groupKey} AS group_key, ROW_NUMBER() OVER (ORDER BY {$order}) AS row_rank");
        $grouped = DB::query()->fromSub($ranked, 'r')
            ->selectRaw('r.*, MIN(row_rank) OVER (PARTITION BY group_key) AS group_rank');
        $positioned = DB::query()->fromSub($grouped, 'g')
            ->selectRaw('g.*, ROW_NUMBER() OVER (ORDER BY group_rank, row_rank) AS pos');
        $started = DB::query()->fromSub($positioned, 'p')
            ->selectRaw('p.*, MIN(pos) OVER (PARTITION BY group_key) AS group_start');
        $paged = DB::query()->fromSub($started, 's')
            ->selectRaw("s.*, DENSE_RANK() OVER (ORDER BY (group_start - 1) / {$perPage}) AS page_no");

        $meta = DB::query()->fromSub($paged, 'm')
            ->selectRaw('COUNT(*) AS total, MAX(page_no) AS pages')
            ->first();
        $total = (int) ($meta->total ?? 0);
        $pages = max(1, (int) ($meta->pages ?? 0));
        $page = max(1, (int) request()->integer('page', 1));

        $refs = $total === 0 ? collect() : DB::query()->fromSub($paged, 'x')
            ->where('page_no', $page)
            ->orderBy('pos')
            ->get(['source', 'entry_id', 'pos']);

        return new GroupedLengthAwarePaginator(
            $this->hydrate($refs)->all(),
            $total,
            $perPage,
            $page,
            $pages,
            $refs->isEmpty() ? null : (int) $refs->first()->pos,
            $refs->isEmpty() ? null : (int) $refs->last()->pos,
            [
                'path' => RelativePagination::path(request()),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * ORDER BY completo da lista: critério ativo + desempate (origem, id).
     *
     * @return list<string>
     */
    private function orderExpressions(string $sort): array
    {
        return [...FinanceListSort::orderBySql($sort), 'source ASC', 'entry_id ASC'];
    }

    /**
     * @param  array{q?: string, status?: string, origin?: string}  $filters
     */
    private function unionQuery(array $filters): ?Builder
    {
        $origin = $filters['origin'] ?? '';
        $parts = [];

        if ($origin === '' || $origin === 'manual') {
            $parts[] = $this->manualQuery($filters);
        }

        if ($origin === '' || $origin === 'sale') {
            $parts[] = $this->saleQuery($filters);
        }

        $parts = array_values(array_filter($parts));
        if ($parts === []) {
            return null;
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return $union;
    }

    /**
     * @param  array{q?: string, status?: string}  $filters
     */
    private function manualQuery(array $filters): Builder
    {
        $query = DB::table('finance_receivables')
            ->selectRaw("'manual' AS source, id AS entry_id, CAST(NULL AS INTEGER) AS sale_id, due_date, paid_at");

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('payer_name', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @param  array{q?: string, status?: string}  $filters
     */
    private function saleQuery(array $filters): ?Builder
    {
        $query = DB::table('commercial_sale_installments as i')
            ->selectRaw("'sale' AS source, i.id AS entry_id, i.sale_id AS sale_id, i.due_date AS due_date, i.paid_at AS paid_at");

        $status = $filters['status'] ?? '';
        if ($status !== '') {
            if (! isset(self::STATUS_FROM_INSTALLMENT[$status])) {
                return null;
            }
            $query->where('i.status', self::STATUS_FROM_INSTALLMENT[$status]);
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->whereExists(function (Builder $q) use ($term) {
                $q->selectRaw('1')
                    ->from('commercial_sales as s')
                    ->whereColumn('s.id', 'i.sale_id')
                    ->where(function (Builder $w) use ($term) {
                        $w->where('s.client_name', 'like', $term)
                            ->orWhere('s.code', 'like', $term);
                    });
            });
        }

        return $query;
    }

    /**
     * Carrega os models só das linhas da página, preservando a ordem do SQL.
     *
     * @param  Collection<int, object{source: string, entry_id: int|string}>  $refs
     * @return Collection<int, array<string, mixed>>
     */
    private function hydrate(Collection $refs): Collection
    {
        $manualIds = $refs->where('source', 'manual')->pluck('entry_id')->map(fn ($id) => (int) $id)->all();
        $saleIds = $refs->where('source', 'sale')->pluck('entry_id')->map(fn ($id) => (int) $id)->all();

        $manuals = $manualIds === [] ? collect() : FinanceReceivable::query()
            ->with(['paymentMethod:id,name', 'bankAccount:id,name'])
            ->whereIn('id', $manualIds)
            ->get()
            ->keyBy('id');

        $installments = $saleIds === [] ? collect() : CommercialSaleInstallment::query()
            ->with([
                'sale:id,code,client_name,is_recurring,recurring_months,recurring_monthly_cents,installments_count',
                'bankAccount:id,name',
            ])
            ->whereIn('id', $saleIds)
            ->get()
            ->keyBy('id');

        return $refs
            ->map(function (object $ref) use ($manuals, $installments): ?array {
                $id = (int) $ref->entry_id;

                return $ref->source === 'manual'
                    ? ($manuals->has($id) ? $this->manualRow($manuals->get($id)) : null)
                    : ($installments->has($id) ? $this->saleRow($installments->get($id)) : null);
            })
            ->filter()
            ->values();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyPage(int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => RelativePagination::path(request()),
            'query' => request()->query(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function manualRow(FinanceReceivable $r): array
    {
        return [
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
            'paid_at' => self::paidDay($r->paid_at),
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function saleRow(CommercialSaleInstallment $i): array
    {
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
            'sale_code' => $i->sale?->code,
            'installment_number' => $i->number,
            'installments_count' => $i->sale?->installments_count,
            'title' => 'Parcela '.$i->number,
            'counterparty' => $i->sale?->client_name,
            'amount_cents' => $i->amount_cents,
            'due_date' => $i->due_date?->toDateString(),
            'paid_at' => self::paidDay($i->paid_at),
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
            'bank_account_id' => $i->bank_account_id,
            'bank_account' => $i->bankAccount?->only(['id', 'name']),
            'href' => $i->sale_id
                ? route('admin.financeiro.vendas.show', $i->sale_id)
                : null,
            'can_mark_paid' => $i->status === CommercialSaleInstallment::STATUS_PENDENTE,
            'can_edit' => true,
            'can_delete' => false,
        ];
    }

    private static function paidDay(?CarbonInterface $paidAt): ?string
    {
        return $paidAt?->copy()->setTimezone(FinancePaymentDate::TIMEZONE)->toDateString();
    }
}
