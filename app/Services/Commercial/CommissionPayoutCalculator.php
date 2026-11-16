<?php

declare(strict_types=1);

namespace App\Services\Commercial;

use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\CommercialCommissionRefund;
use App\Models\CommercialCommissionRefundEntry;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Support\Commercial\OptionalCommission;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comissão: fonte única dos cálculos (backend e telas).
 *
 * - Teto (amount_cents) = round(percent × soma das parcelas não canceladas).
 * - Pago (paid_cents) = pagamentos ativos − devoluções já registradas (valor líquido com a colaboradora).
 * - Ganho sobre o recebido = floor(percent × soma recebida), limitado ao teto; venda quitada ganha o teto.
 * - Liberável (proporcional) = ganho − pago, nunca negativo e nunca acima do saldo. É o limite de quem
 *   não pode adiantar e o indicador para todos.
 * - Limite de pagamento = saldo (teto − pago): quem tem a permissão de adiantar paga até aqui,
 *   independentemente do recebido. Nunca acima do teto.
 * - Adiantado em aberto = max(0, min(pago, teto) − ganho): diminui sozinho quando o cliente paga.
 * - Excesso sobre o teto = max(0, pago − teto): vira devolução pendente (RefreshCommissionPayout).
 * - Status: a_pagar (nada pago), parcial (0 < pago < teto), paga (pago ≥ teto).
 */
final class CommissionPayoutCalculator
{
    public static function ceilingCents(int $saleTotalCents, float $percent): int
    {
        return OptionalCommission::centsFromPercent($saleTotalCents, $percent);
    }

    public static function statusFor(int $paidCents, int $amountCents): string
    {
        return match (true) {
            $paidCents <= 0 => CommercialCommission::STATUS_A_PAGAR,
            $paidCents < $amountCents => CommercialCommission::STATUS_PARCIAL,
            default => CommercialCommission::STATUS_PAGA,
        };
    }

    /**
     * Valor da venda para o teto: parcelas não canceladas (ou o total gravado, se a venda não tem parcelas).
     *
     * @param  Collection<int, CommercialSaleInstallment>  $installments
     */
    public static function saleTotalCents(CommercialSale $sale, Collection $installments): int
    {
        return $installments->isEmpty()
            ? (int) $sale->total_cents
            : CommercialSale::totalFromInstallments($installments);
    }

    /**
     * @param  Collection<int, CommercialSaleInstallment>  $installments
     */
    public static function receivedCents(Collection $installments): int
    {
        return (int) $installments
            ->where('status', CommercialSaleInstallment::STATUS_PAGO)
            ->sum(fn (CommercialSaleInstallment $i) => (int) ($i->paid_amount_cents ?? $i->amount_cents));
    }

    /**
     * floor(percent × recebido), em aritmética inteira (percentual em centésimos).
     */
    public static function earnedOnReceivedCents(int $receivedCents, float $percent): int
    {
        $basisPoints = (int) round($percent * 100);
        if ($receivedCents < 1 || $basisPoints < 1) {
            return 0;
        }

        return intdiv($receivedCents * $basisPoints, 10_000);
    }

    /**
     * Quanto da comissão o recebido já cobre: floor(percent × recebido), limitado ao teto;
     * venda quitada cobre o teto inteiro (absorve o centavo do floor).
     *
     * @param  Collection<int, CommercialSaleInstallment>  $installments
     */
    public static function earnedCents(
        CommercialCommission $commission,
        CommercialSale $sale,
        Collection $installments,
    ): int {
        $amount = (int) $commission->amount_cents;

        if ($sale->status === CommercialSale::STATUS_QUITADA) {
            return $amount;
        }

        return min($amount, self::earnedOnReceivedCents(self::receivedCents($installments), (float) $commission->percent));
    }

    /**
     * Liberável proporcional ao recebido: ganho − pago, nunca negativo nem acima do saldo.
     *
     * @param  Collection<int, CommercialSaleInstallment>  $installments
     */
    public static function releasableCents(
        CommercialCommission $commission,
        CommercialSale $sale,
        Collection $installments,
    ): int {
        $paid = (int) $commission->paid_cents;

        return min(
            self::paymentLimitCents($commission),
            max(0, self::earnedCents($commission, $sale, $installments) - $paid),
        );
    }

    /** Limite de pagamento com adiantamento: saldo da comissão (teto − pago). */
    public static function paymentLimitCents(CommercialCommission $commission): int
    {
        return max(0, (int) $commission->amount_cents - (int) $commission->paid_cents);
    }

