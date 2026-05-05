<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class RhidComplianceService
{
    /**
     * Mapa id RHID do departamento -> nome, por empresa, na mesma requisicao PHP
     * (evita repetir GET department.svc no modo agregado de banco de horas).
     *
     * @var array<int, array<int, string>>
     */
    private array $departmentNameMapCacheByCompany = [];

    /**
     * Mapa id RHID do cargo (person role) -> nome.
     *
     * @var array<int, array<int, string>>
     */
    private array $personRoleNameMapCacheByCompany = [];

    public function __construct(
        private RhidClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listJustificationTypes(Company $company, ?User $user, array $query = []): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/justificationtype.svc/a',
            ['query' => $query, 'auditAction' => 'rhid.justification_types.list'],
        );

        return $this->decodeJson($r, 'justification_types');
    }

    /**
     * @return array<string, mixed>
     */
    public function listAlertTypes(Company $company, ?User $user, array $query = []): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/alerttype.svc/a',
            ['query' => $query, 'auditAction' => 'rhid.alert_types.list'],
        );

        return $this->decodeJson($r, 'alert_types');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function listJustifications(Company $company, ?User $user, array $payload): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/justification.svc/list',
            [
                'body' => $payload,
                'auditAction' => 'rhid.justifications.list',
            ],
        );

        return $this->decodeJson($r, 'justifications.list');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createJustification(Company $company, ?User $user, array $payload): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/justification.svc/a',
            [
                'body' => $payload,
                'auditAction' => 'rhid.justifications.create',
            ],
        );

        return $this->decodeJson($r, 'justifications.create');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function massJustification(Company $company, ?User $user, array $payload): array
    {
        if (isset($payload['inicio']) && ! isset($payload['ínicio'])) {
            $payload['ínicio'] = $payload['inicio'];
            unset($payload['inicio']);
        }

        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/justification.svc/justificativa_em_massa',
            [
                'body' => $payload,
                'auditAction' => 'rhid.justifications.mass',
            ],
        );

        return $this->decodeJson($r, 'justifications.mass');
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteJustification(Company $company, ?User $user, int $id): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'DELETE',
            'customerdb/justification.svc/a/'.$id,
            ['auditAction' => 'rhid.justifications.delete'],
        );

        return $this->decodeJson($r, 'justifications.delete');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function personBankHours(Company $company, ?User $user, array $query): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/person.svc/person_banco_horas',
            [
                'query' => $query,
                'auditAction' => 'rhid.person_banco_horas',
            ],
        );

        $json = $r->json();
        if (! is_array($json)) {
            throw RhidApiException::fromResponse($r, 'person_banco_horas');
        }

        $rows = $this->normalizeBankHoursRows($json);
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->mergePersonNestedIntoBankHourRow($row, 'person_banco_horas');
            }
        }

        $out = $this->enrichBankHourRowsDepartmentNames($company, $user, $out);
        $out = $this->enrichBankHourRowsPersonRoleNames($company, $user, $out);

        return $this->shrinkBankHourRowsForClient($out);
    }

    /**
     * Lista colaboradores (cadastro RHID).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listPersons(Company $company, ?User $user, array $query = []): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/person.svc/a',
            [
                'query' => $query,
                'auditAction' => 'rhid.person.list',
            ],
        );

        return $this->decodeJson($r, 'person.list');
    }

    /**
     * Lista departamentos no customerdb RHID (documentacao API Control iD: mesmo padrao de cadastro GET *.svc/a).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listDepartments(Company $company, ?User $user, array $query = []): array
    {
        return $this->fetchDepartmentsListJson($company, $user, array_merge([
            'page' => 0,
            'maxSize' => 500,
        ], $query));
    }

    /**
     * Lista cargos / funcoes (person role) no customerdb — GET personrole.svc/a (Control iD RHID).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function listPersonRoles(Company $company, ?User $user, array $query = []): array
    {
        return $this->fetchPersonRolesListJson($company, $user, array_merge([
            'page' => 0,
            'maxSize' => 500,
        ], $query));
    }

    /**
     * Cadastro de uma pessoa por id (GET customerdb/person.svc/a/{id}).
     *
     * @return array<string, mixed>
     */
    public function getPersonDetail(Company $company, ?User $user, int $id): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/person.svc/a/'.$id,
            [
                'auditAction' => 'rhid.person.show',
            ],
        );

        $json = $r->json();
        if (! is_array($json)) {
            throw RhidApiException::fromResponse($r, 'person.show');
        }

        if (isset($json['data']) && is_array($json['data'])) {
            $inner = $json['data'];
            $row = (is_array($inner) && array_is_list($inner) && isset($inner[0]) && is_array($inner[0]))
                ? $inner[0]
                : $inner;
        } else {
            $row = $json;
        }

        if (! is_array($row) || ! isset($row['id'])) {
            throw new RhidApiException('Colaborador não encontrado na API RHID.', $r->status());
        }

        $row = $this->mergePersonNestedIntoBankHourRow($row, 'person.show.'.$id);
        $row = $this->enrichSinglePersonDepartmentAndRoleNames($company, $user, $row);

        return $this->shrinkPersonDetailForClient($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function enrichSinglePersonDepartmentAndRoleNames(Company $company, ?User $user, array $row): array
    {
        $idDept = $row['idDepartment'] ?? null;
        if ($idDept !== null && $idDept !== '' && is_numeric($idDept)) {
            $map = $this->departmentIdToNameMap($company, $user);
            $idi = (int) $idDept;
            $cur = $row['departmentName'] ?? null;
            $fillDept = isset($map[$idi]) && (
                ! is_string($cur) || trim($cur) === '' || $this->looksLikeRhidIdPlaceholder($cur)
            );
            if ($fillDept) {
                $row['departmentName'] = $map[$idi];
            }
        }
        $idRole = $row['idPersonRole'] ?? null;
        if ($idRole !== null && $idRole !== '' && is_numeric($idRole)) {
            $map = $this->personRoleIdToNameMap($company, $user);
            $idi = (int) $idRole;
            $cur = $row['roleName'] ?? null;
            $fillRole = isset($map[$idi]) && (
                ! is_string($cur) || trim($cur) === '' || $this->looksLikeRhidIdPlaceholder($cur)
            );
            if ($fillRole) {
                $row['roleName'] = $map[$idi];
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function shrinkPersonDetailForClient(array $row): array
    {
        $keep = array_flip([
            'id', 'name', 'nome', 'strNome', 'strName', 'strPersonName', 'personName', 'socialName', 'strSocialName',
            'registration', 'matricula', 'strMatricula', 'cpf', 'pis', 'strPis',
            'email', 'phone', 'status', 'statusStr',
            'departmentName', 'idDepartment',
            'roleName', 'idPersonRole',
            'companyName', 'companyTradingName', 'idCompany',
            'costCenterName', 'idCostCenter',
            'admissionDate', 'admissionDateStr', 'inicioBancoHoras', 'inicioBancoHorasStr',
            'idPerson', 'id_funcionario',
        ]);

        return array_intersect_key($row, $keep);
    }

    /**
     * GET customerdb/personrole.svc/a (fallbacks por grafia).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function fetchPersonRolesListJson(Company $company, ?User $user, array $query): array
    {
        $paths = [
            'customerdb/personrole.svc/a',
            'customerdb/personroles.svc/a',
            'customerdb/person_role.svc/a',
        ];
        $last = null;
        foreach ($paths as $path) {
            try {
                $r = $this->client->request(
                    $company,
                    $user,
                    'GET',
                    $path,
                    [
                        'query' => $query,
                        'auditAction' => 'rhid.personrole.list',
                    ],
                );

                return $this->decodeJson($r, 'personrole.list');
            } catch (RhidApiException $e) {
                $last = $e;
                if ($e->httpStatus === 404) {
                    continue;
                }
                throw $e;
            }
        }

        throw $last ?? new RhidApiException('Lista de cargos RHID indisponivel.', 404);
    }

    /**
     * @return array<int, string>
     */
    protected function personRoleIdToNameMap(Company $company, ?User $user): array
    {
        $cid = (int) $company->id;
        if (array_key_exists($cid, $this->personRoleNameMapCacheByCompany)) {
            return $this->personRoleNameMapCacheByCompany[$cid];
        }
        $map = [];
        try {
            $page = 0;
            $maxSize = 500;
            while ($page < 500) {
                $json = $this->fetchPersonRolesListJson($company, $user, [
                    'page' => $page,
                    'maxSize' => $maxSize,
                ]);
                $batch = $this->extractPersonRoleIdToNameFromListJson($json);
                if ($batch === []) {
                    break;
                }
                foreach ($batch as $id => $name) {
                    $map[$id] = $name;
                }
                if (count($batch) < $maxSize) {
                    break;
                }
                $page++;
            }
        } catch (RhidApiException) {
            $map = [];
        }
        $this->personRoleNameMapCacheByCompany[$cid] = $map;

        return $map;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<int, string>
     */
    protected function extractPersonRoleIdToNameFromListJson(array $json): array
    {
        $rows = $json['data'] ?? $json;
        if (! is_array($rows)) {
            return [];
        }
        if ($rows !== [] && ! array_is_list($rows)) {
            if (isset($rows['id'])) {
                $rows = [$rows];
            } else {
                return [];
            }
        }
        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! isset($row['id']) || ! is_numeric($row['id'])) {
                continue;
            }
            $id = (int) $row['id'];
            $name = $this->pickPersonRoleDisplayNameFromRow($row);
            if ($name !== null && $name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function pickPersonRoleDisplayNameFromRow(array $row): ?string
    {
        foreach (['roleName', 'personRoleName', 'strPersonRoleName', 'nome', 'name', 'strNome', 'strName', 'description', 'descricao'] as $k) {
            if (! array_key_exists($k, $row)) {
                continue;
            }
            $v = $row[$k];
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (is_int($v) || is_float($v)) {
                return trim((string) $v);
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function enrichBankHourRowsPersonRoleNames(Company $company, ?User $user, array $rows): array
    {
        $map = $this->personRoleIdToNameMap($company, $user);
        if ($map === []) {
            return $rows;
        }
        foreach ($rows as $i => $row) {
            $id = $row['idPersonRole'] ?? null;
            if ($id === null || $id === '' || ! is_numeric($id)) {
                continue;
            }
            $idInt = (int) $id;
            if (! isset($map[$idInt])) {
                continue;
            }
            $current = $row['roleName'] ?? null;
            if (is_string($current) && trim($current) !== '' && ! $this->looksLikeRhidIdPlaceholder($current)) {
                continue;
            }
            $rows[$i]['roleName'] = $map[$idInt];
        }

        return $rows;
    }

    /**
     * GET customerdb/department.svc/a ou departament.svc/a (variacao de grafia na API).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function fetchDepartmentsListJson(Company $company, ?User $user, array $query): array
    {
        $paths = [
            'customerdb/department.svc/a',
            'customerdb/departament.svc/a',
        ];
        $last = null;
        foreach ($paths as $path) {
            try {
                $r = $this->client->request(
                    $company,
                    $user,
                    'GET',
                    $path,
                    [
                        'query' => $query,
                        'auditAction' => 'rhid.department.list',
                    ],
                );

                return $this->decodeJson($r, 'department.list');
            } catch (RhidApiException $e) {
                $last = $e;
                if ($e->httpStatus === 404) {
                    continue;
                }
                throw $e;
            }
        }

        throw $last ?? new RhidApiException('Lista de departamentos RHID indisponivel.', 404);
    }

    /**
     * @return array<int, string>
     */
    protected function departmentIdToNameMap(Company $company, ?User $user): array
    {
        $cid = (int) $company->id;
        if (array_key_exists($cid, $this->departmentNameMapCacheByCompany)) {
            return $this->departmentNameMapCacheByCompany[$cid];
        }
        $map = [];
        try {
            $page = 0;
            $maxSize = 500;
            while ($page < 500) {
                $json = $this->fetchDepartmentsListJson($company, $user, [
                    'page' => $page,
                    'maxSize' => $maxSize,
                ]);
                $batch = $this->extractDepartmentIdToNameFromListJson($json);
                if ($batch === []) {
                    break;
                }
                foreach ($batch as $id => $name) {
                    $map[$id] = $name;
                }
                if (count($batch) < $maxSize) {
                    break;
                }
                $page++;
            }
        } catch (RhidApiException) {
            $map = [];
        }
        $this->departmentNameMapCacheByCompany[$cid] = $map;

        return $map;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<int, string>
     */
    protected function extractDepartmentIdToNameFromListJson(array $json): array
    {
        $rows = $json['data'] ?? $json;
        if (! is_array($rows)) {
            return [];
        }
        if ($rows !== [] && ! array_is_list($rows)) {
            if (isset($rows['id'])) {
                $rows = [$rows];
            } else {
                return [];
            }
        }
        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! isset($row['id']) || ! is_numeric($row['id'])) {
                continue;
            }
            $id = (int) $row['id'];
            $name = $this->pickDepartmentDisplayNameFromRow($row);
            if ($name !== null && $name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function pickDepartmentDisplayNameFromRow(array $row): ?string
    {
        foreach (['departmentName', 'strDepartmentName', 'nome', 'name', 'strNome', 'strName', 'description', 'descricao'] as $k) {
            if (! array_key_exists($k, $row)) {
                continue;
            }
            $v = $row[$k];
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (is_int($v) || is_float($v)) {
                return trim((string) $v);
            }
        }

        return null;
    }

    /**
     * Valores como "#54" ou "#37" vindos da API no lugar do nome — devem ser trocados pela lista mestre.
     */
    protected function looksLikeRhidIdPlaceholder(string $value): bool
    {
        return (bool) preg_match('/^#\d+$/', trim($value));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function enrichBankHourRowsDepartmentNames(Company $company, ?User $user, array $rows): array
    {
        $map = $this->departmentIdToNameMap($company, $user);
        if ($map === []) {
            return $rows;
        }
        foreach ($rows as $i => $row) {
            $id = $row['idDepartment'] ?? null;
            if ($id === null || $id === '' || ! is_numeric($id)) {
                continue;
            }
            $idInt = (int) $id;
            if (! isset($map[$idInt])) {
                continue;
            }
            $current = $row['departmentName'] ?? null;
            if (is_string($current) && trim($current) !== '' && ! $this->looksLikeRhidIdPlaceholder($current)) {
                continue;
            }
            $rows[$i]['departmentName'] = $map[$idInt];
        }

        return $rows;
    }

    /**
     * Banco de horas na data de referencia.
     *
     * Por padrao: uma unica chamada a person_banco_horas?date= (API RHID).
     * Com config rhid.bank_hours_aggregate: lista colaboradores paginada + consultas em lotes.
     *
     * @return array{date: string, rows: list<array<string, mixed>>, source: string}
     */
    public function allPersonBankHoursAggregated(
        Company $company,
        ?User $user,
        string $date,
        int $listPageSize = 200,
        int $bankChunk = 50,
    ): array {
        if (! config('rhid.bank_hours_aggregate')) {
            $rows = $this->personBankHours($company, $user, ['date' => $date]);

            return [
                'date' => $date,
                'rows' => $rows,
                'source' => 'person_banco_horas',
            ];
        }

        $listPageSize = max(1, min(500, $listPageSize));
        $bankChunk = max(1, min(200, $bankChunk));

        $ids = [];
        try {
            $page = 0;
            while (true) {
                $listJson = $this->listPersons($company, $user, [
                    'page' => $page,
                    'maxSize' => $listPageSize,
                ]);
                $batch = $this->extractPersonIdsFromListResponse($listJson);
                foreach ($batch as $id) {
                    $ids[] = $id;
                }
                if (count($batch) < $listPageSize) {
                    break;
                }
                $page++;
                if ($page > 500) {
                    break;
                }
            }
        } catch (RhidApiException) {
            $rows = $this->personBankHours($company, $user, ['date' => $date]);

            return [
                'date' => $date,
                'rows' => $rows,
                'source' => 'person_banco_horas_sem_lista',
            ];
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            $rows = $this->personBankHours($company, $user, ['date' => $date]);

            return [
                'date' => $date,
                'rows' => $rows,
                'source' => 'person_banco_horas_sem_ids',
            ];
        }

        $merged = [];
        foreach (array_chunk($ids, $bankChunk) as $chunk) {
            $part = $this->personBankHours($company, $user, [
                'date' => $date,
                'people' => array_values($chunk),
            ]);
            foreach ($part as $row) {
                $merged[] = $row;
            }
        }

        return [
            'date' => $date,
            'rows' => $merged,
            'source' => 'aggregated_by_person_ids',
        ];
    }

    /**
     * Remove campos sensiveis e pesados da resposta RHID antes de enviar ao browser.
     *
     * `balance` e `saldo` permanecem na allowlist para tenants que so enviam esses aliases;
     * a escolha do valor exibido segue precedencia em canonicalizeRhidBankHourBalanceFields.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function shrinkBankHourRowsForClient(array $rows): array
    {
        $keep = array_flip([
            'name', 'nome', 'strNome', 'strName', 'strPersonName', 'personName', 'socialName', 'strSocialName',
            'registration', 'matricula', 'strMatricula', 'cpf', 'pis', 'strPis',
            'saldoBancoHoras', 'strSaldoBancoHoras', 'bancoHoras', 'saldo',
            'minutesBank', 'balance', 'totalBancoHoras', 'strBanco', 'strSaldo',
            'departmentName', 'idDepartment',
            'roleName', 'idPersonRole',
            'excluded',
            'idPerson', 'id_funcionario',
        ]);
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = array_intersect_key($row, $keep);
        }

        return $out;
    }

    /**
     * Lista JSON (0..n-1) com chaves inteiras ou string-numericas consecutivas.
     *
     * @param  array<int|string, mixed>  $arr
     */
    protected function isSequentialRowList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        $expected = 0;
        foreach (array_keys($arr) as $k) {
            $i = is_int($k) ? $k : (is_string($k) && ctype_digit((string) $k) ? (int) $k : null);
            if ($i !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    /**
     * Copia profunda via JSON para snapshot antes do merge (apenas auditoria local).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function deepCopyRowForRhidMergeLog(array $row): array
    {
        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false || $json === '') {
            return $row;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : $row;
    }

    /**
     * Campos relevantes para comparar se o merge alterou algo (evita log em massa).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function snapshotForRhidMergeLog(array $row): array
    {
        $out = [];
        foreach (['idPerson', 'id', 'id_funcionario', 'strPersonName', 'personName', 'name', 'nome', 'departmentName', 'roleName', 'idDepartment', 'idPersonRole', 'registration', 'matricula', 'cpf', 'pis', 'strSaldoBancoHoras', 'saldoBancoHoras'] as $k) {
            if (array_key_exists($k, $row)) {
                $out[$k] = $row[$k];
            }
        }
        foreach (['person', 'Person', 'pessoa', 'Pessoa'] as $nk) {
            if (! isset($row[$nk]) || ! is_array($row[$nk])) {
                continue;
            }
            $inner = $row[$nk];
            $nested = [];
            foreach (['id', 'strPersonName', 'personName', 'name', 'nome', 'departmentName', 'roleName', 'idDepartment', 'idPersonRole', 'strSaldoBancoHoras', 'saldoBancoHoras'] as $ik) {
                if (array_key_exists($ik, $inner)) {
                    $nested[$ik] = $inner[$ik];
                }
            }
            if ($nested !== []) {
                $out[$nk] = $nested;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    protected function logRhidPersonRowMerge(string $context, array $before, array $after): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $fullAudit = (bool) config('rhid.merge_audit_full', false);
        if (! $fullAudit) {
            $snapB = $this->snapshotForRhidMergeLog($before);
            $snapA = $this->snapshotForRhidMergeLog($after);
            if (json_encode($snapB) === json_encode($snapA)) {
                return;
            }
        }

        $encodePayload = function (array $data) use ($fullAudit): string {
            $payload = $fullAudit ? $data : $this->snapshotForRhidMergeLog($data);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($json === false) {
                return '{"_error":"json_encode falhou"}';
            }
            if (strlen($json) > 65536) {
                return substr($json, 0, 65536).'…[truncado]';
            }

            return $json;
        };

        Log::debug('RHID merge person row (antes/depois)', [
            'context' => $context,
            'before_json' => $encodePayload($before),
            'after_json' => $encodePayload($after),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mergePersonNestedIntoBankHourRow(array $row, ?string $auditContext = null): array
    {
        $before = $this->deepCopyRowForRhidMergeLog($row);

        $nestedKeys = ['person', 'Person', 'pessoa', 'Pessoa'];
        /** Campos de texto: cadastro em `person` tem precedencia sobre a raiz (alinha com telas RHID). */
        $stringLift = [
            'nome', 'name', 'strNome', 'strName', 'strPersonName', 'personName',
            'socialName', 'strSocialName',
            'registration', 'matricula', 'strMatricula', 'cpf', 'pis', 'strPis',
            'departmentName', 'roleName',
        ];
        /** IDs: so preenche na raiz se ainda vazios (evita trocar id incorretamente). */
        $idLift = ['idDepartment', 'idPersonRole'];
        foreach ($nestedKeys as $nk) {
            if (! isset($row[$nk]) || ! is_array($row[$nk])) {
                continue;
            }
            $inner = $row[$nk];
            foreach ($stringLift as $f) {
                if (! isset($inner[$f]) || $inner[$f] === null || $inner[$f] === '') {
                    continue;
                }
                if (is_string($inner[$f]) && trim($inner[$f]) === '') {
                    continue;
                }
                $row[$f] = $inner[$f];
            }
            foreach ($idLift as $f) {
                $empty = ! array_key_exists($f, $row) || $row[$f] === null || $row[$f] === '';
                if ($empty && isset($inner[$f]) && ($inner[$f] !== null && $inner[$f] !== '')) {
                    $row[$f] = $inner[$f];
                }
            }
            $idEmpty = ! array_key_exists('idPerson', $row) || $row['idPerson'] === null || $row['idPerson'] === '';
            if ($idEmpty && isset($inner['id']) && is_numeric($inner['id'])) {
                $row['idPerson'] = (int) $inner['id'];
            }
        }

        $row = $this->canonicalizeRhidBankHourBalanceFields($row);

        $this->logRhidPersonRowMerge($auditContext ?? 'mergePersonNestedIntoBankHourRow', $before, $row);

        return $row;
    }

    /**
     * Unifica aliases de saldo BH (camelCase, PascalCase, grafias da API) e da prioridade:
     * raiz primeiro, depois person/Person (ultimo vence — alinha com espelho quando o aninhado traz strSaldo).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function canonicalizeRhidBankHourBalanceFields(array $row): array
    {
        $chunks = [];
        $rootFlat = [];
        foreach ($row as $k => $v) {
            $ks = (string) $k;
            if (in_array($ks, ['person', 'Person', 'pessoa', 'Pessoa'], true)) {
                continue;
            }
            $rootFlat[$k] = $v;
        }
        $chunks[] = $rootFlat;
        foreach (['person', 'Person', 'pessoa', 'Pessoa'] as $nk) {
            if (isset($row[$nk]) && is_array($row[$nk])) {
                $chunks[] = $row[$nk];
            }
        }

        $str = null;
        $num = null;
        $strOrder = $this->rhidBankBalanceStrKeyPrecedence();
        $numOrder = $this->rhidBankBalanceNumericKeyPrecedence();
        foreach ($chunks as $src) {
            $chunkStr = $this->pickRhidBankStringFromSource($src, $strOrder);
            $chunkNum = $this->pickRhidBankNumericFromSource($src, $numOrder);
            if ($chunkStr !== null) {
                $str = $chunkStr;
            }
            if ($chunkNum !== null) {
                $num = $chunkNum;
            }
        }

        if ($str !== null) {
            $row['strSaldoBancoHoras'] = $str;
        }
        if ($num !== null) {
            $row['saldoBancoHoras'] = $num;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $src
     * @param  list<string>  $orderedLowerAliases
     */
    protected function pickRhidBankStringFromSource(array $src, array $orderedLowerAliases): ?string
    {
        $byLower = $this->lowerKeyMapFromArray($src);
        foreach ($orderedLowerAliases as $lc) {
            if (! array_key_exists($lc, $byLower)) {
                continue;
            }
            $v = $byLower[$lc];
            if ($v === null || $v === '') {
                continue;
            }
            $s = is_string($v) ? trim($v) : (string) $v;
            if ($s !== '') {
                return $s;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $src
     * @param  list<string>  $orderedLowerAliases
     */
    protected function pickRhidBankNumericFromSource(array $src, array $orderedLowerAliases): int|float|null
    {
        $byLower = $this->lowerKeyMapFromArray($src);
        foreach ($orderedLowerAliases as $lc) {
            if (! array_key_exists($lc, $byLower)) {
                continue;
            }
            $v = $byLower[$lc];
            if ($v === null || $v === '') {
                continue;
            }
            if (is_numeric($v)) {
                return 0 + $v;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $src
     * @return array<string, mixed>
     */
    protected function lowerKeyMapFromArray(array $src): array
    {
        $out = [];
        foreach ($src as $k => $v) {
            if (! is_string($k) && ! is_int($k)) {
                continue;
            }
            $out[strtolower((string) $k)] = $v;
        }

        return $out;
    }

    /**
     * Ordem: mais especifico primeiro; `balance` / `saldo` genericos por ultimo.
     *
     * @return list<string>
     */
    protected function rhidBankBalanceStrKeyPrecedence(): array
    {
        return ['strsaldobancohoras', 'strsaldobanco', 'strsaldo', 'strbanco'];
    }

    /**
     * @return list<string>
     */
    protected function rhidBankBalanceNumericKeyPrecedence(): array
    {
        return [
            'saldobancohoras',
            'bancohoras',
            'totalbancohoras',
            'minutesbank',
            'vlsaldobancohoras',
            'vlsaldo',
            'vlbancohoras',
            'saldobanco',
            'balance',
            'saldo',
        ];
    }

    /**
     * @param  array<int|string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    protected function normalizeBankHoursRows(array $json, int $depth = 0): array
    {
        if ($depth > 20) {
            return [$json];
        }

        if ($json === []) {
            return [];
        }

        if ($this->isSequentialRowList($json)) {
            /** @var list<array<string, mixed>> $out */
            $out = [];
            foreach ($json as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }

            return $out;
        }

        $listKeys = [
            'data', 'rows', 'items', 'results', 'list', 'persons', 'values',
            'personBankHours', 'PersonBankHours', 'd',
        ];
        foreach ($listKeys as $lk) {
            if (isset($json[$lk]) && is_array($json[$lk])) {
                return $this->normalizeBankHoursRows($json[$lk], $depth + 1);
            }
        }

        /** @var array<string, mixed> $json */
        return [$json];
    }

    /**
     * @param  array<string, mixed>  $listJson
     * @return list<int>
     */
    protected function extractPersonIdsFromListResponse(array $listJson): array
    {
        $rows = $listJson['data'] ?? $listJson;
        if (! is_array($rows)) {
            return [];
        }
        if ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1)) {
            $rows = [$rows];
        }
        $ids = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row['id']) && is_numeric($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function massPersonShift(Company $company, ?User $user, array $items): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/personshift.svc/insertlist',
            [
                'body' => $items,
                'auditAction' => 'rhid.personshift.insertlist',
            ],
        );

        return $this->decodeJson($r, 'personshift.insertlist');
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Response $response, string $context): array
    {
        if ($response->failed()) {
            throw RhidApiException::fromResponse($response, $context);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RhidApiException('Resposta RHID inválida (não JSON).', $response->status());
        }

        return $json;
    }
}
