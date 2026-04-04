<?php

namespace App\Mail;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplaintReporterNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Complaint $complaint,
        public string $eventType,
        public array $meta = [],
    ) {}

    public function envelope(): Envelope
    {
        $this->complaint->loadMissing('company');
        $companyName = $this->complaint->company->name;

        $subject = match ($this->eventType) {
            'status' => 'Atualização de status — '.$companyName,
            'message' => 'Nova resposta da empresa — '.$companyName,
            default => 'Atualização no protocolo — '.$companyName,
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.complaint-reporter-notification',
            text: 'mail.complaint-reporter-notification-text',
            with: [
                'trackUrl' => $this->trackUrl(),
                'bodyLine' => $this->bodyLine(),
                'companyName' => $this->complaint->company->name,
                'protocol' => $this->complaint->protocol,
            ],
        );
    }

    public function trackUrl(): string
    {
        $this->complaint->loadMissing('company');
        $token = $this->complaint->company->complaints_public_token;

        return route('denuncia.protocol', [
            'token' => $token,
            'protocol' => $this->complaint->protocol,
        ], absolute: true);
    }

    public function bodyLine(): string
    {
        return match ($this->eventType) {
            'status' => 'O status do seu protocolo foi atualizado para: '.$this->statusLabel($this->meta['status'] ?? ''),
            'message' => 'A empresa enviou uma nova resposta no seu protocolo. Use o link abaixo para acompanhar.',
            default => 'Há uma atualização no seu protocolo.',
        };
    }

    private function statusLabel(string $s): string
    {
        return match ($s) {
            'new' => 'Nova',
            'under_review' => 'Em análise',
            'resolved' => 'Resolvida',
            'archived' => 'Arquivada',
            default => $s,
        };
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
