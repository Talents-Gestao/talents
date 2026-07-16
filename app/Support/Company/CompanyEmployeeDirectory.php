<?php

declare(strict_types=1);

namespace App\Support\Company;

use App\Models\Company;
use App\Models\CompanyEmployee;
use Illuminate\Support\Collection;

final class CompanyEmployeeDirectory
{
    /**
     * @return list<array{id: int, name: string, email: ?string}>
     */
    public function suggestionsFor(Company $company, int $limit = 100): array
    {
        return CompanyEmployee::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email'])
            ->map(fn (CompanyEmployee $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'email' => $e->email,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CompanyEmployee>
     */
    public function activeFor(Company $company): Collection
    {
        return CompanyEmployee::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
