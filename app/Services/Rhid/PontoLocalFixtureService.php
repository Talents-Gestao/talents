<?php

declare(strict_types=1);

namespace App\Services\Rhid;

use App\Models\Company;
use App\Models\RhidEspelhoDay;
use App\Models\RhidEspelhoImport;
use App\Support\RhidBankBalanceFormat;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Dados locais para a empresa de QA visual (sem chamar a Control iD).
 */
class PontoLocalFixtureService
{
    public const ATESTADO_TYPE_ID = 1;

    public const FALTA_TYPE_ID = 2;

    /**
     * @return list<array<string, mixed>>
     */
    public function lastPunches(Company $company): array
    {
        $days = $this->daysForCompany($company);
        $targetDate = $this->preferredPunchDate($days);
        if ($targetDate === null) {
            return [];
        }

        $punches = [];
        foreach ($days as $day) {
            if ($day->ref_date?->toDateString() !== $targetDate) {
                continue;
            }
            foreach ($this->collaborators($day) as $colab) {
                if ($this->isAtestadoColab($day, $colab)) {
                    continue;
                }
                $idPerson = (int) ($colab['id_person'] ?? 0);
                $nome = trim((string) ($colab['nome'] ?? '—'));
                foreach (['ent_1', 'sai_1', 'ent_2', 'sai_2'] as $key) {
                    $time = $colab[$key] ?? null;
                    if (! is_string($time) || trim($time) === '') {
                        continue;
                    }
                    $punches[] = [
                        'idPerson' => $idPerson,
                        'name' => $nome,
                        'nome' => $nome,
                        'data' => $this->isoDateTime($targetDate, $time),
                    ];
                }
            }
        }

        usort($punches, fn (array $a, array $b): int => strcmp((string) $b['data'], (string) $a['data']));

        return array_values($punches);
    }

