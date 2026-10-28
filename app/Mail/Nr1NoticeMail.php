<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail do aviso do NR-1 (nova oportunidade, nova solicitação, relatório publicado). Só texto do aviso e o endereço do
 * sistema: nenhum dado de resposta.
 */
class Nr1NoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $noticeTitle,
        public string $noticeBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->noticeTitle.' — Talents');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.nr1-notice-text');
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
