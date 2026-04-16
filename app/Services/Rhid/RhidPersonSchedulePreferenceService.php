<?php

namespace App\Services\Rhid;

use App\Models\Company;
use App\Models\RhidPersonSchedulePreference;

class RhidPersonSchedulePreferenceService
{
    /**
     * Map id_person => use_second_lunch_interval (apenas linhas gravadas; omitidos = false).
     *
     * @return array<int, bool>
     */
    public function secondLunchMapForCompany(int $companyId): array
    {
        $rows = RhidPersonSchedulePreference::query()
            ->where('company_id', $companyId)
            ->get(['id_person', 'use_second_lunch_interval']);

        $m = [];
        foreach ($rows as $r) {
            $m[(int) $r->id_person] = (bool) $r->use_second_lunch_interval;
        }

        return $m;
    }

    public function getUseSecondLunchInterval(Company $company, int $idPerson): bool
    {
        $row = RhidPersonSchedulePreference::query()
            ->where('company_id', $company->id)
            ->where('id_person', $idPerson)
            ->first();

        return $row ? (bool) $row->use_second_lunch_interval : false;
    }

    public function setForPerson(Company $company, int $idPerson, bool $useSecond): void
    {
        if (! $useSecond) {
            RhidPersonSchedulePreference::query()
                ->where('company_id', $company->id)
                ->where('id_person', $idPerson)
                ->delete();

            return;
        }

        RhidPersonSchedulePreference::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'id_person' => $idPerson,
            ],
            [
                'use_second_lunch_interval' => true,
            ],
        );
    }

    /**
     * @param  list<int>  $idPeople
     */
    public function setBatch(Company $company, array $idPeople, bool $useSecond): int
    {
        $n = 0;
        foreach (array_unique(array_map('intval', $idPeople)) as $pid) {
            if ($pid < 1) {
                continue;
            }
            $this->setForPerson($company, $pid, $useSecond);
            $n++;
        }

        return $n;
    }
}
