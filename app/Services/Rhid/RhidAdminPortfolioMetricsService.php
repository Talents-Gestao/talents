<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Models\Company;
use App\Models\RhidAuditLog;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Models\User;
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
    public function portfolioMetrics(?User $user, bool $refresh = false, ?string $segment = null): array
    {
        $period = $this->currentPeriod();
        $query = Company::query()
            ->where('is_active', true)
            ->whereNotNull('rhid_email')
            ->whereNotNull('rhid_password')
            ->orderBy('name');

        if ($segment !== null && $segment !== '') {
            $query->where('segment', $segment);
        }

        $companies = $query->get();
        $configuredTotal = $companies->count();

        $companyMetrics = $this->fetchMetricsForCompanies($companies, $user, $period, $refresh);

        $successful = array_values(array_filter($companyMetrics, fn (array $m) => ($m['status'] ?? '') === 'ok'));
        $failed = array_values(array_filter($companyMetrics, fn (array $m) => ($m['status'] ?? '') === 'error'));

        $highAlert = array_filter($successful, fn (array $m) => ($m['operational_alert'] ?? '') === 'high');
        $dualRisk = array_filter($successful, fn (array $m) => ! empty($m['dual_risk']));

        $bankMinutes = array_values(array_filter(
            array_map(fn (array $m) => $m['bank']['avg_minutes'] ?? null, $successful),
            fn ($v) => $v !== null,
        ));
        $portfolioBankAvg = count($bankMinutes) > 0
            ? round(array_sum($bankMinutes) / count($bankMinutes), 1)
            : null;

        $ranking = collect($successful)
            ->sortByDesc(fn (array $m) => $this->alertSortWeight($m['operational_alert'] ?? 'low'))
            ->values()
            ->take(10)
            ->all();

        $bySegment = $this->aggregateBySegment($successful);

        return [
            'period' => $period,
            'fetched_at' => now()->toIso8601String(),
            'partial' => count($failed) > 0,
            'summary' => [
                'companies_rhid_configured' => $configuredTotal,
                'companies_loaded' => count($successful),
                'companies_failed' => count($failed),
                'portfolio_bank_avg_minutes' => $portfolioBankAvg,
                'high_alert_count' => count($highAlert),
                'high_alert_pct' => $configuredTotal > 0
                    ? round(100 * count($highAlert) / $configuredTotal, 1)
                    : 0.0,
                'dual_risk_count' => count($dualRisk),
            ],
            'companies' => $companyMetrics,
            'ranking' => $ranking,
            'by_segment' => $bySegment,
            'errors' => array_map(fn (array $m) => [
                'company_id' => $m['company_id'],
                'company_name' => $m['company_name'],
                'message' => $m['error'] ?? 'Erro desconhecido',
            ], $failed),
        ];
    }

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

        $bankToday = $this->compliance->allPersonBankHoursAggregated(
            $company,
            $user,
            $period['bank_date_today'],
        );
        $bankPrev = $this->compliance->allPersonBankHoursAggregated(
            $company,
            $user,
            $period['bank_date_prev_month_end'],
        );

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

        $typeMap = RhidJustificationAnalytics::buildTypeMapFromPayload(
            $this->compliance->listJustificationTypes($company, $user),
        );

        $justCurrent = $this->justificationStats($company, $user, $period['just_ini'], $period['just_fim'], $typeMap);
        $justPrevious = $this->justificationStats($company, $user, $period['prev_just_ini'], $period['prev_just_fim'], $typeMap);

        $atestadosMomPct = null;
        if ($justPrevious['atestados'] > 0) {
            $atestadosMomPct = round(
                100 * ($justCurrent['atestados'] - $justPrevious['atestados']) / $justPrevious['atestados'],
                1,
            );
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
            'nr1' => $nr1,
            'bank' => [
                'avg_minutes' => $bankStats['avg_minutes'],
                'negative_count' => $bankStats['negative_count'],
                'numeric_count' => $bankStats['numeric_count'],
                'negative_pct' => $bankStats['negative_pct'],
                'mom_delta_minutes' => $momDelta,
                'worst_three' => $bankStats['worst_three'],
                'date' => $period['bank_date_today'],
            ],
            'adherence' => [
                'dias' => $dias,
                'colabs' => $colabs,
                'tolerancia_minutos' => (int) ($resumo['tolerancia_minutos'] ?? 0),
                'dias_mom_delta' => $dias - (int) ($adherencePrevious['resumo']['dias_calendario_distintos'] ?? 0),
                'colabs_mom_delta' => $colabs - (int) ($adherencePrevious['resumo']['colaboradores_com_dados'] ?? 0),
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
            ],
            'punches' => [
                'count' => $punchCount,
                'distinct_collaborators' => $punchDistinct,
            ],
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
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $prevStart = $monthStart->copy()->subMonth()->startOfMonth();
        $prevEnd = $monthStart->copy()->subMonth()->endOfMonth();

        return [
            'cache_key' => $monthStart->format('Y-m'),
            'month_ini' => $monthStart->toDateString(),
            'month_fim' => $monthEnd->toDateString(),
            'prev_month_ini' => $prevStart->toDateString(),
            'prev_month_fim' => $prevEnd->toDateString(),
            'just_ini' => $monthStart->format('Ymd'),
            'just_fim' => $monthEnd->format('Ymd'),
            'prev_just_ini' => $prevStart->format('Ymd'),
            'prev_just_fim' => $prevEnd->format('Ymd'),
            'bank_date_today' => $now->format('Ymd'),
            'bank_date_prev_month_end' => $prevEnd->format('Ymd'),
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
     * @param  array<string, array<string, mixed>>  $typeMap
     * @return array{total: int, atestados: int, note: string}
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

        return [
            'total' => $recordsTotal,
            'atestados' => $atestados,
            'note' => $note,
        ];
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
        $imports = (int) ($diagnostics['imports_no_periodo'] ?? 0);
        if ($imports === 0) {
            return 'Sem espelhos importados no período — aderência pode estar incompleta.';
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
     * @param  list<array<string, mixed>>  $successful
     * @return list<array<string, mixed>>
     */
    protected function aggregateBySegment(array $successful): array
    {
        $grouped = collect($successful)->groupBy(fn (array $m) => $m['segment'] ?? 'Sem segmento');

        return $grouped->map(function (Collection $items, string $segment) {
            $alerts = $items->filter(fn (array $m) => ($m['operational_alert'] ?? '') === 'high')->count();
            $bankAvgs = $items->pluck('bank.avg_minutes')->filter(fn ($v) => $v !== null);

            return [
                'segment' => $segment,
                'companies' => $items->count(),
                'high_alert' => $alerts,
                'avg_bank_minutes' => $bankAvgs->isNotEmpty() ? round($bankAvgs->avg(), 1) : null,
            ];
        })->values()->sortByDesc('high_alert')->values()->all();
    }

    protected function alertSortWeight(string $alert): int
    {
        return match ($alert) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
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
        ];
    }
}
