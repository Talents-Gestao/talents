<?php

namespace App\Actions\Notices;

use App\Enums\CompanyNoticeEventKind;
use App\Enums\StrategicCalendarItemKind;
use App\Models\Company;
use App\Models\StrategicCalendarItem;
use App\Models\User;
use Illuminate\Support\Collection;

class PublishStrategicCalendarChangeNotice
{
    public function __construct(
        private PublishCompanyNotice $publishCompanyNotice,
    ) {}

    public function handle(
        StrategicCalendarItem $item,
        CompanyNoticeEventKind $eventKind,
        ?User $actor = null,
        ?string $previousOccursOn = null,
    ): void {
        $companies = $this->affectedCompanies($item);
        if ($companies->isEmpty()) {
            return;
        }

        $kindLabel = $item->kind instanceof StrategicCalendarItemKind
            ? $item->kind->label()
            : (string) $item->kind;

        [$title, $body] = $this->copyFor($item, $eventKind, $kindLabel, $previousOccursOn);

        foreach ($companies as $company) {
            if ($this->isDuplicateRecent($company->id, $item->id, $eventKind)) {
                continue;
            }

            $this->publishCompanyNotice->handle(
                companyId: (int) $company->id,
                title: $title,
                body: $body,
                actor: $actor,
                sourceType: 'strategic_calendar_item',
                sourceId: $item->id,
                eventKind: $eventKind,
            );
        }
    }

    /**
     * @return Collection<int, Company>
     */
    private function affectedCompanies(StrategicCalendarItem $item): Collection
    {
        if ($item->company_id) {
            $company = Company::query()->find($item->company_id);

            return $company && $company->hasStrategicCalendarEnabled()
                ? collect([$company])
                : collect();
        }

        return Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Company $company) => $company->hasStrategicCalendarEnabled())
            ->values();
    }

    private function isDuplicateRecent(int $companyId, int $itemId, CompanyNoticeEventKind $eventKind): bool
    {
        return \App\Models\CompanyNotice::query()
            ->where('company_id', $companyId)
            ->where('source_type', 'strategic_calendar_item')
            ->where('source_id', $itemId)
            ->where('event_kind', $eventKind->value)
            ->where('published_at', '>=', now()->subMinutes(5))
            ->exists();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function copyFor(
        StrategicCalendarItem $item,
        CompanyNoticeEventKind $eventKind,
        string $kindLabel,
        ?string $previousOccursOn,
    ): array {
        $dateLabel = $item->occurs_on?->format('d/m/Y') ?? '—';

        return match ($eventKind) {
            CompanyNoticeEventKind::Created => [
                'Calendário atualizado',
                "Novo {$kindLabel} «{$item->title}» em {$dateLabel}. Consulte o calendário estratégico para ver os detalhes.",
            ],
            CompanyNoticeEventKind::Updated => [
                'Calendário atualizado',
                "O {$kindLabel} «{$item->title}» foi atualizado. Data de referência: {$dateLabel}.",
            ],
            CompanyNoticeEventKind::DateChanged => [
                'Data alterada no calendário',
                "A data do {$kindLabel} «{$item->title}» foi alterada"
                    .($previousOccursOn ? " de {$this->formatBrDate($previousOccursOn)} para {$dateLabel}" : " para {$dateLabel}")
                    .'.',
            ],
            CompanyNoticeEventKind::Deleted => [
                'Item removido do calendário',
                "O {$kindLabel} «{$item->title}» foi removido do calendário estratégico.",
            ],
        };
    }

    private function formatBrDate(string $iso): string
    {
        try {
            return \Carbon\Carbon::parse($iso)->format('d/m/Y');
        } catch (\Throwable) {
            return $iso;
        }
    }
}
