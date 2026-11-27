<?php

declare(strict_types=1);

namespace App\Support\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\CompanyContactTopic;
use App\Enums\HiringCommunicationChannel;
use App\Enums\PermissionAction;
use App\Models\CompanyContact;
use App\Models\HiringCommunication;
use App\Models\HiringVacancy;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\Hiring\HiringCommunicationService;

/** Dados das comunicações da vaga para as telas (quadro da vaga e avaliação). */
final class HiringCommunicationsPayload
{
    /**
     * Histórico com status claros: nunca «entregue» nem «lido».
     *
     * @return list<array<string, mixed>>
     */
    public static function history(HiringVacancy $vacancy, ?int $applicationId = null): array
    {
        return HiringCommunication::query()
            ->where('hiring_vacancy_id', $vacancy->id)
            ->when($applicationId !== null, fn ($q) => $q->where('hiring_application_id', $applicationId))
            ->with(['contact:id,name', 'createdByUser:id,name'])
            ->latest()
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (HiringCommunication $c) => [
                'id' => $c->id,
                'kind' => $c->kind->value,
                'kind_label' => $c->kind->label(),
                'channel' => $c->channel->value,
                'channel_label' => $c->channel->label(),
                'contact_name' => $c->contact?->name,
                'recipient' => $c->recipient,
                'stage_name' => $c->stage_name,
                'subject' => $c->subject,
                'body' => $c->body,
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'sent_at' => $c->sent_at?->toIso8601String(),
                'failure_reason' => $c->failure_reason,
                'created_by' => $c->createdByUser?->name,
                'created_at' => $c->created_at?->toIso8601String(),
                'copy_url' => $c->channel === HiringCommunicationChannel::Whatsapp
                    ? route('admin.contratacao.vagas.communications.copied', [$vacancy, $c])
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Contatos da empresa da vaga que podem receber o tópico «vagas», com os canais permitidos.
     *
     * @return array<string, mixed>
     */
    public static function audience(HiringVacancy $vacancy, ?User $user): array
    {
        $contacts = CompanyContact::query()
            ->where('company_id', $vacancy->company_id)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        $eligible = $contacts
            ->map(fn (CompanyContact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'role' => $c->role,
                'email' => $c->email,
                'phone' => $c->phone,
                'is_primary' => $c->is_primary,
                'channels' => CompanyContactEligibility::channelsFor($c, CompanyContactTopic::Vagas),
            ])
            ->filter(fn (array $c) => $c['channels'] !== [])
            ->values();

        $service = app(HiringCommunicationService::class);

        return [
            'contacts' => $eligible->all(),
            'ineligible_count' => $contacts->count() - $eligible->count(),
            'contacts_url' => $user?->canAccessAdmin(AdminPermissionModule::Companies, PermissionAction::View)
                ? route('admin.companies.show', ['company' => $vacancy->company_id, 'tab' => 'contatos'])
                : null,
            'smtp_configured' => MailSetting::smtpConfigured(),
            'emails_remaining_today' => $service->emailsRemainingToday(),
            'daily_limit' => HiringCommunicationService::DAILY_EMAIL_LIMIT,
        ];
    }
}
