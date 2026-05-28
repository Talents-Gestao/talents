<?php

namespace App\Actions\Tasks;

use App\Models\TaskCard;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ToggleTaskCardCompletion
{
    public function __construct(
        private LogTaskActivity $logTaskActivity,
        private MoveTaskCard $moveTaskCard,
    ) {}

    public function handle(TaskCard $card, bool $completed, User $actor): TaskCard
    {
        return DB::transaction(function () use ($card, $completed, $actor) {
            $card->loadMissing(['list.board']);
            $board = $card->list?->board;
            if (! $board) {
                return $card;
            }

            if ($completed) {
                $card->update(['completed_at' => now()]);

                $doneList = $this->findConcluidoList($board->id);
                if ($doneList && (int) $card->list_id !== (int) $doneList->id) {
                    $max = (float) $doneList->cards()->max('position');
                    $this->moveTaskCard->handle(
                        $card->fresh(),
                        $doneList,
                        $max + 1000,
                        $actor,
                        true,
                    );
                }

                $this->logTaskActivity->handle($board, $card->fresh(), 'card.completed', $actor, []);

                return $card->fresh(['list']);
            }

            $card->update(['completed_at' => null]);
            $this->logTaskActivity->handle($board, $card->fresh(), 'card.reopened', $actor, []);

            return $card->fresh(['list']);
        });
    }

    private function findConcluidoList(int $boardId): ?TaskList
    {
        return TaskList::query()
            ->where('board_id', $boardId)
            ->where('is_archived', false)
            ->get()
            ->first(fn (TaskList $list) => $this->isConcluidoListName($list->name));
    }

    private function isConcluidoListName(?string $name): bool
    {
        return Str::lower(Str::ascii(trim((string) $name))) === 'concluido';
    }
}
