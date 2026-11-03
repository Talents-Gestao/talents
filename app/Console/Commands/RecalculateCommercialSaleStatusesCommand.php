<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Commercial\RefreshCommissionPayout;
use App\Models\CommercialCommission;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Services\Commercial\CommissionPayoutCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reaplica nas vendas existentes as regras:
 * - parcelas canceladas não entram no total nem no pago da venda;
 * - comissão: teto = percent × total sem canceladas; paid_cents/status derivados dos pagamentos.
 *
 * Por padrão só lista o que mudaria (dry-run); grava apenas com --apply.
 * O valor da venda só é recalculado nas vendas que têm parcela cancelada,
 * para não mexer em vendas antigas cujo total diverge por outro motivo.
 */
class RecalculateCommercialSaleStatusesCommand extends Command
{
    protected $signature = 'commercial:recalculate-sale-statuses
        {--apply : Grava as alterações (sem esta opção, apenas simula)}';

    protected $description = 'Recalcula status/valor das vendas e o teto das comissões (dry-run por padrão)';

    public function handle(RefreshCommissionPayout $refresh): int
    {
        $apply = (bool) $this->option('apply');
        $saleChanges = [];
        $commissionChanges = [];

        CommercialSale::query()
            ->where('status', '!=', CommercialSale::STATUS_CANCELADA)
            ->whereHas('installments')
            ->with(['installments', 'commissions'])
            ->orderBy('id')
            ->chunkById(200, function ($sales) use (&$saleChanges, &$commissionChanges): void {
                foreach ($sales as $sale) {
                    $change = $this->saleDiff($sale);
                    if ($change !== null) {
                        $saleChanges[] = $change;
                    }

                    $totalTo = $change['total_to'] ?? (int) $sale->total_cents;
                    foreach ($sale->commissions as $commission) {
                        $commissionChange = $this->commissionDiff($sale, $commission, $totalTo);
                        if ($commissionChange !== null) {
                            $commissionChanges[] = $commissionChange;
                        }
                    }
                }
            });

        if ($saleChanges === [] && $commissionChanges === []) {
            $this->info('Nenhuma venda precisa de ajuste.');

            return self::SUCCESS;
        }

        if ($saleChanges !== []) {
            $this->table(
                ['ID', 'Código', 'Status atual', 'Status novo', 'Valor atual', 'Valor novo'],
                array_map(fn (array $c) => [
                    $c['id'],
                    $c['code'],
                    $c['status_from'],
                    $c['status_to'],
                    $this->money($c['total_from']),
                    $this->money($c['total_to']),
                ], $saleChanges),
            );

            $toQuitada = count(array_filter(
                $saleChanges,
                fn (array $c) => $c['status_to'] === CommercialSale::STATUS_QUITADA && $c['status_from'] !== CommercialSale::STATUS_QUITADA,
            ));
            $this->line(count($saleChanges)." venda(s) com ajuste; {$toQuitada} passariam a «quitada».");
        }

        if ($commissionChanges !== []) {
            $this->table(
                ['Comissão', 'Venda', 'Teto atual', 'Teto novo', 'Pago', 'Status atual', 'Status novo'],
                array_map(fn (array $c) => [
                    $c['id'],
                    $c['sale_code'],
                    $this->money($c['amount_from']),
                    $this->money($c['amount_to']),
                    $this->money($c['paid_to']),
                    $c['status_from'],
                    $c['status_to'],
                ], $commissionChanges),
            );
            $this->line(count($commissionChanges).' comissão(ões) com ajuste.');
        }

        if (! $apply) {
            $this->warn('Dry-run: nada foi gravado. Rode com --apply para aplicar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($saleChanges, $commissionChanges, $refresh): void {
            foreach ($saleChanges as $c) {
                CommercialSale::query()->whereKey($c['id'])->update([
                    'status' => $c['status_to'],
                    'total_cents' => $c['total_to'],
                    'updated_at' => now(),
                ]);
            }

            $saleIds = array_unique(array_column($commissionChanges, 'sale_id'));
            CommercialSale::query()->whereKey($saleIds)->get()
                ->each(fn (CommercialSale $sale) => $refresh->forSale($sale));
        });

        $this->info(count($saleChanges).' venda(s) e '.count($commissionChanges).' comissão(ões) atualizada(s).');

        return self::SUCCESS;
    }

    /**
     * @return array{id: int, code: string, status_from: string, status_to: string, total_from: int, total_to: int}|null
     */
    private function saleDiff(CommercialSale $sale): ?array
    {
        $installments = $sale->installments;
        $statusTo = CommercialSale::statusFromInstallments($installments) ?? (string) $sale->status;

        $hasCancelled = $installments->contains('status', CommercialSaleInstallment::STATUS_CANCELADO);
        $totalFrom = (int) $sale->total_cents;
        $totalTo = $hasCancelled ? CommercialSale::totalFromInstallments($installments) : $totalFrom;

        if ($statusTo === $sale->status && $totalTo === $totalFrom) {
            return null;
        }

        return [
            'id' => (int) $sale->id,
            'code' => (string) $sale->code,
            'status_from' => (string) $sale->status,
            'status_to' => $statusTo,
            'total_from' => $totalFrom,
            'total_to' => $totalTo,
        ];
    }

    /**
     * Mesma regra de RefreshCommissionPayout, sem gravar.
     *
     * @return array{id: int, sale_id: int, sale_code: string, amount_from: int, amount_to: int, paid_to: int, status_from: string, status_to: string}|null
     */
    private function commissionDiff(CommercialSale $sale, CommercialCommission $commission, int $saleTotalCents): ?array
    {
        $installments = $sale->installments;
        $ceilingBase = $installments->isEmpty() ? $saleTotalCents : CommercialSale::totalFromInstallments($installments);
        $percent = (float) $commission->percent;

        $amountFrom = (int) $commission->amount_cents;
        $amountTo = $percent > 0 ? CommissionPayoutCalculator::ceilingCents($ceilingBase, $percent) : $amountFrom;
        $baseChanged = $percent > 0 && (int) $commission->base_cents !== $ceilingBase;
        $paidTo = (int) $commission->payments()->sum('amount_cents');
        $statusTo = CommissionPayoutCalculator::statusFor($paidTo, $amountTo);

        if ($amountTo === $amountFrom && ! $baseChanged && $paidTo === (int) $commission->paid_cents && $statusTo === $commission->status) {
            return null;
        }

        return [
            'id' => (int) $commission->id,
            'sale_id' => (int) $sale->id,
            'sale_code' => (string) $sale->code,
            'amount_from' => $amountFrom,
            'amount_to' => $amountTo,
            'paid_to' => $paidTo,
            'status_from' => (string) $commission->status,
            'status_to' => $statusTo,
        ];
    }

    private function money(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
