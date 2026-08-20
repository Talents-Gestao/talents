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

    public const NEGOTIATION = 'negotiation';

    public const APPROVED = 'approved';

    public const ENDED = 'ended';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::OPEN, self::NEGOTIATION, self::APPROVED, self::ENDED];
    }

    /** @return list<string> */
    public static function filterKeys(): array
    {
        return ['abertas', 'em_negociacao', 'aprovadas', 'encerradas'];
    }

    public static function for(CommercialProposal $proposal): string
    {
        $stored = $proposal->list_status;
        if (is_string($stored) && $stored !== '') {
            $normalized = self::normalize($stored);
            if (in_array($normalized, self::values(), true)) {
                return $normalized;
            }
        }

        return self::legacyFor($proposal);
    }

    /**
     * Normaliza slugs antigos (in_progress/closed) para os atuais.
     */
    public static function normalize(string $status): string
    {
        return match ($status) {
            'in_progress' => self::NEGOTIATION,
            'closed' => self::APPROVED,
            default => $status,
        };
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
            return self::NEGOTIATION;
        }

        if ($saleStatus === CommercialSale::STATUS_QUITADA) {
            return self::APPROVED;
        }

        if ($proposal->is_closed) {
            return self::APPROVED;
        }

        return self::OPEN;
    }

    public static function label(string $status): string
    {
        return match (self::normalize($status)) {
            self::NEGOTIATION => 'Em negociação',
            self::APPROVED => 'Aprovada',
            self::ENDED => 'Encerrada',
            default => 'Em aberto',
        };
    }

    public static function labelFor(CommercialProposal $proposal): string
    {
        return self::label(self::for($proposal));
    }

    /**
     * Apenas «Aprovada» marca is_closed (pronta para venda / contratos fechados).
     */
    public static function impliesClosed(string $status): bool
    {
        return self::normalize($status) === self::APPROVED;
    }

    public static function canConvert(string $status): bool
    {
        return self::normalize($status) === self::APPROVED;
    }

    /**
     * @param  Builder<CommercialProposal>  $query
     * @return Builder<CommercialProposal>
     */
    public static function applyFilter(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'em_negociacao', 'em_andamento' => $query->where(function (Builder $q): void {
                $q->whereIn('list_status', [self::NEGOTIATION, 'in_progress'])
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('list_status')
                            ->whereHas(
                                'sale',
                                fn (Builder $sale) => $sale->where('status', CommercialSale::STATUS_PARCIAL),
                            );
                    });
            }),
            'aprovadas', 'fechadas' => $query->where(function (Builder $q): void {
                $q->whereIn('list_status', [self::APPROVED, 'closed'])
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
            'encerradas' => $query->where('list_status', self::ENDED),
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

    /**
     * Exclui propostas encerradas (list_status = ended).
     * Inclui list_status nulo (legado) — em SQL, NULL != 'ended' não as traria.
     *
     * @param  Builder<CommercialProposal>  $query
     * @return Builder<CommercialProposal>
     */
    public static function excludeEnded(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('list_status')
                ->orWhere('list_status', '!=', self::ENDED);
        });
    }
}
