<?php

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeAudience;
use App\Enums\CompanyNoticeEventKind;
use App\Models\CompanyNotice;
use App\Models\User;
use Carbon\Carbon;

class PublishCompanyNotice
{
    /**
     * Cria um aviso. Ponto único de criação para qualquer origem (manual ou evento).
     *
     * Quando $dedupeWithinMinutes é indicado (com source_type/source_id), evita
     * duplicar o mesmo evento na mesma audiência/empresa dentro da janela; nesse
     * caso devolve o aviso já existente em vez de criar outro.
     */
    public function handle(
        ?int $companyId,
        string $title,
        string $body,
        CompanyNoticeAudience $audience = CompanyNoticeAudience::Company,
        ?User $actor = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?CompanyNoticeEventKind $eventKind = null,
        ?Carbon $publishedAt = null,
        ?int $dedupeWithinMinutes = null,
    ): CompanyNotice {
        if ($dedupeWithinMinutes !== null && $sourceType !== null && $sourceId !== null) {
            $existing = $this->findRecentDuplicate(
                $audience,
                $companyId,
                $sourceType,
                $sourceId,
                $eventKind,
                $dedupeWithinMinutes,
            );

            if ($existing !== null) {
                return $existing;
            }
        }

        return CompanyNotice::query()->create([
            'audience' => $audience->value,
            'company_id' => $companyId,
            'title' => $title,
            'body' => $body,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'event_kind' => $eventKind?->value,
            'published_at' => $publishedAt ?? now(),
            'created_by_user_id' => $actor?->id,
        ]);
    }

    private function findRecentDuplicate(
        CompanyNoticeAudience $audience,
        ?int $companyId,
        string $sourceType,
        int $sourceId,
        ?CompanyNoticeEventKind $eventKind,
        int $withinMinutes,
    ): ?CompanyNotice {
        return CompanyNotice::query()
            ->where('audience', $audience->value)
            ->when(
                $companyId !== null,
                fn ($query) => $query->where('company_id', $companyId),
                fn ($query) => $query->whereNull('company_id'),
            )
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->when($eventKind !== null, fn ($query) => $query->where('event_kind', $eventKind->value))
            ->where('published_at', '>=', now()->subMinutes($withinMinutes))
            ->first();
    }
}
