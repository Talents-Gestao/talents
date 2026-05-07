<?php

namespace App\Jobs;

use App\Models\TaskCard;
use App\Notifications\TaskDueReminderNotification;
use App\Support\Tasks\TaskCardVisibility;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class RemindUpcomingTaskDueDatesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        TaskCard::query()
            ->whereDate('due_date', $tomorrow)
            ->whereNull('completed_at')
            ->where('is_archived', false)
            ->with(['list.board', 'members'])
            ->chunkById(100, function ($cards): void {
                foreach ($cards as $card) {
                    $board = $card->list?->board;
                    if (! $board || ! $board->company_id) {
                        continue;
                    }
                    if (! TaskCardVisibility::isVisibleToCompany($card)) {
                        continue;
                    }
                    $recipients = $card->members;
                    if ($recipients->isEmpty()) {
                        continue;
                    }
                    Notification::send($recipients, new TaskDueReminderNotification($card));
                }
            });
    }
}
