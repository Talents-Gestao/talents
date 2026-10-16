<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ClientEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientEvaluationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientEvaluation $evaluation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sua avaliação sobre a Talents',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.client-evaluation-invitation',
            text: 'mail.client-evaluation-invitation-text',
        );
    }
}