    /**
     * Adiantado em aberto: o que foi pago (até o teto) além do que o recebido já cobre.
     *
     * @param  Collection<int, CommercialSaleInstallment>  $installments
     */
    public static function advanceCents(
        CommercialCommission $commission,
        CommercialSale $sale,
        Collection $installments,
    ): int {
        $paidWithinCeiling = min((int) $commission->paid_cents, (int) $commission->amount_cents);

        return max(0, $paidWithinCeiling - self::earnedCents($commission, $sale, $installments));
    }

    /**
     * Adiantado que ainda pode ser cobrado: o adiantado em aberto menos o que já está em cobrança
     * (pendente) ou já foi assumido como perda nas devoluções de cliente que não pagou.
     *
     * @param  iterable<CommercialCommissionRefund>  $clientDefaultRefunds
     */
    public static function chargeableAdvanceCents(int $advanceCents, iterable $clientDefaultRefunds): int
    {
        $covered = 0;
        foreach ($clientDefaultRefunds as $refund) {
            $covered += $refund->pendingCents() + (int) $refund->written_off_cents;
        }

        return max(0, $advanceCents - $covered);
    }

    /** Pago acima do teto (parcela ou venda cancelada depois do repasse). */
    public static function ceilingExcessCents(CommercialCommission $commission): int
    {
        return max(0, (int) $commission->paid_cents - (int) $commission->amount_cents);
    }

    /**
     * Perda assumida antes da regra de devolução: a comissão já estava acima do teto com o aviso
     * antigo disparado (loss_notified_at). Esses casos não viram devolução: dados antigos não mudam.
     */
    public static function isLegacyLoss(CommercialCommission $commission): bool
    {
        return $commission->loss_notified_at !== null && self::ceilingExcessCents($commission) > 0;
    }

    /**
     * Pago líquido: pagamentos ativos − devoluções registradas.
     */
    public static function netPaidCents(CommercialCommission $commission): int
    {
        $payments = (int) $commission->payments()->sum('amount_cents');
        $returned = (int) CommercialCommissionRefundEntry::query()
            ->where('type', CommercialCommissionRefundEntry::TYPE_RETURNED)
            ->whereIn('refund_id', $commission->refunds()->select('id'))
            ->sum('amount_cents');

        return max(0, $payments - $returned);
    }

    /**
     * Perda assumida: excesso legado (pago acima do teto com o aviso antigo) + perdas assumidas
     * nas devoluções. Com período, o legado conta pela data do aviso e as novas pela data do registro.
     *
     * @param  Builder<CommercialCommission>  $query
     * @return array{loss_assumed_cents: int, loss_assumed_count: int}
     */
    public static function lossAssumedTotals(
        Builder $query,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): array {
        $legacy = (clone $query)
            ->whereNotNull('loss_notified_at')
            ->whereColumn('paid_cents', '>', 'amount_cents')
            ->when($from, fn ($q) => $q->where('loss_notified_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('loss_notified_at', '<=', $to));

        $writtenOff = CommercialCommissionRefundEntry::query()
            ->where('type', CommercialCommissionRefundEntry::TYPE_LOSS_ASSUMED)
            ->whereHas('refund', fn ($q) => $q->whereIn('commission_id', (clone $query)->select('id')))
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to));

        $legacyIds = (clone $legacy)->pluck('id');
        $writtenOffIds = CommercialCommissionRefund::query()
            ->whereIn('id', (clone $writtenOff)->select('refund_id'))
            ->pluck('commission_id');

        return [
            'loss_assumed_cents' => (int) (clone $legacy)->sum(DB::raw('paid_cents - amount_cents'))
                + (int) (clone $writtenOff)->sum('amount_cents'),
            'loss_assumed_count' => $legacyIds->merge($writtenOffIds)->unique()->count(),
        ];
    }

    /**
     * Devoluções pendentes de um conjunto de comissões.
     *
     * @param  Builder<CommercialCommission>  $query
     * @return array{refund_pending_cents: int, refund_pending_count: int}
     */
    public static function refundTotals(Builder $query): array
    {
        $pending = CommercialCommissionRefund::query()
            ->where('status', CommercialCommissionRefund::STATUS_PENDING)
            ->whereIn('commission_id', (clone $query)->select('id'));

        return [
            'refund_pending_cents' => (int) (clone $pending)
                ->sum(DB::raw('amount_cents - returned_cents - written_off_cents - cancelled_cents')),
            'refund_pending_count' => (int) (clone $pending)->distinct()->count('commission_id'),
        ];
    }

