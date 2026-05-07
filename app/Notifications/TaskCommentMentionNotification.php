<?php

namespace App\Notifications;

use App\Models\TaskCard;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskCommentMentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TaskComment $comment,
        public TaskCard $card,
        public User $mentioner,
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
            ->subject('Talents — Mencionou-o num comentário em Tarefas')
            ->line($this->mentioner->name.' mencionou-o no cartão «'.$this->card->title.'».')
            ->line('Comentário: '.str($this->comment->body)->limit(200))
            ->action('Ver quadro', $url);
    }
}
