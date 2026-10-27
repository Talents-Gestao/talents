<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso ao usuário de que a verificação em duas etapas dele foi resetada.
 */
class MfaResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $by,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sua verificação em duas etapas foi resetada — Talents');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.mfa-reset-text');
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
