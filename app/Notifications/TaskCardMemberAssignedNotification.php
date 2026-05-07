<?php

namespace App\Notifications;

use App\Models\TaskCard;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskCardMemberAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TaskCard $card,
        public User $assignedBy,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->card->loadMissing('list.board');
        $board = $this->card->list?->board;
        $boardId = $board?->id ?? 0;
        $url = $board && $board->company_id
            ? url('/client/tarefas/'.$boardId)
            : url('/admin/tarefas/quadros/'.$boardId);

        return (new MailMessage)
            ->subject('Talents — Você foi atribuído a um cartão')
            ->line($this->assignedBy->name.' atribuiu-lhe o cartão «'.$this->card->title.'».')
            ->action('Abrir quadro', $url);
    }
}