    /**
     * Totais de um conjunto de comissões (KPI do Resumo e cartões da tela de Comissões).
     * Saldo = Σ max(0, teto − pago); liberável = Σ liberável agora; adiantado = Σ adiantado em aberto.
     *
     * @param  Builder<CommercialCommission>  $query
     * @return array{pending_cents: int, releasable_cents: int, pending_count: int, advance_cents: int, advance_count: int}
     */
    public static function openTotals(Builder $query): array
    {
        $open = (clone $query)->whereColumn('amount_cents', '>', 'paid_cents');

        $releasable = 0;
        (clone $open)
            ->with(['sale:id,status,total_cents', 'sale.installments'])
            ->chunkById(200, function ($commissions) use (&$releasable): void {
                foreach ($commissions as $commission) {
                    if ($commission->sale) {
                        $releasable += self::releasableCents($commission, $commission->sale, $commission->sale->installments);
                    }
                }
            });

        // Adiantado só existe em comissão com algo pago e venda não quitada.
        $advance = 0;
        $advanceCount = 0;
        (clone $query)
            ->where('paid_cents', '>', 0)
            ->whereHas('sale', fn ($q) => $q->where('status', '!=', CommercialSale::STATUS_QUITADA))
            ->with(['sale:id,status,total_cents', 'sale.installments'])
            ->chunkById(200, function ($commissions) use (&$advance, &$advanceCount): void {
                foreach ($commissions as $commission) {
                    $value = self::advanceCents($commission, $commission->sale, $commission->sale->installments);
                    if ($value > 0) {
                        $advance += $value;
                        $advanceCount++;
                    }
                }
            });

        return [
            'pending_cents' => (int) (clone $open)->sum(DB::raw('amount_cents - paid_cents')),
            'releasable_cents' => $releasable,
            'pending_count' => (int) (clone $open)->count(),
            'advance_cents' => $advance,
            'advance_count' => $advanceCount,
        ];
    }

