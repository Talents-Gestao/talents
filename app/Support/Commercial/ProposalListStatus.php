<?php

declare(strict_types=1);

namespace App\Support\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use Illuminate\Database\Eloquent\Builder;

/**
 * Status da lista de propostas: persistido em commercial_proposals.list_status.
 * Fallback legado (coluna null): deriva de is_closed + status da venda.
 */
final class ProposalListStatus
{
    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const CLOSED = 'closed';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::OPEN, self::IN_PROGRESS, self::CLOSED];
    }

    public static function for(CommercialProposal $proposal): string
    {
        $stored = $proposal->list_status;
        if (is_string($stored) && in_array($stored, self::values(), true)) {
            return $stored;
        }

        return self::legacyFor($proposal);
    }

    /**
     * Lógica anterior à coluna list_status (registos legados com null).
     */
    public static function legacyFor(CommercialProposal $proposal): string
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

    public static function impliesClosed(string $status): bool
    {
        return $status === self::CLOSED || $status === self::IN_PROGRESS;
    }

    /**
     * @param  Builder<CommercialProposal>  $query
     * @return Builder<CommercialProposal>
     */
    public static function applyFilter(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'em_andamento' => $query->where(function (Builder $q): void {
                $q->where('list_status', self::IN_PROGRESS)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('list_status')
                            ->whereHas(
                                'sale',
                                fn (Builder $sale) => $sale->where('status', CommercialSale::STATUS_PARCIAL),
                            );
                    });
            }),
            'fechadas' => $query->where(function (Builder $q): void {
                $q->where('list_status', self::CLOSED)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('list_status')
                            ->where(function (Builder $inner): void {
                                $inner->whereHas(
                                    'sale',
                                    fn (Builder $sale) => $sale->where('status', CommercialSale::STATUS_QUITADA),
                                )->orWhere(function (Builder $closed): void {
                                    $closed->where('is_closed', true)
                                        ->whereDoesntHave(
                                            'sale',
                                            fn (Builder $sale) => $sale->where('status', CommercialSale::STATUS_PARCIAL),
                                        );
                                });
                            });
                    });
            }),
            'abertas' => $query->where(function (Builder $q): void {
                $q->where('list_status', self::OPEN)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('list_status')
                            ->where('is_closed', false)
                            ->where(function (Builder $inner): void {
                                $inner->whereDoesntHave('sale')
                                    ->orWhereHas(
                                        'sale',
                                        fn (Builder $sale) => $sale->whereNotIn('status', [
                                            CommercialSale::STATUS_PARCIAL,
                                            CommercialSale::STATUS_QUITADA,
                                        ]),
                                    );
                            });
                    });
            }),
            default => $query,
        };
    }
}
