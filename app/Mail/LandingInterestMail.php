<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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
        $safeName = Str::of($this->submitterName)
            ->replaceMatches('/[\r\n\x00]+/', ' ')
            ->trim()
            ->limit(200)
            ->toString();

        return new Envelope(
            subject: 'Novo interesse na Talents — '.$safeName,
            replyTo: [
                new Address($this->submitterEmail, $safeName !== '' ? $safeName : null),
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
