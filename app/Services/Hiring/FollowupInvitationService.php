<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\CompanyContactTopic;
use App\Enums\EmploymentCycleEventType;
use App\Enums\EmploymentCycleStatus;
use App\Enums\FollowupAudience;
use App\Enums\HiringCommunicationChannel;
use App\Mail\HiringFollowupInvitationMail;
use App\Models\EmploymentCycle;
use App\Models\EmploymentCycleEvent;
use App\Models\HiringFollowupInvitation;
use App\Models\HiringFollowupMilestone;
use App\Models\MailSetting;
use App\Support\Hiring\CompanyContactEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «Enviar acompanhamento» de um marco: destino Colaborador, Gestor ou Ambos; canal E-mail ou Copiar link e mensagem.
 * - cada destino é avaliado sozinho: sem elegibilidade, ele é pulado com o motivo e o outro segue (envio parcial explícito);
 * - um convite válido por marco e destino: tentar de novo reaproveita; destinatário diferente revoga o antigo e gera outro;
 * - e-mail pelo SMTP do painel, no mesmo limite diário de Contratação (HiringCommunicationService::DAILY_EMAIL_LIMIT);
 * - copiar registra copied_at e nunca emailed_at. Nada é enviado sem ação de uma pessoa.
 */
final class FollowupInvitationService
{
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_COPY = 'copy';

    public function __construct(
        private readonly HiringCommunicationService $communications,
    ) {}

    /**
     * @return list<array{audience: string, audience_label: string, outcome: string, reason: ?string, invitation_id: ?int, link: ?string, message: ?string, copy_url: ?string}>
     */
    public function send(HiringFollowupMilestone $milestone, string $target, string $channel, ?int $userId): array
    {
        $cycle = $milestone->cycle()->with(['employee:id,name,company_id', 'company:id,name', 'manager'])->firstOrFail();

        $blocked = match ($cycle->status) {
            EmploymentCycleStatus::PendingAdmission => 'Confirme a admissão antes de enviar o acompanhamento.',
            EmploymentCycleStatus::Paused => 'O acompanhamento está pausado. Retome-o para enviar.',
            EmploymentCycleStatus::Terminated => 'O vínculo foi encerrado; não há acompanhamento a enviar.',
            default => null,
        };
        if ($blocked !== null) {
            throw ValidationException::withMessages(['followup' => $blocked]);
        }
        if ($milestone->skipped_at !== null) {
            throw ValidationException::withMessages(['followup' => 'Este marco foi pulado.']);
        }

        $audiences = match ($target) {
            'collaborator' => [FollowupAudience::Collaborator],
            'manager' => [FollowupAudience::Manager],
            'both' => [FollowupAudience::Collaborator, FollowupAudience::Manager],
            default => throw ValidationException::withMessages(['target' => 'Escolha o destino do acompanhamento.']),
        };

        // Cada destino sozinho: inelegível é pulado com o motivo, sem travar o outro.
        $plans = [];
        foreach ($audiences as $audience) {
            $recipient = $this->recipient($cycle, $audience);
            $reason = $this->ineligibilityReason($cycle, $audience, $channel);
            $plans[] = ['audience' => $audience, 'recipient' => $recipient, 'reason' => $reason];
        }

        $emailCount = 0;
        if ($channel === self::CHANNEL_EMAIL) {
            foreach ($plans as $i => $plan) {
                if ($plan['reason'] !== null) {
                    continue;
                }
                $current = $this->usableInvitation($milestone, $plan['audience']);
                if ($current?->answered_at !== null) {
                    continue;
                }
                $emailCount++;
            }
            if ($emailCount > 0 && ! MailSetting::smtpConfigured()) {
                throw ValidationException::withMessages(['channel' => 'Configure e ligue o SMTP em Configuração → Geral para enviar e-mails.']);
            }
            $remaining = $this->communications->emailsRemainingToday();
            if ($emailCount > $remaining) {
                throw ValidationException::withMessages([
                    'limit' => 'Limite de '.HiringCommunicationService::DAILY_EMAIL_LIMIT.' e-mails de Contratação por dia. Hoje restam '.$remaining.'.',
                ]);
            }
        }

        $results = [];
        foreach ($plans as $plan) {
            /** @var FollowupAudience $audience */
            $audience = $plan['audience'];
            $base = ['audience' => $audience->value, 'audience_label' => $audience->label(), 'invitation_id' => null, 'link' => null, 'message' => null, 'copy_url' => null];

            if ($plan['reason'] !== null) {
                $results[] = [...$base, 'outcome' => 'skipped', 'reason' => $plan['reason']];

                continue;
            }

            $invitation = DB::transaction(fn () => $this->invitationFor($milestone, $cycle, $audience, $plan['recipient'], $userId));

            if ($invitation->answered_at !== null) {
                $results[] = [...$base, 'invitation_id' => $invitation->id, 'outcome' => 'already_answered', 'reason' => 'Este destino já respondeu a este marco.'];

                continue;
            }

            $message = $this->message($cycle, $milestone, $invitation);

            if ($channel === self::CHANNEL_COPY) {
                $results[] = [
                    ...$base,
                    'invitation_id' => $invitation->id,
                    'outcome' => 'ready_to_copy',
                    'reason' => null,
                    'link' => $invitation->publicUrl(),
                    'message' => $message,
                    'copy_url' => route('admin.contratacao.pos.invitations.copied', [$cycle, $milestone, $invitation]),
                ];

                continue;
            }

            $results[] = [...$base, ...$this->email($cycle, $milestone, $invitation)];
        }

        return $results;
    }

