<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\FeedbackSession;
use App\Models\FeedbackSessionSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackSignatureInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FeedbackSession $session,
        public FeedbackSessionSignature $signature,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Assinatura de feedback — '.$this->session->employee?->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.feedback-signature-invitation',
            text: 'mail.feedback-signature-invitation-text',
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
