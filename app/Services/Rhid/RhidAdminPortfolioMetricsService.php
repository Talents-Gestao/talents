<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Models\Company;
use App\Models\RhidAuditLog;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Models\User;
use App\Support\Rhid\PontoPeopleSnapshot;
use App\Support\RhidBankBalanceFormat;
use App\Support\RhidBankBalanceParser;
use App\Support\RhidJustificationAnalytics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RhidAdminPortfolioMetricsService
{
    private const CACHE_TTL_SECONDS = 1200;

    private const CONCURRENCY_BATCH = 3;

    public function __construct(
        private readonly RhidComplianceService $compliance,
        private readonly EspelhoScheduleAdherenceService $adherence,
        private readonly RhidMonitoringService $monitoring,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function metricsForCompany(Company $company, ?User $user, bool $refresh = false): array
    {
        if (! $company->rhidConfigured()) {
            return [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'segment' => $company->segment,
                'status' => 'not_configured',
                'rhid_configured' => false,
                'message' => 'Integração RHID não configurada para esta empresa.',
                'people' => PontoPeopleSnapshot::empty(),
            ];
        }

        $period = $this->currentPeriod();
        $results = $this->fetchMetricsForCompanies(collect([$company]), $user, $period, $refresh);

        return $results[0] ?? [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'status' => 'error',
            'error' => 'Não foi possível carregar métricas.',
        ];
    }

    /**
     * @param  Collection<int, Company>  $companies
     * @param  array<string, string>  $period
     * @return list<array<string, mixed>>
     */
    protected function fetchMetricsForCompanies(Collection $companies, ?User $user, array $period, bool $refresh): array
    {
        $out = [];
        $chunks = $companies->chunk(self::CONCURRENCY_BATCH);

        foreach ($chunks as $batch) {
            foreach ($batch as $company) {
                $cacheKey = sprintf(
                    'rhid_admin_metrics_%d_%s',
                    $company->id,
                    $period['cache_key'],
                );

                if ($refresh) {
                    Cache::forget($cacheKey);
                }

                try {
                    $metric = Cache::remember(
                        $cacheKey,
                        self::CACHE_TTL_SECONDS,
                        fn () => $this->buildCompanyMetrics($company, $user, $period),
                    );
                    $out[] = $metric;
                } catch (\Throwable $e) {
                    report($e);
                    $out[] = $this->errorPayload($company, $e);
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $period
     * @return array<string, mixed>
     */
    protected function buildCompanyMetrics(Company $company, ?User $user, array $period): array
    {
        $nr1 = $this->nr1Snapshot($company);

        $bankToday = ['rows' => []];
        $bankPrev = ['rows' => []];
        try {
            $bankToday = $this->compliance->allPersonBankHoursAggregated(
                $company,
                $user,
                $period['bank_date_reference'],
            );
            $bankPrev = $this->compliance->allPersonBankHoursAggregated(
                $company,
                $user,
                $period['bank_date_mom'],
            );
        } catch (\Throwable $e) {
            report($e);
        }

        $bankStats = $this->bankStats($bankToday['rows'] ?? []);
        $bankPrevStats = $this->bankStats($bankPrev['rows'] ?? []);
        $momDelta = ($bankStats['avg_minutes'] !== null && $bankPrevStats['avg_minutes'] !== null)
            ? round($bankStats['avg_minutes'] - $bankPrevStats['avg_minutes'], 1)
            : null;

        $adherenceCurrent = $this->adherence->aggregateForCompany(
            $company,
            Carbon::parse($period['month_ini']),
            Carbon::parse($period['month_fim']),
        );
        $adherencePrevious = $this->adherence->aggregateForCompany(
            $company,
            Carbon::parse($period['prev_month_ini']),
            Carbon::parse($period['prev_month_fim']),
        );

        $justCurrent = $this->emptyJustificationStats();
        $justPrevious = $this->emptyJustificationStats();
        try {
            $typeMap = RhidJustificationAnalytics::buildTypeMapFromPayload(
                $this->compliance->listJustificationTypes($company, $user),
            );
            $justCurrent = $this->justificationStats($company, $user, $period['just_ini'], $period['just_fim'], $typeMap);
            $justPrevious = $this->justificationStats($company, $user, $period['prev_just_ini'], $period['prev_just_fim'], $typeMap);
        } catch (\Throwable $e) {
            report($e);
        }

        $bankRows = $this->formatBankRowsList($bankToday['rows'] ?? []);

        $atestadosMomPct = null;
        if ($justPrevious['atestados'] > 0) {
            $atestadosMomPct = round(
                100 * ($justCurrent['atestados'] - $justPrevious['atestados']) / $justPrevious['atestados'],
                1,
            );
        }

        $people = PontoPeopleSnapshot::empty();
        try {
            $monthly = $this->adherence->monthlyComplianceRanking(
                $company,
                Carbon::parse($period['month_ini']),
                Carbon::parse($period['month_fim']),
                $user,
            );
            $people = is_array($monthly['people'] ?? null)
                ? $monthly['people']
                : PontoPeopleSnapshot::fromComplianceRanking(
                    is_array($monthly['ranking_cumprimento_mes'] ?? null) ? $monthly['ranking_cumprimento_mes'] : [],
                    is_array($monthly['resumo'] ?? null) ? $monthly['resumo'] : [],
                );
        } catch (\Throwable $e) {
            report($e);
        }

        $punches = [];
        try {
            $punches = $this->monitoring->ultimasMarcacoes($company, $user);
        } catch (RhidApiException) {
            $punches = [];
        }

        $punchCount = count($punches);
        $punchDistinct = count(array_unique(array_filter(array_map(
            fn ($p) => $p['idPerson'] ?? $p['id'] ?? $p['id_funcionario'] ?? null,
            is_array($punches) ? $punches : [],
        ))));

        $resumo = $adherenceCurrent['resumo'] ?? [];
        $dias = (int) ($resumo['dias_calendario_distintos'] ?? 0);
        $colabs = (int) ($resumo['colaboradores_com_dados'] ?? 0);

        $operationalAlert = $this->computeOperationalAlert(
            $bankStats['negative_pct'],
            $bankStats['avg_minutes'],
            $dias,
            $atestadosMomPct,
        );

        $dualRisk = ($nr1['risk_level'] ?? null) === 'red' && $operationalAlert === 'high';

        $integration = $this->integrationStatus($company);

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'segment' => $company->segment,
            'status' => 'ok',
            'rhid_configured' => true,
            'fetched_at' => now()->toIso8601String(),
            'period' => [
                'bank_reference_date' => $period['bank_date_reference'],
                'bank_reference_label' => $period['bank_date_reference_label'],
                'bank_mom_date' => $period['bank_date_mom'],
                'bank_mom_label' => $period['bank_date_mom_label'],
                'month_label' => $period['label_current'],
            ],
            'nr1' => $nr1,
            'bank' => [
                'avg_minutes' => $bankStats['avg_minutes'],
                'negative_count' => $bankStats['negative_count'],
                'numeric_count' => $bankStats['numeric_count'],
                'negative_pct' => $bankStats['negative_pct'],
                'mom_delta_minutes' => $momDelta,
                'worst_three' => $bankStats['worst_three'],
                'reference_date' => $period['bank_date_reference'],
                'reference_date_label' => $period['bank_date_reference_label'],
                'mom_date_label' => $period['bank_date_mom_label'],
                'rows' => $bankRows,
            ],
            'adherence' => [
                'dias' => $dias,
                'colabs' => $colabs,
                'tolerancia_minutos' => (int) ($resumo['tolerancia_minutos'] ?? 0),
                'dias_mom_delta' => $dias - (int) ($adherencePrevious['resumo']['dias_calendario_distintos'] ?? 0),
                'colabs_mom_delta' => $colabs - (int) ($adherencePrevious['resumo']['colaboradores_com_dados'] ?? 0),
                'ranking_atrasos_entrada' => $adherenceCurrent['ranking_atrasos_entrada'] ?? [],
                'ranking_infracoes_almoco' => $adherenceCurrent['ranking_infracoes_almoco'] ?? [],
                'ranking_pior_aderencia' => $adherenceCurrent['ranking_pior_aderencia_marcacoes'] ?? [],
                'worst_entrada' => array_slice($adherenceCurrent['ranking_atrasos_entrada'] ?? [], 0, 5),
                'worst_adherence' => array_slice($adherenceCurrent['ranking_pior_aderencia_marcacoes'] ?? [], 0, 3),
                'diagnostics_hint' => $this->adherenceDiagnosticsHint($adherenceCurrent['diagnostics'] ?? []),
            ],
            'justifications' => [
                'total' => $justCurrent['total'],
                'atestados' => $justCurrent['atestados'],
                'total_mom_delta' => $justCurrent['total'] - $justPrevious['total'],
                'atestados_mom_delta' => $justCurrent['atestados'] - $justPrevious['atestados'],
                'atestados_mom_pct' => $atestadosMomPct,
                'note' => $justCurrent['note'],
                'items' => $justCurrent['items'],
            ],
            'punches' => [
                'count' => $punchCount,
                'distinct_collaborators' => $punchDistinct,
                'items' => $this->formatPunchRowsList(is_array($punches) ? $punches : []),
            ],
            'people' => $people,
            'operational_alert' => $operationalAlert,
            'dual_risk' => $dualRisk,
            'integration' => $integration,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function currentPeriod(): array
    {
        $now = Carbon::now();
        $referenceDay = $now->copy()->subDay()->startOfDay();
        $bankMomDay = $referenceDay->copy()->subMonth();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $prevStart = $monthStart->copy()->subMonth()->startOfMonth();
        $prevEnd = $monthStart->copy()->subMonth()->endOfMonth();

        return [
            'cache_key' => $monthStart->format('Y-m').'_ref_'.$referenceDay->format('Ymd'),
            'month_ini' => $monthStart->toDateString(),
            'month_fim' => $monthEnd->toDateString(),
            'prev_month_ini' => $prevStart->toDateString(),
            'prev_month_fim' => $prevEnd->toDateString(),
            'just_ini' => $monthStart->format('Ymd'),
            'just_fim' => $monthEnd->format('Ymd'),
            'prev_just_ini' => $prevStart->format('Ymd'),
            'prev_just_fim' => $prevEnd->format('Ymd'),
            'bank_date_reference' => $referenceDay->format('Ymd'),
            'bank_date_reference_label' => $referenceDay->locale('pt_BR')->translatedFormat('d/m/Y'),
            'bank_date_mom' => $bankMomDay->format('Ymd'),
            'bank_date_mom_label' => $bankMomDay->locale('pt_BR')->translatedFormat('d/m/Y'),
            'label_current' => $monthStart->locale('pt_BR')->translatedFormat('F Y'),
            'label_previous' => $prevStart->locale('pt_BR')->translatedFormat('F Y'),
        ];
    }

    /**
     * @return array{risk_level: string|null, average_score: float|null, survey_id: int|null}
     */
    protected function nr1Snapshot(Company $company): array
    {
        $lastSurveyId = Survey::query()
            ->where('company_id', $company->id)
            ->max('id');

        if ($lastSurveyId === null) {
            return [
                'risk_level' => null,
                'average_score' => null,
                'survey_id' => null,
            ];
        }

        $result = SurveyResult::query()
            ->where('survey_id', $lastSurveyId)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->first(['risk_level', 'average_score']);

        return [
            'risk_level' => $result?->risk_level,
            'average_score' => $result !== null ? (float) $result->average_score : null,
            'survey_id' => (int) $lastSurveyId,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{avg_minutes: float|null, negative_count: int, numeric_count: int, negative_pct: float, worst_three: list<array<string, mixed>>}
     */
    protected function bankStats(array $rows): array
    {
        $parsed = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $m = RhidBankBalanceParser::parseMinutesFromRow($row);
            if ($m === null) {
                continue;
            }
            $parsed[] = ['row' => $row, 'minutes' => $m];
        }

        $numericCount = count($parsed);
        if ($numericCount === 0) {
            return [
                'avg_minutes' => null,
                'negative_count' => 0,
                'numeric_count' => 0,
                'negative_pct' => 0.0,
                'worst_three' => [],
            ];
        }

        $negativeCount = count(array_filter($parsed, fn (array $p) => $p['minutes'] < 0));
        $sum = array_sum(array_column($parsed, 'minutes'));

        usort($parsed, fn (array $a, array $b) => $a['minutes'] <=> $b['minutes']);
        $worstThree = array_map(fn (array $p) => [
            'name' => RhidBankBalanceParser::displayName($p['row']),
            'minutes' => $p['minutes'],
            'display' => RhidBankBalanceFormat::minutesToHhMm($p['minutes']),
        ], array_slice($parsed, 0, 3));

        return [
            'avg_minutes' => round($sum / $numericCount, 1),
            'negative_count' => $negativeCount,
            'numeric_count' => $numericCount,
            'negative_pct' => round(100 * $negativeCount / $numericCount, 1),
            'worst_three' => $worstThree,
        ];
    }

    /**
     * @return array{total: int, atestados: int, note: string, items: list<array<string, mixed>>}
     */
    protected function emptyJustificationStats(): array
    {
        return [
            'total' => 0,
            'atestados' => 0,
            'note' => '',
            'items' => [],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $typeMap
     * @return array{total: int, atestados: int, note: string, items: list<array<string, mixed>>}
     */
    protected function justificationStats(
        Company $company,
        ?User $user,
        string $ini,
        string $fim,
        array $typeMap,
    ): array {
        $payload = [
            'ini' => $ini,
            'fim' => $fim,
            'page' => 0,
            'maxSize' => 500,
        ];

        $response = $this->compliance->listJustifications($company, $user, $payload);
        $chunk = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        $recordsTotal = isset($response['recordsTotal']) && is_numeric($response['recordsTotal'])
            ? (int) $response['recordsTotal']
            : count($chunk);

        $atestados = 0;
        foreach ($chunk as $row) {
            if (is_array($row) && RhidJustificationAnalytics::isAtestadoByKeyword($row, $typeMap)) {
                $atestados++;
            }
        }

        $note = '';
        if (count($chunk) >= 500 && $recordsTotal > count($chunk)) {
            $note = 'Atestados: contagem na primeira página da amostra (máx. 500).';
        }

        $items = [];
        foreach ($chunk as $row) {
            if (! is_array($row)) {
                continue;
            }
            $items[] = [
                'person_name' => $this->justificationPersonName($row),
                'type' => RhidJustificationAnalytics::justificationTypeLabel($row['idJustificationType'] ?? null, $typeMap),
                'start' => $row['inicio'] ?? $row['ínicio'] ?? $row['start'] ?? null,
                'end' => $row['fim'] ?? $row['end'] ?? null,
                'description' => trim((string) ($row['justificativa'] ?? $row['description'] ?? '')),
                'is_atestado' => RhidJustificationAnalytics::isAtestadoByKeyword($row, $typeMap),
            ];
        }

        return [
            'total' => $recordsTotal,
            'atestados' => $atestados,
            'note' => $note,
            'items' => $items,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function formatBankRowsList(array $rows): array
    {
        $parsed = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $minutes = RhidBankBalanceParser::parseMinutesFromRow($row);
            if ($minutes === null) {
                continue;
            }
            $parsed[] = [
                'person_id' => $row['idPerson'] ?? $row['id'] ?? $row['id_funcionario'] ?? null,
                'name' => RhidBankBalanceParser::displayName($row),
                'social_name' => $this->firstFilledString($row, ['socialName', 'strSocialName', 'nomeSocial']),
                'registration' => $this->firstFilledString($row, [
                    'registration', 'matricula', 'strMatricula', 'pis', 'strPis',
                ]),
                'cpf' => $this->firstFilledString($row, ['cpf', 'strCpf', 'document', 'documento']),
                'department' => $this->firstFilledString($row, [
                    'departmentName', 'strDepartmentName', 'departamento', 'department',
                ]),
                'role' => $this->firstFilledString($row, [
                    'roleName', 'personRoleName', 'strPersonRole', 'cargo', 'role',
                ]),
                'minutes' => $minutes,
                'balance_display' => RhidBankBalanceFormat::minutesToHhMm($minutes),
                'excluded' => (bool) ($row['excluded'] ?? false),
            ];
        }

        usort($parsed, fn (array $a, array $b) => $a['minutes'] <=> $b['minutes']);

        return $parsed;
    }

    /**
     * @param  list<array<string, mixed>>  $punches
     * @return list<array<string, mixed>>
     */
    protected function formatPunchRowsList(array $punches): array
    {
        $out = [];
        foreach (array_slice($punches, 0, 100) as $punch) {
            if (! is_array($punch)) {
                continue;
            }
            $out[] = [
                'name' => $this->punchDisplayName($punch),
                'datetime' => $punch['data'] ?? $punch['dateTime'] ?? $punch['Data'] ?? $punch['date'] ?? null,
                'person_id' => $punch['idPerson'] ?? $punch['id'] ?? $punch['id_funcionario'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function justificationPersonName(array $row): string
    {
        foreach (['personName', 'strPersonName', 'name', 'nome', 'strNome'] as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }
        foreach (['person', 'Person'] as $nestKey) {
            $nest = isset($row[$nestKey]) && is_array($row[$nestKey]) ? $row[$nestKey] : null;
            if ($nest === null) {
                continue;
            }
            foreach (['personName', 'name', 'nome'] as $key) {
                if (isset($nest[$key]) && trim((string) $nest[$key]) !== '') {
                    return trim((string) $nest[$key]);
                }
            }
        }

        return '—';
    }

    /**
     * @param  array<string, mixed>  $punch
     */
    protected function punchDisplayName(array $punch): string
    {
        foreach (['nome', 'name', 'strNome', 'personName', 'strPersonName'] as $key) {
            if (isset($punch[$key]) && trim((string) $punch[$key]) !== '') {
                return trim((string) $punch[$key]);
            }
        }

        $pid = $punch['idPerson'] ?? $punch['id'] ?? null;
        if ($pid !== null && $pid !== '') {
            return 'ID '.$pid;
        }

        return '—';
    }

    protected function computeOperationalAlert(
        float $negativePct,
        ?float $avgMinutes,
        int $adherenceDias,
        ?float $atestadosMomPct,
    ): string {
        if (
            $negativePct >= 25.0
            || ($avgMinutes !== null && $avgMinutes <= -480)
            || $adherenceDias < 10
            || ($atestadosMomPct !== null && $atestadosMomPct >= 30.0)
        ) {
            return 'high';
        }

        if (
            $negativePct >= 15.0
            || ($avgMinutes !== null && $avgMinutes <= -240)
            || $adherenceDias < 15
            || ($atestadosMomPct !== null && $atestadosMomPct >= 15.0)
        ) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    protected function adherenceDiagnosticsHint(array $diagnostics): ?string
    {
        $hint = $diagnostics['provavel_causa'] ?? $diagnostics['hint'] ?? null;
        if (is_string($hint) && $hint !== '') {
            return $hint;
        }

        $porStatus = is_array($diagnostics['imports_por_status'] ?? null)
            ? $diagnostics['imports_por_status']
            : [];
        $failed = (int) ($porStatus['failed'] ?? 0);
        $pending = (int) ($porStatus['pending'] ?? 0);
        if ($failed > 0) {
            return 'Há espelhos com parse falhado. Confira a fila, o Python (RHID_ESPELHO_PYTHON) e o erro do import no painel do cliente.';
        }
        if ($pending > 0) {
            return 'Há espelhos ainda em parse. Se ficarem assim, a fila pode estar parada.';
        }

        $imports = (int) ($diagnostics['imports_no_periodo'] ?? 0);
        if ($imports === 0) {
            return 'Sem espelhos importados no período. Importe o PDF no painel do cliente (Marcações → Espelho); a aderência Admin usa esse histórico.';
        }

        return null;
    }

    /**
     * @return array{ok: bool, last_error: string|null, last_http_status: int|null}
     */
    protected function integrationStatus(Company $company): array
    {
        $last = RhidAuditLog::query()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->first(['http_status', 'response_summary', 'action']);

        if ($last === null || ($last->http_status !== null && (int) $last->http_status < 400)) {
            return ['ok' => true, 'last_error' => null, 'last_http_status' => null];
        }

        $summary = is_array($last->response_summary) ? $last->response_summary : [];
        $message = $summary['message'] ?? $summary['error'] ?? $last->action;

        return [
            'ok' => false,
            'last_error' => is_string($message) ? $message : 'Falha recente na API RHID.',
            'last_http_status' => $last->http_status !== null ? (int) $last->http_status : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    protected function firstFilledString(array $row, array $keys): ?string
    {
        $sources = [$row];
        foreach (['person', 'Person', 'pessoa', 'Pessoa'] as $nestedKey) {
            if (isset($row[$nestedKey]) && is_array($row[$nestedKey])) {
                $sources[] = $row[$nestedKey];
            }
        }

        foreach ($sources as $source) {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $source) || $source[$key] === null) {
                    continue;
                }
                $value = trim((string) $source[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function errorPayload(Company $company, \Throwable $e): array
    {
        $message = $e instanceof RhidApiException
            ? $e->getMessage()
            : 'Falha ao consultar RHID.';

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'segment' => $company->segment,
            'status' => 'error',
            'rhid_configured' => $company->rhidConfigured(),
            'error' => $message,
            'operational_alert' => null,
            'dual_risk' => false,
            'people' => PontoPeopleSnapshot::empty(),
        ];
    }
}
