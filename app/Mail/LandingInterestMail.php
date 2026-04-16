<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LandingInterestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $submitterName,
        public string $submitterEmail,
        public ?string $company,
        public ?string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Novo interesse na Talents — '.$this->submitterName,
            replyTo: [
                new Address($this->submitterEmail, $this->submitterName),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.landing-interest',
            text: 'mail.landing-interest-text',
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
