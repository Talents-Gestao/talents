<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de segurança ao próprio usuário sobre a verificação em duas etapas dele: uso de código de recuperação, nova
 * inscrição ou geração de novos códigos. Nunca leva códigos nem segredos.
 */
class MfaSecurityMail extends Mailable
{
    use Queueable, SerializesModels;

    public const EVENTS = [
        'recovery_used' => ['subject' => 'Um código de recuperação foi usado na sua conta — Talents', 'what' => 'Um código de recuperação foi usado para entrar na sua conta.'],
        'enrolled' => ['subject' => 'Verificação em duas etapas ativada — Talents', 'what' => 'A verificação em duas etapas da sua conta foi ativada com um novo aplicativo autenticador.'],
        'codes_regenerated' => ['subject' => 'Novos códigos de recuperação gerados — Talents', 'what' => 'Novos códigos de recuperação foram gerados; os anteriores deixaram de valer.'],
    ];

    public function __construct(
        public User $user,
        public string $event,
        public ?string $ip,
        public string $when,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::EVENTS[$this->event]['subject']);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.mfa-security-text', with: ['what' => self::EVENTS[$this->event]['what']]);
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
