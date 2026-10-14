<?php

declare(strict_types=1);

namespace App\Services\Rhid;

use App\Jobs\ProcessRhidEspelhoBatchJob;
use App\Models\Company;
use App\Models\RhidEspelhoBatch;
use App\Models\RhidEspelhoDay;
use App\Support\Rhid\RhidPersonDirectory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

class RhidEspelhoAutoImportService
{
    public function __construct(
        private RhidPersonDirectory $people,
    ) {}

    /**
     * Enfileira a importação do dia fechado (ontem) para empresas com RHID pronto.
     *
     * @return list<array{
     *     company_id: int,
     *     company_name: string,
     *     action: string,
     *     reason: ?string,
     *     batch_id: ?int,
     *     people: int,
     *     backfill: bool,
     *     ini: ?string,
     *     fim: ?string
     * }>
     */
    public function enqueueClosedDay(?int $companyId = null, ?CarbonInterface $closedDay = null): array
    {
        $tz = (string) config('app.timezone', 'America/Sao_Paulo');
        $today = Carbon::now($tz)->startOfDay();
        $closedDay = $closedDay !== null
            ? Carbon::parse($closedDay->toDateString(), $tz)->startOfDay()
            : $today->copy()->subDay();

        if ($closedDay->gte($today)) {
            return [[
                'company_id' => 0,
                'company_name' => '',
                'action' => 'failed',
                'reason' => 'A importação automática só cobre o dia já fechado (ontem). Informe uma data anterior a hoje.',
                'batch_id' => null,
                'people' => 0,
                'backfill' => false,
                'ini' => null,
                'fim' => null,
            ]];
        }

        $results = [];
        foreach ($this->eligibleCompanies($companyId) as $company) {
            $results[] = $this->enqueueForCompany($company, $closedDay);
        }

        if ($companyId !== null && $results === []) {
            $company = Company::query()->find($companyId);
            $results[] = $this->skipped(
                $company,
                $company === null ? 'Empresa não encontrada.' : 'Empresa sem módulo RHID ligado ou sem credenciais.',
            );
        }

        return $results;
    }

    /**
     * @return list<Company>
     */
    private function eligibleCompanies(?int $companyId): array
    {
        return Company::query()
            ->where('is_active', true)
            ->when($companyId !== null, fn ($q) => $q->whereKey($companyId))
            ->orderBy('id')
            ->get()
            ->filter(fn (Company $company): bool => $company->hasRhidEnabled() && $company->rhidConfigured())
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     company_id: int,
     *     company_name: string,
     *     action: string,
     *     reason: ?string,
     *     batch_id: ?int,
     *     people: int,
     *     backfill: bool,
     *     ini: ?string,
     *     fim: ?string
     * }
     */
    private function enqueueForCompany(Company $company, CarbonInterface $closedDay): array
    {
        if ($this->hasOpenBatch($company)) {
            return $this->skipped($company, 'Já existe um lote de espelho em andamento.');
        }

        try {
            $personIds = $this->people
                ->fetchActivePersons($company)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        } catch (Throwable $e) {
            report($e);

            return $this->skipped($company, 'Falha ao listar colaboradores na RHID: '.$e->getMessage());
        }

        if ($personIds === []) {
            return $this->skipped($company, 'Nenhum colaborador ativo encontrado.');
        }

        $alreadyImported = $this->personIdsWithOkClosedDay($company, $closedDay);
        $personIds = array_values(array_filter(
            $personIds,
            fn (int $id): bool => ! in_array($id, $alreadyImported, true),
        ));

        if ($personIds === []) {
            return $this->skipped($company, 'Todos os colaboradores ativos já têm o dia fechado importado.');
        }

        $monthStart = $closedDay->copy()->startOfMonth();
        $needsBackfill = ! $this->companyHasOkDayInRange($company, $monthStart, $closedDay);
        $ini = $needsBackfill ? $monthStart : $closedDay;

        $batch = RhidEspelhoBatch::query()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'status' => RhidEspelhoBatch::STATUS_PENDING,
            'total' => count($personIds),
            'processed' => 0,
            'succeeded' => 0,
            'skipped' => 0,
            'current_id_person' => null,
            'skipped_person_ids' => [],
            'meta_json' => [
                'person_ids' => $personIds,
                'ini' => $ini->format('Ymd'),
                'fim' => $closedDay->format('Ymd'),
                'rhid_status' => '1',
                'list_columns' => ['TODAS_MARCACOES', 'ENTRADAS_SAIDAS'],
                'filters' => [],
                'source' => RhidEspelhoBatch::SOURCE_AUTOMATIC,
                'closed_day' => $closedDay->toDateString(),
                'backfill' => $needsBackfill,
            ],
            'message' => null,
        ]);

        ProcessRhidEspelhoBatchJob::dispatch($batch->id);

        return [
            'company_id' => $company->id,
            'company_name' => (string) $company->name,
            'action' => 'queued',
            'reason' => $needsBackfill
                ? 'Mês sem espelhos: backfill do dia 1 até o dia fechado.'
                : null,
            'batch_id' => $batch->id,
            'people' => count($personIds),
            'backfill' => $needsBackfill,
            'ini' => $ini->toDateString(),
            'fim' => $closedDay->toDateString(),
        ];
    }

    private function hasOpenBatch(Company $company): bool
    {
        return RhidEspelhoBatch::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [RhidEspelhoBatch::STATUS_PENDING, RhidEspelhoBatch::STATUS_RUNNING])
            ->exists();
    }

    /**
     * @return list<int>
     */
    private function personIdsWithOkClosedDay(Company $company, CarbonInterface $closedDay): array
    {
        return RhidEspelhoDay::query()
            ->join('rhid_espelho_imports as i', 'i.id', '=', 'rhid_espelho_days.import_id')
            ->where('i.company_id', $company->id)
            ->where('i.parse_status', 'ok')
            ->whereDate('rhid_espelho_days.ref_date', $closedDay->toDateString())
            ->pluck('i.id_person')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function companyHasOkDayInRange(
        Company $company,
        CarbonInterface $ini,
        CarbonInterface $fim,
    ): bool {
        return RhidEspelhoDay::query()
            ->join('rhid_espelho_imports as i', 'i.id', '=', 'rhid_espelho_days.import_id')
            ->where('i.company_id', $company->id)
            ->where('i.parse_status', 'ok')
            ->where('rhid_espelho_days.ref_date', '>=', $ini->toDateString())
            ->where('rhid_espelho_days.ref_date', '<=', $fim->toDateString().' 23:59:59')
            ->exists();
    }

    /**
     * @return array{
     *     company_id: int,
     *     company_name: string,
     *     action: string,
     *     reason: ?string,
     *     batch_id: ?int,
     *     people: int,
     *     backfill: bool,
     *     ini: ?string,
     *     fim: ?string
     * }
     */
    private function skipped(?Company $company, string $reason): array
    {
        return [
            'company_id' => (int) ($company?->id ?? 0),
            'company_name' => (string) ($company?->name ?? ''),
            'action' => 'skipped',
            'reason' => $reason,
            'batch_id' => null,
            'people' => 0,
            'backfill' => false,
            'ini' => null,
            'fim' => null,
        ];
    }
}
