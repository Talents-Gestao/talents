<?php

declare(strict_types=1);

namespace App\Support\Commercial;

use Illuminate\Http\Request;

/**
 * Visão Kanban da lista de propostas: colunas = list_status canónico do Talents.
 */
final class ProposalKanbanBoard
{
    public const VIEW_LIST = 'list';

    public const VIEW_KANBAN = 'kanban';

    /** Máximo de cards carregados por coluna (header mostra contagem total). */
    public const PER_COLUMN_LIMIT = 100;

    /** Dias sem atualização para marcar proposta aberta como estagnada. */
    public const STAGNANT_DAYS = 30;

    /** @return list<string> */
    public static function views(): array
    {
        return [self::VIEW_LIST, self::VIEW_KANBAN];
    }

    public static function viewFromRequest(Request $request): string
    {
        $view = (string) $request->input('view', self::VIEW_KANBAN);

        return in_array($view, self::views(), true) ? $view : self::VIEW_KANBAN;
    }

    /**
     * @return list<array{key: string, filter: string, label: string}>
     */
    public static function columns(): array
    {
        return [
            [
                'key' => ProposalListStatus::OPEN,
                'filter' => 'abertas',
                'label' => ProposalListStatus::label(ProposalListStatus::OPEN),
            ],
            [
                'key' => ProposalListStatus::CLOSED,
                'filter' => 'fechadas',
                'label' => ProposalListStatus::label(ProposalListStatus::CLOSED),
            ],
            [
                'key' => ProposalListStatus::ENDED,
                'filter' => 'perdidas',
                'label' => ProposalListStatus::label(ProposalListStatus::ENDED),
            ],
        ];
    }
}
