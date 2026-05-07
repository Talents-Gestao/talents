<?php

namespace App\Notifications;

use App\Models\TaskCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDueReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TaskCard $card,
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
        $due = $this->card->due_date?->format('d/m/Y') ?? '';
        $url = url('/client/tarefas/'.$boardId);

        return (new MailMessage)
            ->subject('Talents — Lembrete: cartão com vencimento próximo')
            ->line('O cartão «'.$this->card->title.'» tem data prevista em '.$due.'.')
            ->action('Abrir quadro', $url);
    }
}