    /**
     * @return array{data: list<array<string, mixed>>, recordsTotal: int}
     */
    public function people(Company $company): array
    {
        $data = [];
        foreach ($this->peopleFromImports($company) as $person) {
            $meta = $this->personMeta($person['id']);
            $data[] = [
                'id' => $person['id'],
                'idPerson' => $person['id'],
                'name' => $person['name'],
                'registration' => $meta['registration'],
                'departmentName' => $meta['department'],
                'personRoleName' => $meta['role'],
                'status' => 1,
            ];
        }

        return [
            'data' => $data,
            'recordsTotal' => count($data),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, recordsTotal: int}
     */
    public function justificationTypes(): array
    {
        return [
            'data' => [
                ['id' => self::ATESTADO_TYPE_ID, 'name' => 'Atestado médico'],
                ['id' => self::FALTA_TYPE_ID, 'name' => 'Falta justificada'],
            ],
            'recordsTotal' => 2,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: list<array<string, mixed>>, recordsTotal: int}
     */
    public function justifications(Company $company, array $payload = []): array
    {
        $ini = $this->parseYmd($payload['ini'] ?? null) ?? Carbon::now()->startOfMonth()->toDateString();
        $fim = $this->parseYmd($payload['fim'] ?? null) ?? Carbon::now()->endOfMonth()->toDateString();

        $rows = [];
        $id = 800001;
        foreach ($this->daysForCompany($company) as $day) {
            $date = $day->ref_date?->toDateString();
            if ($date === null || $date < $ini || $date > $fim) {
                continue;
            }
            foreach ($this->collaborators($day) as $colab) {
                if (! $this->isAtestadoColab($day, $colab)) {
                    continue;
                }
                $nome = trim((string) ($colab['nome'] ?? '—'));
                $idPerson = (int) ($colab['id_person'] ?? 0);
                $rows[] = [
                    'id' => $id++,
                    'idPerson' => $idPerson,
                    'idJustificationType' => self::ATESTADO_TYPE_ID,
                    'personName' => $nome,
                    'justificativa' => 'Atestado médico (teste visual)',
                    'inicio' => $this->isoDateTime($date, '08:00'),
                    'fim' => $this->isoDateTime($date, '17:00'),
                ];
            }
        }

        return [
            'data' => $rows,
            'recordsTotal' => count($rows),
        ];
    }

    /**
     * @return array{date: string, rows: list<array<string, mixed>>, source: string}
     */
    public function bankHours(Company $company, string $date): array
    {
        $rows = [];
        foreach ($this->peopleFromImports($company) as $person) {
            $meta = $this->personMeta($person['id']);
            $minutes = $this->bankMinutes($person['id']);
            $rows[] = [
                'idPerson' => $person['id'],
                'name' => $person['name'],
                'socialName' => $meta['social_name'],
                'departmentName' => $meta['department'],
                'roleName' => $meta['role'],
                'registration' => $meta['registration'],
                'cpf' => $meta['cpf'],
                'saldoBancoHoras' => $minutes,
                'strSaldoBancoHoras' => RhidBankBalanceFormat::minutesToHhMm($minutes),
            ];
        }

        return [
            'date' => $date,
            'rows' => $rows,
            'source' => 'local_ponto_fixture',
        ];
    }

    /**
     * @return Collection<int, RhidEspelhoDay>
     */
    private function daysForCompany(Company $company): Collection
    {
        $importIds = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->pluck('id');

        if ($importIds->isEmpty()) {
            return collect();
        }

        return RhidEspelhoDay::query()
            ->whereIn('import_id', $importIds)
            ->orderBy('ref_date')
            ->get();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function peopleFromImports(Company $company): array
    {
        $people = [];
        foreach ($this->daysForCompany($company) as $day) {
            foreach ($this->collaborators($day) as $colab) {
                $id = (int) ($colab['id_person'] ?? 0);
                $name = trim((string) ($colab['nome'] ?? ''));
                if ($id <= 0 || $name === '') {
                    continue;
                }
                $people[$id] = ['id' => $id, 'name' => $name];
            }
        }

        ksort($people);

        return array_values($people);
    }

    /**
     * @param  Collection<int, RhidEspelhoDay>  $days
     */
    private function preferredPunchDate(Collection $days): ?string
    {
        $dates = [];
        foreach ($days as $day) {
            $date = $day->ref_date?->toDateString();
            if ($date === null) {
                continue;
            }
            foreach ($this->collaborators($day) as $colab) {
                if ($this->isAtestadoColab($day, $colab)) {
                    continue;
                }
                if (filled($colab['ent_1'] ?? null) || filled($colab['sai_1'] ?? null)) {
                    $dates[$date] = true;
                    break;
                }
            }
        }

        if ($dates === []) {
            return null;
        }

        $today = Carbon::now()->toDateString();
        if (isset($dates[$today])) {
            return $today;
        }

        $keys = array_keys($dates);
        rsort($keys);

        return $keys[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collaborators(RhidEspelhoDay $day): array
    {
        $json = is_array($day->row_json) ? $day->row_json : [];
        $colabs = $json['colaboradores'] ?? [];
        if (! is_array($colabs)) {
            return [];
        }

        return array_values(array_filter($colabs, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $colab
     */
    private function isAtestadoColab(RhidEspelhoDay $day, array $colab): bool
    {
        $json = is_array($day->row_json) ? $day->row_json : [];

        return ! empty($json['atestado']) || ! empty($colab['atestado']);
    }

    /**
     * @return array{registration: string, department: string, role: string, cpf: string, social_name: string}
     */
    private function personMeta(int $idPerson): array
    {
        return match ($idPerson) {
            910001 => [
                'registration' => '1001',
                'department' => 'Operações',
                'role' => 'Analista de RH',
                'cpf' => '111.111.111-11',
                'social_name' => '',
            ],
            910002 => [
                'registration' => '1002',
                'department' => 'Operações',
                'role' => 'Auxiliar administrativo',
                'cpf' => '222.222.222-22',
                'social_name' => '',
            ],
            910003 => [
                'registration' => '1003',
                'department' => 'Operações',
                'role' => 'Assistente',
                'cpf' => '333.333.333-33',
                'social_name' => '',
            ],
            default => [
                'registration' => (string) $idPerson,
                'department' => 'Operações',
                'role' => 'Colaborador',
                'cpf' => '',
                'social_name' => '',
            ],
        };
    }

    private function bankMinutes(int $idPerson): int
    {
        return match ($idPerson) {
            910001 => 120,
            910002 => -480,
            910003 => 0,
            default => 0,
        };
    }

    private function isoDateTime(string $date, string $time): string
    {
        $time = strlen($time) === 5 ? $time.':00' : $time;

        return $date.' '.$time;
    }

    private function parseYmd(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';
        if (strlen($digits) < 8) {
            return null;
        }
        $y = (int) substr($digits, 0, 4);
        $m = (int) substr($digits, 4, 2);
        $d = (int) substr($digits, 6, 2);
        if (! checkdate($m, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}
