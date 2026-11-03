<?php

declare(strict_types=1);

namespace App\Services\Commercial;

use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPayment;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Support\Commercial\OptionalCommission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comissão proporcional ao recebido — fonte única dos cálculos (backend e telas).
 *
 * - Teto (amount_cents) = round(percent × soma das parcelas não canceladas).
 * - Liberável = floor(percent × soma recebida) − já pago, nunca negativo e nunca acima do teto.
 *   Venda quitada: liberável = teto − já pago (absorve o arredondamento).
 * - Status: a_pagar (nada pago), parcial (0 < pago < teto), paga (pago ≥ teto).
 * - Pago acima do teto (ex.: parcela cancelada depois do repasse) só é sinalizado.
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
     * @param  Collection<int, CommercialSaleInstallment>  $installments
     */
    public static function releasableCents(
        CommercialCommission $commission,
        CommercialSale $sale,
        Collection $installments,
    ): int {
        $amount = (int) $commission->amount_cents;
        $paid = (int) $commission->paid_cents;
        $remaining = max(0, $amount - $paid);

        if ($sale->status === CommercialSale::STATUS_QUITADA) {
            return $remaining;
        }

        $earned = self::earnedOnReceivedCents(self::receivedCents($installments), (float) $commission->percent);

        return min($remaining, max(0, $earned - $paid));
    }

    /**
     * Perda assumida pela empresa: Σ (pago − teto) das comissões pagas acima do teto.
     *
     * @param  Builder<CommercialCommission>  $query
     * @return array{loss_assumed_cents: int, loss_assumed_count: int}
     */
    public static function lossAssumedTotals(Builder $query): array
    {
        $inLoss = (clone $query)->whereColumn('paid_cents', '>', 'amount_cents');

        return [
            'loss_assumed_cents' => (int) (clone $inLoss)->sum(DB::raw('paid_cents - amount_cents')),
            'loss_assumed_count' => (int) (clone $inLoss)->count(),
        ];
    }

    /**
     * Totais em aberto de um conjunto de comissões (KPI do Resumo e cartões da tela de Comissões).
     * Pendente = Σ max(0, teto − pago); liberável = Σ liberável agora.
     *
     * @param  Builder<CommercialCommission>  $query
     * @return array{pending_cents: int, releasable_cents: int, pending_count: int}
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

        return [
            'pending_cents' => (int) (clone $open)->sum(DB::raw('amount_cents - paid_cents')),
            'releasable_cents' => $releasable,
            'pending_count' => (int) (clone $open)->count(),
        ];
    }

    /**
     * Payload único para as telas (Comissões e Detalhe da venda). Espera sale.installments,
     * seller e payments.payer carregados.
     *
     * @return array<string, mixed>
     */
    public static function present(CommercialCommission $commission, bool $canDeletePayment = false): array
    {
        $sale = $commission->sale;
        $installments = $sale?->installments ?? collect();
        $amount = (int) $commission->amount_cents;
        $paid = (int) $commission->paid_cents;
        $payments = ($commission->payments ?? collect())->sortBy('id')->values();
        $latestId = $payments->max('id');

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
            'remaining_cents' => max(0, $amount - $paid),
            'releasable_cents' => $sale ? self::releasableCents($commission, $sale, $installments) : 0,
            // Teto abaixo do já pago: a empresa assume a diferença (sem cobrança do vendedor).
            'loss_assumed' => $paid > $amount,
            'loss_assumed_cents' => max(0, $paid - $amount),
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
                'received_cents' => self::receivedCents($installments),
            ] : null,
            'payments' => $payments->map(fn (CommercialCommissionPayment $p) => [
                'id' => (int) $p->id,
                'amount_cents' => (int) $p->amount_cents,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'notes' => $p->notes,
                'paid_by' => $p->payer?->name,
                // Sem conta a pagar vinculada = pagamento anterior à Fase 2, sem movimento de saldo.
                'is_legacy' => $p->payable === null,
                'bank_account' => $p->payable?->bankAccount?->name,
                'can_delete' => $canDeletePayment && (int) $p->id === (int) $latestId,
            ])->values()->all(),
        ];
    }
}
