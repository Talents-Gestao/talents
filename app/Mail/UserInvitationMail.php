<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Company $company,
        public string $resetPasswordUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Convite — portal Talents ('.$this->company->name.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.user-invitation',
            text: 'mail.user-invitation-text',
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
