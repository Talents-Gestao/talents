<?php

namespace App\Actions\Tasks;

use App\Models\TaskActivityLog;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\User;

final class LogTaskActivity
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function handle(
        TaskBoard $board,
        ?TaskCard $card,
        string $action,
        ?User $actor = null,
        ?array $payload = null,
    ): TaskActivityLog {
        return TaskActivityLog::query()->create([
            'board_id' => $board->id,
            'task_card_id' => $card?->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
