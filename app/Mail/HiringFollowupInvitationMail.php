<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Convite do acompanhamento pós-contratação (D15/D30/D60/D90) ao colaborador ou ao gestor, com o link individual.
 * Só sai por ação de uma pessoa; conta no limite diário de e-mails de Contratação.
 */
class HiringFollowupInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.hiring-followup-invitation',
            text: 'mail.hiring-followup-invitation-text',
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
