<?php

declare(strict_types=1);

namespace App\Support\Hiring;

use App\Enums\CompanyContactTopic;
use App\Enums\HiringCommunicationChannel;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\CompanyContactEvent;
use App\Models\User;

/** Dados da aba «Contatos» do detalhe da empresa (admin). */
final class CompanyContactsPayload
{
    private const FIELD_LABELS = [
        'name' => 'nome',
        'role' => 'função',
        'email' => 'e-mail',
        'phone' => 'telefone',
        'channel_email' => 'canal e-mail',
        'channel_whatsapp' => 'canal WhatsApp',
        'topics' => 'tópicos',
        'is_enabled' => 'habilitado',
        'is_primary' => 'principal',
        'responsible_user_id' => 'responsável Talents',
        'notes' => 'observações',
        'reviewed_at' => 'conferência',
        'archived_at' => 'arquivamento',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function contacts(Company $company): array
    {
        return CompanyContact::query()
            ->where('company_id', $company->id)
            ->with([
                'responsibleUser:id,name',
                'reviewedBy:id,name',
                'events' => fn ($q) => $q->with('user:id,name')->limit(200),
            ])
            ->orderByRaw('case when archived_at is null then 0 else 1 end')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->map(fn (CompanyContact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'role' => $c->role,
                'email' => $c->email,
                'phone' => $c->phone,
                'channel_email' => $c->channel_email,
                'channel_whatsapp' => $c->channel_whatsapp,
                'topics' => $c->topicValues(),
                'is_enabled' => $c->is_enabled,
                'is_primary' => $c->is_primary,
                'responsible_user_id' => $c->responsible_user_id,
                'responsible_name' => $c->responsibleUser?->name,
                'notes' => $c->notes,
                'reviewed_at' => $c->reviewed_at?->toIso8601String(),
                'reviewed_by' => $c->reviewedBy?->name,
                'archived' => $c->isArchived(),
                // Onde o contato pode receber comunicação hoje (mesma regra dos envios).
                'eligibility' => array_map(static fn (CompanyContactTopic $t) => [
                    'topic' => $t->value,
                    'label' => $t->label(),
                    'channels' => CompanyContactEligibility::channelsFor($c, $t),
                    'reasons' => array_values(array_unique(array_merge(
                        CompanyContactEligibility::reasons($c, $t, HiringCommunicationChannel::Email),
                        CompanyContactEligibility::reasons($c, $t, HiringCommunicationChannel::Whatsapp),
                    ))),
                ], CompanyContactTopic::cases()),
                'events' => $c->events->take(20)->map(fn (CompanyContactEvent $e) => [
                    'id' => $e->id,
                    'label' => $e->type->label(),
                    'fields' => array_map(
                        static fn (string $f) => self::FIELD_LABELS[$f] ?? $f,
                        $e->meta['fields'] ?? [],
                    ),
                    'user_name' => $e->user?->name,
                    'created_at' => $e->created_at?->toIso8601String(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'topics' => CompanyContactTopic::options(),
            'responsibles' => User::query()
                ->where('role', UserRole::SuperAdmin->value)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                ->values()
                ->all(),
        ];
    }
}
