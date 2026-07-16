<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\CompanyEmployee;
use Illuminate\Support\Facades\DB;

class ResolveOrCreateCompanyEmployee
{
    /**
     * Resolve um colaborador da empresa a partir do nome (e e-mail opcional).
     * Com e-mail: chave (company_id, email). Sem e-mail: reutiliza o mesmo nome
     * (case-insensitive) sem e-mail, ou cria um novo registo.
     */
    public function execute(
        Company $company,
        string $name,
        ?string $email = null,
        ?int $leaderUserId = null,
    ): CompanyEmployee {
        $name = trim($name);
        $email = $email !== null ? trim($email) : null;
        if ($email === '') {
            $email = null;
        }

        return DB::transaction(function () use ($company, $name, $email, $leaderUserId) {
            if ($email !== null) {
                $employee = CompanyEmployee::query()
                    ->where('company_id', $company->id)
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                    ->lockForUpdate()
                    ->first();

                if ($employee) {
                    $employee->fill([
                        'name' => $name,
                        'email' => $email,
                        'is_active' => true,
                    ]);
                    if ($leaderUserId !== null) {
                        $employee->leader_user_id = $leaderUserId;
                    }
                    $employee->save();

                    return $employee;
                }

                return CompanyEmployee::query()->create([
                    'company_id' => $company->id,
                    'name' => $name,
                    'email' => $email,
                    'leader_user_id' => $leaderUserId,
                    'is_active' => true,
                ]);
            }

            $employee = CompanyEmployee::query()
                ->where('company_id', $company->id)
                ->whereNull('email')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->lockForUpdate()
                ->first();

            if ($employee) {
                $employee->fill([
                    'name' => $name,
                    'is_active' => true,
                ]);
                if ($leaderUserId !== null) {
                    $employee->leader_user_id = $leaderUserId;
                }
                $employee->save();

                return $employee;
            }

            return CompanyEmployee::query()->create([
                'company_id' => $company->id,
                'name' => $name,
                'email' => null,
                'leader_user_id' => $leaderUserId,
                'is_active' => true,
            ]);
        });
    }
}
