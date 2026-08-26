<?php

declare(strict_types=1);

namespace App\Support\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use Illuminate\Database\Eloquent\Builder;

/**
 * Status da lista de propostas: persistido em commercial_proposals.list_status.
 * Valores canónicos: open (Aberta), closed (Fechada), ended (Perdida).
 * Fallback legado (coluna null): deriva de is_closed + status da venda.
 */
final class ProposalListStatus
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const ENDED = 'ended';

    /**
     * @deprecated Use CLOSED. Mantido para referências legadas em código/testes.
     */
    public const APPROVED = 'approved';

    /**
     * @deprecated Negociação foi absorvida por Aberta.
     */
    public const NEGOTIATION = 'negotiation';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::OPEN, self::CLOSED, self::ENDED];
    }

    /** @return list<string> */
    public static function filterKeys(): array
    {
        return ['abertas', 'fechadas', 'perdidas'];
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
     * Normaliza slugs antigos para os três status canónicos.
     */
    public static function normalize(string $status): string
    {
        return match ($status) {
            'in_progress', 'negotiation' => self::OPEN,
            'approved', 'closed' => self::CLOSED,
            'ended' => self::ENDED,
            default => $status,
        };
    }

    /**
     * Lógica anterior à coluna list_status (registros legados com null).
     */
    public static function legacyFor(CommercialProposal $proposal): string
    {
        $saleStatus = $proposal->relationLoaded('sale')
            ? $proposal->sale?->status
            : $proposal->sale()->value('status');

        if ($saleStatus === CommercialSale::STATUS_QUITADA || $saleStatus === CommercialSale::STATUS_PARCIAL) {
            return self::CLOSED;
        }

        if ($proposal->is_closed) {
            return self::CLOSED;
        }

        return self::OPEN;
    }

    public static function label(string $status): string
    {
        return match (self::normalize($status)) {
            self::CLOSED => 'Fechada',
            self::ENDED => 'Perdida',
            default => 'Aberta',
        };
    }

    public static function labelFor(CommercialProposal $proposal): string
    {
        return self::label(self::for($proposal));
    }

    /**
     * «Fechada» marca is_closed (pronta para venda / contratos fechados).
     */
    public static function impliesClosed(string $status): bool
    {
        return self::normalize($status) === self::CLOSED;
    }

    public static function canConvert(string $status): bool
    {
        return self::normalize($status) === self::CLOSED;
    }

    /**
     * @param  Builder<CommercialProposal>  $query
     * @return Builder<CommercialProposal>
     */
    public static function applyFilter(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'fechadas', 'aprovadas' => $query->where(function (Builder $q): void {
                $q->whereIn('list_status', [self::CLOSED, self::APPROVED, 'closed'])
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('list_status')
                            ->where(function (Builder $inner): void {
                                $inner->whereHas(
                                    'sale',
                                    fn (Builder $sale) => $sale->whereIn('status', [
                                        CommercialSale::STATUS_QUITADA,
                                        CommercialSale::STATUS_PARCIAL,
                                    ]),
                                )->orWhere(function (Builder $closed): void {
                                    $closed->where('is_closed', true);
                                });
                            });
                    });
            }),
            'perdidas', 'encerradas' => $query->where('list_status', self::ENDED),
            'abertas', 'em_negociacao', 'em_andamento' => $query->where(function (Builder $q): void {
                $q->whereIn('list_status', [self::OPEN, self::NEGOTIATION, 'in_progress'])
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
     * Exclui propostas perdidas (list_status = ended).
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
