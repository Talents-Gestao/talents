<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\StrategicCalendarItem;
use Illuminate\Support\Collection;

final class StrategicCalendarAudience
{
    /**
     * @param  list<int|string|null>  $companyIds
     */
    public static function syncCompanies(StrategicCalendarItem $item, ?array $companyIds): void
    {
        $ids = collect($companyIds ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            $item->companies()->detach();
            $item->forceFill(['company_id' => null])->save();

            return;
        }

        $item->companies()->sync($ids);
        $item->forceFill(['company_id' => null])->save();
    }

    /**
     * @return list<int>
     */
    public static function targetCompanyIds(StrategicCalendarItem $item): array
    {
        if ($item->relationLoaded('companies')) {
            $ids = $item->companies->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $ids = $item->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->all();
        }

        if ($ids !== []) {
            return $ids;
        }

        if ($item->company_id !== null) {
            return [(int) $item->company_id];
        }

        return [];
    }

    public static function isGlobal(StrategicCalendarItem $item): bool
    {
        if ($item->company_id !== null) {
            return false;
        }

        if ($item->relationLoaded('companies')) {
            return $item->companies->isEmpty();
        }

        return ! $item->companies()->exists();
    }

    public static function label(StrategicCalendarItem $item): string
    {
        if (self::isGlobal($item)) {
            return 'Todas as empresas';
        }

        $names = $item->relationLoaded('companies')
            ? $item->companies->sortBy('name')->pluck('name')
            : $item->companies()->orderBy('name')->pluck('name');

        if ($names->isEmpty() && $item->relationLoaded('company') && $item->company) {
            return $item->company->name;
        }

        if ($names->isEmpty() && $item->company_id) {
            return (string) ($item->company()->value('name') ?? 'Empresa');
        }

        return $names->join(', ');
    }

    /**
     * @return Collection<int, \App\Models\Company>
     */
    public static function affectedCompanies(StrategicCalendarItem $item): Collection
    {
        $item->loadMissing(['companies', 'company']);

        if (self::isGlobal($item)) {
            return \App\Models\Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->filter(fn (\App\Models\Company $company) => $company->hasStrategicCalendarEnabled())
                ->values();
        }

        $companies = $item->companies->isNotEmpty()
            ? $item->companies
            : collect($item->company ? [$item->company] : []);

        return $companies
            ->filter(fn (\App\Models\Company $company) => $company->hasStrategicCalendarEnabled())
            ->values();
    }

    /**
     * @return list<array{id:int, name:string}>
     */
    public static function companiesPayload(StrategicCalendarItem $item): array
    {
        $item->loadMissing(['companies', 'company']);

        if ($item->companies->isNotEmpty()) {
            return $item->companies
                ->sortBy('name')
                ->map(fn ($company) => ['id' => $company->id, 'name' => $company->name])
                ->values()
                ->all();
        }

        if ($item->company) {
            return [['id' => $item->company->id, 'name' => $item->company->name]];
        }

        return [];
    }
}