    /** «Copiar»: registra copied_at automaticamente. Nunca toca em emailed_at. */
    public function markCopied(HiringFollowupInvitation $invitation): HiringFollowupInvitation
    {
        if (! $invitation->isUsable()) {
            throw ValidationException::withMessages(['invitation' => 'Este convite não pode mais ser usado.']);
        }

        $invitation->copied_at = now();
        $invitation->save();

        return $invitation;
    }

    /** Revoga os convites ainda utilizáveis do ciclo (ou só de um marco), com o motivo. */
    public function revokeOpenInvitations(EmploymentCycle $cycle, string $reason, ?int $userId, ?HiringFollowupMilestone $only = null): int
    {
        $query = HiringFollowupInvitation::query()
            ->whereIn('milestone_id', $only !== null ? [$only->id] : $cycle->milestones()->pluck('id'))
            ->whereNull('revoked_at')
            ->whereNull('answered_at');

        $count = 0;
        foreach ($query->get() as $invitation) {
            $this->revoke($invitation, $reason, $cycle, $userId);
            $count++;
        }

        return $count;
    }

    /**
     * Motivo para não enviar àquele destino (null = pode).
     */
    public function ineligibilityReason(EmploymentCycle $cycle, FollowupAudience $audience, string $channel): ?string
    {
        if ($audience === FollowupAudience::Collaborator) {
            if (! $cycle->collaborator_email_opt_in) {
                return 'Colaborador sem autorização de e-mail.';
            }
            if ($channel === self::CHANNEL_EMAIL && $cycle->collaborator_email === null) {
                return 'Colaborador sem e-mail.';
            }
            if ($channel === self::CHANNEL_COPY && $cycle->collaborator_email === null && $cycle->collaborator_phone === null) {
                return 'Colaborador sem e-mail nem telefone.';
            }

            return null;
        }

        $manager = $cycle->manager;
        if ($manager === null) {
            return 'Gestor não definido.';
        }

        // E-mail pelo canal e-mail; «Copiar link e mensagem» segue para o WhatsApp do gestor.
        $contactChannel = $channel === self::CHANNEL_EMAIL ? HiringCommunicationChannel::Email : HiringCommunicationChannel::Whatsapp;
        $reasons = CompanyContactEligibility::reasons($manager, CompanyContactTopic::PosContratacao, $contactChannel);
        if ($reasons === []) {
            return null;
        }

        $map = [
            'Contato ainda não conferido.' => 'Gestor sem conferência.',
            'Contato desabilitado.' => 'Gestor desabilitado.',
            'Contato arquivado.' => 'Gestor arquivado.',
            'Contato sem e-mail.' => 'Gestor sem e-mail.',
            'Contato sem telefone.' => 'Gestor sem telefone.',
        ];

        return implode(' ', array_map(
            static fn (string $r) => $map[$r] ?? 'Gestor: '.lcfirst(rtrim($r, '.')).'.',
            $reasons,
        ));
    }

    /**
     * @return array{name: string, email: ?string, phone: ?string}
     */
    private function recipient(EmploymentCycle $cycle, FollowupAudience $audience): array
    {
        if ($audience === FollowupAudience::Collaborator) {
            return ['name' => (string) $cycle->employee?->name, 'email' => $cycle->collaborator_email, 'phone' => $cycle->collaborator_phone];
        }

        return ['name' => (string) $cycle->manager?->name, 'email' => $cycle->manager?->email, 'phone' => $cycle->manager?->phone];
    }

