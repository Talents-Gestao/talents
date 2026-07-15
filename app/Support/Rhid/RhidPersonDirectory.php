<?php

declare(strict_types=1);

namespace App\Support\Rhid;

use App\Models\Company;
use App\Models\User;
use App\Services\Rhid\RhidComplianceService;
use App\Support\RhidJustificationAnalytics;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Lista colaboradores ativos do Control iD (RHID) para integrações e demos locais.
 */
class RhidPersonDirectory
{
    private const PAGE_SIZE = 500;

    private const MAX_PAGES = 20;

    public function __construct(
        private readonly RhidComplianceService $compliance,
    ) {}

    /**
     * @return Collection<int, array{id: int, name: string, email: ?string}>
     */
    public function activePersons(Company $company, ?User $actor = null): Collection
    {
        if (! $company->hasRhidEnabled() || ! $company->rhidConfigured()) {
            return $this->demoPersonsIfEnabled();
        }

        try {
            $byId = [];

            for ($page = 0; $page < self::MAX_PAGES; $page++) {
                $payload = $this->compliance->listPersons($company, $actor, [
                    'page' => $page,
                    'maxSize' => self::PAGE_SIZE,
                    'status' => 1,
                ]);

                $rows = RhidJustificationAnalytics::extractListItems($payload);
                if ($rows === []) {
                    break;
                }

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $normalized = $this->normalizeRow($row);
                    if ($normalized === null) {
                        continue;
                    }
                    $byId[$normalized['id']] = $normalized;
                }

                if (count($rows) < self::PAGE_SIZE) {
                    break;
                }
            }

            return collect(array_values($byId))
                ->sortBy(fn (array $person) => mb_strtolower($person['name']))
                ->values();
        } catch (Throwable $e) {
            report($e);

            return $this->demoPersonsIfEnabled();
        }
    }

    /**
     * @return Collection<int, array{id: int, name: string, email: ?string}>
     */
    private function demoPersonsIfEnabled(): Collection
    {
        if (! config('rhid.demo_persons')) {
            return collect();
        }

        return collect([
            ['id' => 900001, 'name' => 'Ana Souza (teste)', 'email' => 'ana.souza@teste.local'],
            ['id' => 900002, 'name' => 'Bruno Lima (teste)', 'email' => 'bruno.lima@teste.local'],
            ['id' => 900003, 'name' => 'Carla Mendes (teste)', 'email' => 'carla.mendes@teste.local'],
            ['id' => 900004, 'name' => 'Diego Alves (teste)', 'email' => 'diego.alves@teste.local'],
            ['id' => 900005, 'name' => 'Elena Costa (teste)', 'email' => 'elena.costa@teste.local'],
        ]);
    }

    /**
     * @return array{id: int, name: string, email: ?string}|null
     */
    public function findActive(Company $company, int $rhidPersonId, ?User $actor = null): ?array
    {
        return $this->activePersons($company, $actor)
            ->first(fn (array $person) => $person['id'] === $rhidPersonId);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: int, name: string, email: ?string}|null
     */
    private function normalizeRow(array $row): ?array
    {
        $id = $this->pickId($row);
        if ($id === null) {
            return null;
        }

        $name = $this->pickName($row);
        if ($name === '') {
            $name = 'Colaborador #'.$id;
        }

        $email = $this->pickEmail($row);

        return [
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function pickId(array $row): ?int
    {
        foreach (['idPerson', 'id_person', 'id_funcionario', 'id', 'Id'] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }
            $id = (int) $row[$key];
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function pickName(array $row): string
    {
        foreach ([
            'strPersonName', 'personName', 'socialName', 'strSocialName',
            'strNome', 'strName', 'nome', 'name', 'Nome',
        ] as $key) {
            if (! isset($row[$key])) {
                continue;
            }
            $value = trim((string) $row[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        foreach (['person', 'Person', 'pessoa', 'Pessoa'] as $nestedKey) {
            if (! isset($row[$nestedKey]) || ! is_array($row[$nestedKey])) {
                continue;
            }
            $nested = $this->pickName($row[$nestedKey]);
            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function pickEmail(array $row): ?string
    {
        foreach (['email', 'strEmail', 'mail', 'Email'] as $key) {
            if (! isset($row[$key])) {
                continue;
            }
            $value = trim((string) $row[$key]);
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }
}
