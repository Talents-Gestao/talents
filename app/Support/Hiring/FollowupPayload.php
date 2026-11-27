<?php

declare(strict_types=1);

namespace App\Support\Hiring;

use App\Enums\CompanyContactTopic;
use App\Enums\FollowupAudience;
use App\Models\CompanyContact;
use App\Models\EmploymentCycle;
use App\Models\HiringFollowupInvitation;
use App\Models\HiringFollowupMilestone;
use App\Services\Hiring\FollowupInvitationService;

/**
 * Dados do pós-contratação para as telas do admin. O comentário da resposta só aparece aqui
 * (Talents); nunca em payload do gestor nem do portal.
 */
final class FollowupPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function queueRow(EmploymentCycle $cycle): array
    {
        $next = $cycle->nextMilestone();
        $status = $cycle->followupStatus();

        return [
            'id' => $cycle->id,
            'employee_name' => $cycle->employee?->name,
            'company' => $cycle->company ? ['id' => $cycle->company->id, 'name' => $cycle->company->name] : null,
            'role_title' => $cycle->role_title,
            'manager_name' => $cycle->manager?->name,
            'admission_date' => $cycle->admission_date?->toDateString(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'next_milestone' => $next ? self::milestoneSummary($cycle, $next) : null,
            'url' => route('admin.contratacao.pos.show', $cycle),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function milestoneSummary(EmploymentCycle $cycle, HiringFollowupMilestone $milestone): array
    {
        $status = $milestone->status($cycle->status);
        $service = app(FollowupInvitationService::class);

        return [
            'id' => $milestone->id,
            'moment' => $milestone->moment->value,
            'moment_label' => $milestone->moment->label(),
            'due_on' => $milestone->due_on->toDateString(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'send_url' => route('admin.contratacao.pos.milestones.send', [$cycle, $milestone]),
            // Motivo de cada destino não poder receber, por canal (null = pode).
            'eligibility' => [
                'email' => [
                    'collaborator' => $service->ineligibilityReason($cycle, FollowupAudience::Collaborator, FollowupInvitationService::CHANNEL_EMAIL),
                    'manager' => $service->ineligibilityReason($cycle, FollowupAudience::Manager, FollowupInvitationService::CHANNEL_EMAIL),
                ],
                'copy' => [
                    'collaborator' => $service->ineligibilityReason($cycle, FollowupAudience::Collaborator, FollowupInvitationService::CHANNEL_COPY),
                    'manager' => $service->ineligibilityReason($cycle, FollowupAudience::Manager, FollowupInvitationService::CHANNEL_COPY),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function milestone(EmploymentCycle $cycle, HiringFollowupMilestone $milestone): array
    {
        return [
            ...self::milestoneSummary($cycle, $milestone),
            'skipped_at' => $milestone->skipped_at?->toIso8601String(),
            'skip_reason' => $milestone->skip_reason,
            'completed_at' => $milestone->completed_at?->toIso8601String(),
            'skip_url' => route('admin.contratacao.pos.milestones.skip', [$cycle, $milestone]),
            'invitations' => $milestone->invitations->map(fn (HiringFollowupInvitation $i) => self::invitation($cycle, $milestone, $i))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invitation(EmploymentCycle $cycle, HiringFollowupMilestone $milestone, HiringFollowupInvitation $invitation): array
    {
        $state = $invitation->state();
        $response = $invitation->response;

        return [
            'id' => $invitation->id,
            'audience' => $invitation->audience->value,
            'audience_label' => $invitation->audience->label(),
            'recipient_name' => $invitation->recipient_name,
            'recipient_email' => $invitation->recipient_email,
            'recipient_phone' => $invitation->recipient_phone,
            'state' => $state->value,
            'state_label' => $state->label(),
            // Fatos separados: gerar, copiar, aceitar no servidor e responder nunca se confundem.
            'generated_at' => $invitation->created_at?->toIso8601String(),
            'copied_at' => $invitation->copied_at?->toIso8601String(),
            'emailed_at' => $invitation->emailed_at?->toIso8601String(),
            'email_failure_reason' => $invitation->email_failure_reason,
            'answered_at' => $invitation->answered_at?->toIso8601String(),
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'revoked_at' => $invitation->revoked_at?->toIso8601String(),
            'revoke_reason' => $invitation->revoke_reason,
            'link' => $invitation->isUsable() ? $invitation->publicUrl() : null,
            'copy_url' => $invitation->isUsable() ? route('admin.contratacao.pos.invitations.copied', [$cycle, $milestone, $invitation]) : null,
            'response' => $response ? [
                'rating' => $response->rating,
                'comment' => $response->comment,
                'support_requested' => $response->support_requested,
                'support_note' => $response->support_note,
                'created_at' => $response->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Gestores possíveis: contatos ativos da empresa com o tópico pós-contratação, com a situação de conferência.
     *
     * @return list<array<string, mixed>>
     */
    public static function managerOptions(int $companyId): array
    {
        return CompanyContact::query()
            ->where('company_id', $companyId)
            ->whereNull('archived_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->filter(fn (CompanyContact $c) => in_array(CompanyContactTopic::PosContratacao->value, $c->topicValues(), true))
            ->map(fn (CompanyContact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'role' => $c->role,
                'email' => $c->email,
                'reviewed' => $c->isReviewed(),
                'channels' => CompanyContactEligibility::channelsFor($c, CompanyContactTopic::PosContratacao),
            ])
            ->values()
            ->all();
    }
}