    private function usableInvitation(HiringFollowupMilestone $milestone, FollowupAudience $audience): ?HiringFollowupInvitation
    {
        return HiringFollowupInvitation::query()
            ->where('milestone_id', $milestone->id)
            ->where('audience', $audience->value)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNotNull('answered_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
    }

    /**
     * Reaproveita o convite válido do marco e destino; se o destinatário mudou, revoga o antigo e gera outro.
     *
     * @param  array{name: string, email: ?string, phone: ?string}  $recipient
     */
    private function invitationFor(HiringFollowupMilestone $milestone, EmploymentCycle $cycle, FollowupAudience $audience, array $recipient, ?int $userId): HiringFollowupInvitation
    {
        HiringFollowupMilestone::query()->whereKey($milestone->id)->lockForUpdate()->first();
        $current = $this->usableInvitation($milestone, $audience);

        if ($current !== null) {
            if ($current->answered_at !== null) {
                return $current;
            }
            $same = $current->recipient_name === $recipient['name']
                && $current->recipient_email === $recipient['email']
                && $current->recipient_phone === $recipient['phone'];
            if ($same) {
                return $current;
            }
            $this->revoke($current, 'Destinatário alterado ('.$audience->label().'): novo convite gerado.', $cycle, $userId);
        }

        return HiringFollowupInvitation::query()->create([
            'milestone_id' => $milestone->id,
            'audience' => $audience,
            'recipient_name' => $recipient['name'],
            'recipient_email' => $recipient['email'],
            'recipient_phone' => $recipient['phone'],
            'token' => (string) Str::uuid(),
            'expires_at' => now()->addDays(HiringFollowupInvitation::VALID_DAYS),
            'created_by' => $userId,
        ]);
    }

    private function revoke(HiringFollowupInvitation $invitation, string $reason, EmploymentCycle $cycle, ?int $userId): void
    {
        $invitation->update(['revoked_at' => now(), 'revoke_reason' => $reason]);
        EmploymentCycleEvent::query()->create([
            'employment_cycle_id' => $cycle->id,
            'type' => EmploymentCycleEventType::InvitationRevoked,
            'note' => $reason,
            'meta' => ['invitation_id' => $invitation->id, 'audience' => $invitation->audience->value],
            'user_id' => $userId,
        ]);
    }

    /**
     * @return array{outcome: string, reason: ?string, invitation_id: int, link: null, message: null, copy_url: null}
     */
    private function email(EmploymentCycle $cycle, HiringFollowupMilestone $milestone, HiringFollowupInvitation $invitation): array
    {
        try {
            Mail::to($invitation->recipient_email, $invitation->recipient_name)->send(new HiringFollowupInvitationMail(
                $this->subject($cycle, $milestone),
                $this->message($cycle, $milestone, $invitation),
            ));
            $invitation->update(['emailed_at' => now(), 'email_failure_reason' => null]);

            return ['outcome' => 'emailed', 'reason' => null, 'invitation_id' => $invitation->id, 'link' => null, 'message' => null, 'copy_url' => null];
        } catch (\Throwable $e) {
            report($e);
            $reason = mb_substr('O servidor de e-mail recusou o envio: '.$e->getMessage(), 0, 500);
            $invitation->update(['email_failure_reason' => $reason]);

            return ['outcome' => 'failed', 'reason' => $reason, 'invitation_id' => $invitation->id, 'link' => null, 'message' => null, 'copy_url' => null];
        }
    }

    private function subject(EmploymentCycle $cycle, HiringFollowupMilestone $milestone): string
    {
        return 'Acompanhamento '.$milestone->moment->label().' — '.($cycle->company?->name ?? 'Talents');
    }

    /** Mensagem do convite: sem dados de outras pessoas além do necessário para o gestor saber de quem se trata. */
    public function message(EmploymentCycle $cycle, HiringFollowupMilestone $milestone, HiringFollowupInvitation $invitation): string
    {
        $company = $cycle->company?->name ?? 'a empresa';
        $first = trim(explode(' ', trim($invitation->recipient_name))[0] ?? '');
        $greeting = $first !== '' ? 'Olá, '.$first.'!' : 'Olá!';
        $deadline = $invitation->expires_at->timezone('America/Sao_Paulo')->format('d/m/Y');

        $ask = $invitation->audience === FollowupAudience::Collaborator
            ? "A Talents quer saber como está sendo a sua experiência em {$company} ({$milestone->moment->label()} desde a admissão). A resposta leva menos de um minuto e é confidencial: não é compartilhada com o seu gestor."
            : "A Talents quer saber como está sendo a adaptação de {$cycle->employee?->name} em {$company} ({$milestone->moment->label()} desde a admissão). A resposta leva menos de um minuto.";

        return "{$greeting}\n\n{$ask}\n\nResponda pelo link (válido até {$deadline}):\n{$invitation->publicUrl()}\n\nEquipe Talents Gestão de Pessoas";
    }
}
