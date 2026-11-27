<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Comunicação da Talents ao contato da empresa sobre uma vaga: atualização de etapa (só vaga, etapa e
 * quantidade de candidatos; nunca nome nem dado de candidato) ou envio do dossiê aprovado (PDF anexado).
 * O texto é o revisado na tela, sem acréscimos.
 */
class HiringCommunicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public ?string $attachmentData = null,
        public ?string $attachmentName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.hiring-communication',
            text: 'mail.hiring-communication-text',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->attachmentData === null || $this->attachmentName === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->attachmentData, $this->attachmentName)->withMime('application/pdf'),
        ];
    }
}
