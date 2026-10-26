<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Convite do revisor externo (PASSEG) para definir a senha. O acesso aos documentos só abre quando a revisão externa
 * estiver ligada (depois do MFA); o e-mail diz isso.
 */
class ExternalReviewerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $setPasswordUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Convite — revisão de documentos NR-1 (Talents)');
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.external-reviewer-invitation',
            text: 'mail.external-reviewer-invitation-text',
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
