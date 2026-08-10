<?php

declare(strict_types=1);

namespace App\Support\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use Illuminate\Database\Eloquent\Builder;

/**
 * Status visual da lista de propostas (derivado de is_closed + venda/parcelas).
 * Não persiste na proposta — ver CommercialSale::recalculateStatus() para o financeiro.
 */
final class ProposalListStatus
{
    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const CLOSED = 'closed';

    /**
     * Prioridade v1: parcial → Em andamento; quitada → Fechada; is_closed → Fechada; senão → Em aberto.
     */
    public static function for(CommercialProposal $proposal): string
    {
        $saleStatus = $proposal->relationLoaded('sale')
            ? $proposal->sale?->status
            : $proposal->sale()->value('status');

        if ($saleStatus === CommercialSale::STATUS_PARCIAL) {
            return self::IN_PROGRESS;
        }

        if ($saleStatus === CommercialSale::STATUS_QUITADA) {
            return self::CLOSED;
        }

        if ($proposal->is_closed) {
            return self::CLOSED;
        }

        return self::OPEN;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::IN_PROGRESS => 'Em andamento',
            self::CLOSED => 'Fechada',
            default => 'Em aberto',
        };
    }

    public static function labelFor(CommercialProposal $proposal): string
    {
        return self::label(self::for($proposal));
    }

    /**
     * @param  Builder<CommercialProposal>  $query
     * @return Builder<CommercialProposal>
     */
    public static function applyFilter(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'em_andamento' => $query->whereHas(
                'sale',
                fn (Builder $sale) => $sale->where('status', CommercialSale::STATUS_PARCIAL),
            ),
            'fechadas' => $query->where(function (Builder $q): void {
                $q->whereHas(
                    'sale',
                    fn (Builder $sale) => $sale->where('status', CommercialSale::STATUS_QUITADA),
                )->orWhere(function (Builder $closed): void {
                    $closed->where('is_closed', true)
                        ->whereDoesntHave(
                            'sale',
                            fn (Builder $sale) => $sale->where('status', CommercialSale::STATUS_PARCIAL),
                        );
                });
            }),
            'abertas' => $query->where('is_closed', false)
                ->where(function (Builder $q): void {
                    $q->whereDoesntHave('sale')
                        ->orWhereHas(
                            'sale',
                            fn (Builder $sale) => $sale->whereNotIn('status', [
                                CommercialSale::STATUS_PARCIAL,
                                CommercialSale::STATUS_QUITADA,
                            ]),
                        );
                }),
            default => $query,
        };
    }
}
