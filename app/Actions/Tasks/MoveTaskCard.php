<?php

namespace App\Actions\Tasks;

use App\Models\TaskCard;
use App\Models\TaskList;
use App\Models\User;
use App\Support\Tasks\TaskCardVisibility;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class MoveTaskCard
{
    public function __construct(
        private LogTaskActivity $logTaskActivity,
    ) {}

    public function handle(
        TaskCard $card,
        TaskList $targetList,
        float $position,
        User $actor,
        bool $actorIsSuperAdmin,
    ): TaskCard {
        return DB::transaction(function () use ($card, $targetList, $position, $actor, $actorIsSuperAdmin) {
            $card->loadMissing(['list.board']);
            $sourceList = $card->list;
            if (! $sourceList) {
                throw new AuthorizationException('Lista inválida.');
            }

            $board = $sourceList->board;
            if (! $board || $board->id !== $targetList->board_id) {
                throw new AuthorizationException('Destino inválido.');
            }

            if (! $actorIsSuperAdmin) {
                if ($board->company_id !== $actor->company_id) {
                    throw new AuthorizationException('Acesso negado.');
                }
                if (! TaskCardVisibility::isVisibleToCompany($card)) {
                    throw new AuthorizationException('Cartão não visível.');
                }
                if (! TaskCardVisibility::companyMayMoveBetween($sourceList, $targetList)) {
                    throw new AuthorizationException('Movimento não permitido.');
                }
            }

            $fromListId = $card->list_id;
            $card->update([
                'list_id' => $targetList->id,
                'position' => $position,
            ]);

            $this->logTaskActivity->handle($board, $card, 'card.moved', $actor, [
                'from_list_id' => $fromListId,
                'to_list_id' => $targetList->id,
                'position' => $position,
            ]);

            return $card->fresh(['list']);
        });
    }
}