    /**
     * Payload único para as telas (Comissões e Detalhe da venda). Espera sale.installments,
     * seller, payments.payer e refunds.entries carregados.
     *
     * @param  array{delete_payment?: bool}|bool  $can
     * @return array<string, mixed>
     */
    public static function present(CommercialCommission $commission, array|bool $can = false): array
    {
        $canDeletePayment = is_array($can) ? (bool) ($can['delete_payment'] ?? false) : $can;
        $sale = $commission->sale;
        $installments = $sale?->installments ?? collect();
        $amount = (int) $commission->amount_cents;
        $paid = (int) $commission->paid_cents;
        $payments = ($commission->payments ?? collect())->sortBy('id')->values();
        $latestId = $payments->max('id');
        $refunds = ($commission->relationLoaded('refunds') ? $commission->refunds : $commission->refunds()->with('entries')->get())
            ->sortBy('id')
            ->values();
        $hasResolvedRefund = $refunds->contains(fn (CommercialCommissionRefund $r) => $r->returned_cents > 0 || $r->written_off_cents > 0);
        $legacyLoss = self::isLegacyLoss($commission);
        $writtenOff = (int) $refunds->sum('written_off_cents');
        $received = self::receivedCents($installments);
        $releasable = $sale ? self::releasableCents($commission, $sale, $installments) : 0;
        $advance = $sale ? self::advanceCents($commission, $sale, $installments) : 0;
        $refundPending = (int) $refunds->sum(fn (CommercialCommissionRefund $r) => $r->pendingCents());
        $today = now()->toDateString();

        return [
            'id' => (int) $commission->id,
            'sale_id' => (int) $commission->sale_id,
            'seller' => $commission->seller ? [
                'id' => (int) $commission->seller->id,
                'name' => (string) $commission->seller->name,
            ] : null,
            'percent' => (float) $commission->percent,
            'base_cents' => (int) $commission->base_cents,
            'amount_cents' => $amount,
            'paid_cents' => $paid,
            'remaining_cents' => self::paymentLimitCents($commission),
            'payment_limit_cents' => self::paymentLimitCents($commission),
            'releasable_cents' => $releasable,
            'earned_on_received_cents' => $sale ? self::earnedCents($commission, $sale, $installments) : 0,
            'advance_cents' => $advance,
            // Adiantado que ainda pode ser cobrado (o que já está em cobrança fica de fora).
            'chargeable_advance_cents' => self::chargeableAdvanceCents(
                $advance,
                $refunds->where('origin', CommercialCommissionRefund::ORIGIN_CLIENT_DEFAULT),
            ),
            // Saldo que ainda depende do recebimento da venda (sem contar o que pode ser pago agora).
            'awaiting_receipt_cents' => max(0, self::paymentLimitCents($commission) - $releasable),
            'refund_pending_cents' => $refundPending,
            // Perda assumida: legado (aviso antigo) + perdas registradas nas devoluções.
            'loss_assumed' => $legacyLoss || $writtenOff > 0,
            'loss_assumed_cents' => ($legacyLoss ? self::ceilingExcessCents($commission) : 0) + $writtenOff,
            'legacy_loss' => $legacyLoss,
            'can_delete_latest_payment' => ! $hasResolvedRefund,
            'status' => (string) $commission->status,
            'status_label' => CommercialCommission::statusLabels()[$commission->status] ?? (string) $commission->status,
            'paid_at' => $commission->paid_at?->toIso8601String(),
            'notes' => $commission->notes,
            'created_at' => $commission->created_at?->toIso8601String(),
            'sale' => $sale ? [
                'id' => (int) $sale->id,
                'code' => (string) $sale->code,
                'client_name' => (string) $sale->client_name,
                'client_cnpj' => $sale->client_cnpj,
                'sold_at' => $sale->sold_at?->toIso8601String(),
                'status' => (string) $sale->status,
                'total_cents' => self::saleTotalCents($sale, $installments),
                'received_cents' => $received,
                'outstanding_cents' => max(0, self::saleTotalCents($sale, $installments) - $received),
                'pending_installments' => $installments
                    ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
                    ->sortBy(fn (CommercialSaleInstallment $i) => [$i->due_date?->toDateString() ?? '9999-12-31', (int) $i->number])
                    ->map(fn (CommercialSaleInstallment $i) => [
                        'id' => (int) $i->id,
                        'number' => (int) $i->number,
                        'due_date' => $i->due_date?->toDateString(),
                        'amount_cents' => (int) $i->amount_cents,
                        // Mesma regra do Resumo do Financeiro: vencida = vencimento antes de hoje.
                        'is_overdue' => $i->due_date !== null && $i->due_date->toDateString() < $today,
                    ])
                    ->values()
                    ->all(),
            ] : null,
            'payments' => $payments->map(fn (CommercialCommissionPayment $p) => [
                'id' => (int) $p->id,
                'amount_cents' => (int) $p->amount_cents,
                'advance_cents' => (int) ($p->advance_cents ?? 0),
                'paid_at' => $p->paid_at?->toIso8601String(),
                'notes' => $p->notes,
                'paid_by' => $p->payer?->name,
                // Sem conta a pagar vinculada = pagamento anterior à Fase 2, sem movimento de saldo.
                'is_legacy' => $p->payable === null,
                'bank_account' => $p->payable?->bankAccount?->name,
                'can_delete' => $canDeletePayment && ! $hasResolvedRefund && (int) $p->id === (int) $latestId,
            ])->values()->all(),
            'refunds' => $refunds->map(fn (CommercialCommissionRefund $r) => [
                'id' => (int) $r->id,
                'origin' => (string) $r->origin,
                'origin_label' => CommercialCommissionRefund::originLabels()[$r->origin] ?? (string) $r->origin,
                'amount_cents' => (int) $r->amount_cents,
                'returned_cents' => (int) $r->returned_cents,
                'written_off_cents' => (int) $r->written_off_cents,
                'cancelled_cents' => (int) $r->cancelled_cents,
                'pending_cents' => $r->pendingCents(),
                'status' => (string) $r->status,
                'justification' => $r->justification,
                'requested_by' => $r->requester?->name,
                'created_at' => $r->created_at?->toIso8601String(),
                'entries' => $r->entries->sortBy('id')->map(fn (CommercialCommissionRefundEntry $e) => [
                    'id' => (int) $e->id,
                    'type' => (string) $e->type,
                    'type_label' => CommercialCommissionRefundEntry::typeLabels()[$e->type] ?? (string) $e->type,
                    'amount_cents' => (int) $e->amount_cents,
                    'occurred_at' => $e->occurred_at?->toIso8601String(),
                    'bank_account' => $e->bankAccount?->name,
                    'notes' => $e->notes,
                    'user' => $e->user?->name,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
