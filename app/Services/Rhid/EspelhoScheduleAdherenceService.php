<?php

namespace App\Services\Rhid;

use App\Models\Company;
use App\Models\RhidEspelhoDay;
use App\Models\RhidEspelhoImport;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class EspelhoScheduleAdherenceService
{
    public const MAX_RANGE_DAYS = 93;

    public const TOP_RANK = 10;

    /** Destaques na API/UI: colaboradores com melhor / pior aderência às marcações esperadas (entrada + almoço). */
    public const HIGHLIGHT_RANK = 5;

    /** ISO-8601: 1 = seg … 7 = dom — alinhado a PunchScheduleSettingsService::DAY_KEYS */
    private const DAY_KEY_BY_ISO = [
        1 => 'seg',
        2 => 'ter',
        3 => 'qua',
        4 => 'qui',
        5 => 'sex',
        6 => 'sab',
        7 => 'dom',
    ];

    public function __construct(
        private readonly PunchScheduleSettingsService $scheduleSettings,
        private readonly RhidPersonSchedulePreferenceService $personSchedulePreferences,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function aggregateForCompany(
        Company $company,
        CarbonInterface $ini,
        CarbonInterface $fim,
        ?int $idPerson = null,
    ): array {
        $settings = $this->scheduleSettings->getForCompany($company);
        $tolerance = $this->toleranceMinutes($settings);

        $days = $this->loadDedupedDays($company->id, $ini, $fim, $idPerson);

        $prefMap = $this->personSchedulePreferences->secondLunchMapForCompany($company->id);

        $byPerson = [];
        /** @var array<string, true> datas YYYY-MM-DD com pelo menos um colaborador analisável (4 batidas) no período */
        $datasCalendarioComAnalise = [];

        foreach ($days as $item) {
            /** @var RhidEspelhoDay $day */
            $day = $item['day'];
            $idPersonRow = (int) $item['id_person'];
            $rowJson = is_array($day->row_json) ? $day->row_json : [];
            $refDate = Carbon::parse($day->ref_date)->startOfDay();
            $isoDow = (int) $refDate->format('N');
            $dayKey = self::DAY_KEY_BY_ISO[$isoDow] ?? null;
            if ($dayKey === null) {
                continue;
            }

            $daySchedule = $settings['dias'][$dayKey] ?? null;
            if (! is_array($daySchedule) || empty($daySchedule['ativo'])) {
                continue;
            }

            $fragment = $this->pickFragment($rowJson, $idPersonRow);
            if ($fragment === null) {
                continue;
            }

            $nome = trim((string) ($fragment['nome'] ?? ''));
            if ($nome === '') {
                $nome = '—';
            }

            $useSecond = $prefMap[$idPersonRow] ?? false;
            $analysis = $this->analyzeDayFragment($fragment, $daySchedule, $tolerance, $settings, $useSecond);
            if ($analysis === null) {
                if (! isset($byPerson[$idPersonRow])) {
                    $byPerson[$idPersonRow] = $this->emptyPersonAgg($idPersonRow, $nome);
                }
                $byPerson[$idPersonRow]['dias_insuficientes']++;
                $byPerson[$idPersonRow]['nome'] = $nome;

                continue;
            }

            if (! isset($byPerson[$idPersonRow])) {
                $byPerson[$idPersonRow] = $this->emptyPersonAgg($idPersonRow, $nome);
            }
            $byPerson[$idPersonRow]['nome'] = $nome;
            $byPerson[$idPersonRow]['dias_analisados']++;
            $datasCalendarioComAnalise[$refDate->toDateString()] = true;
            $byPerson[$idPersonRow]['total_atraso_entrada_minutos'] += $analysis['atraso_entrada_minutos'];
            $byPerson[$idPersonRow]['maior_atraso_entrada_minutos'] = max(
                $byPerson[$idPersonRow]['maior_atraso_entrada_minutos'],
                $analysis['atraso_entrada_minutos'],
            );

            if ($analysis['tem_infracao_almoco']) {
                $byPerson[$idPersonRow]['dias_com_infracao_almoco']++;
            }
            $byPerson[$idPersonRow]['total_minutos_atraso_saida_almoco'] += $analysis['atraso_saida_almoco_minutos'];
            $byPerson[$idPersonRow]['total_minutos_atraso_volta_almoco'] += $analysis['atraso_volta_almoco_minutos'];
        }

        $rankingAtrasos = array_values(array_filter($byPerson, fn (array $p): bool => $p['dias_analisados'] > 0));
        usort($rankingAtrasos, function (array $a, array $b): int {
            if ($a['total_atraso_entrada_minutos'] !== $b['total_atraso_entrada_minutos']) {
                return $b['total_atraso_entrada_minutos'] <=> $a['total_atraso_entrada_minutos'];
            }
            if ($a['maior_atraso_entrada_minutos'] !== $b['maior_atraso_entrada_minutos']) {
                return $b['maior_atraso_entrada_minutos'] <=> $a['maior_atraso_entrada_minutos'];
            }

            return strcasecmp($a['nome'], $b['nome']);
        });
        $rankingAtrasos = array_slice($rankingAtrasos, 0, self::TOP_RANK);

        $rankingAlmoco = array_values(array_filter($byPerson, fn (array $p): bool => $p['dias_analisados'] > 0));
        $minTotaisAlmoco = static function (array $p): int {
            return (int) ($p['total_minutos_atraso_saida_almoco'] + $p['total_minutos_atraso_volta_almoco']);
        };
        usort($rankingAlmoco, function (array $a, array $b) use ($minTotaisAlmoco): int {
            if ($a['dias_com_infracao_almoco'] !== $b['dias_com_infracao_almoco']) {
                return $b['dias_com_infracao_almoco'] <=> $a['dias_com_infracao_almoco'];
            }
            if ($minTotaisAlmoco($a) !== $minTotaisAlmoco($b)) {
                return $minTotaisAlmoco($b) <=> $minTotaisAlmoco($a);
            }

            return strcasecmp($a['nome'], $b['nome']);
        });
        $rankingAlmoco = array_slice($rankingAlmoco, 0, self::TOP_RANK);

        $highlightsPool = array_values(array_filter($byPerson, fn (array $p): bool => $p['dias_analisados'] > 0));
        $highlightRows = array_map(fn (array $p): array => $this->buildAdherenceHighlightRow($p), $highlightsPool);

        $rankingPiorAderencia = $highlightRows;
        usort($rankingPiorAderencia, function (array $a, array $b): int {
            if ($a['total_minutos_penalidade'] !== $b['total_minutos_penalidade']) {
                return $b['total_minutos_penalidade'] <=> $a['total_minutos_penalidade'];
            }
            if ($a['dias_com_infracao_almoco'] !== $b['dias_com_infracao_almoco']) {
                return $b['dias_com_infracao_almoco'] <=> $a['dias_com_infracao_almoco'];
            }

            return strcasecmp($a['nome'], $b['nome']);
        });
        $rankingPiorAderencia = array_slice($rankingPiorAderencia, 0, self::HIGHLIGHT_RANK);

        $rankingMelhorAderencia = $highlightRows;
        usort($rankingMelhorAderencia, function (array $a, array $b): int {
            if ($a['total_minutos_penalidade'] !== $b['total_minutos_penalidade']) {
                return $a['total_minutos_penalidade'] <=> $b['total_minutos_penalidade'];
            }
            if ($a['dias_com_infracao_almoco'] !== $b['dias_com_infracao_almoco']) {
                return $a['dias_com_infracao_almoco'] <=> $b['dias_com_infracao_almoco'];
            }
            if ($a['dias_analisados'] !== $b['dias_analisados']) {
                return $b['dias_analisados'] <=> $a['dias_analisados'];
            }

            return strcasecmp($a['nome'], $b['nome']);
        });
        $rankingMelhorAderencia = array_slice($rankingMelhorAderencia, 0, self::HIGHLIGHT_RANK);

        // Soma por colaborador (vários × mesmo dia civil); também contamos dias civis distintos com análise OK.
        $diasAnalisadosTotal = array_sum(array_column($byPerson, 'dias_analisados'));
        $diasCalendarioDistintos = count($datasCalendarioComAnalise);
        $diasInsuficientesTotal = array_sum(array_column($byPerson, 'dias_insuficientes'));

        $diagnostics = $this->buildDiagnostics(
            $company,
            $ini,
            $fim,
            $settings,
            $idPerson,
            $diasCalendarioDistintos,
            $diasInsuficientesTotal,
            count($byPerson),
        );

        return [
            'resumo' => [
                'ini' => $ini->toDateString(),
                'fim' => $fim->toDateString(),
                'tolerancia_minutos' => $tolerance,
                'dias_registro_analisados' => $diasAnalisadosTotal,
                'dias_calendario_distintos' => $diasCalendarioDistintos,
                'dias_insuficientes_total' => $diasInsuficientesTotal,
                'colaboradores_com_dados' => count(array_filter($byPerson, fn ($p) => $p['dias_analisados'] > 0 || $p['dias_insuficientes'] > 0)),
            ],
            'ranking_atrasos_entrada' => $rankingAtrasos,
            'ranking_infracoes_almoco' => $rankingAlmoco,
            'ranking_pior_aderencia_marcacoes' => $rankingPiorAderencia,
            'ranking_melhor_aderencia_marcacoes' => $rankingMelhorAderencia,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * Diagnostico do periodo: ajuda a UI a explicar "por que zerou" — status dos imports, configuracao de horarios,
     * dias uteis configurados, e uma dica do problema mais provavel.
     *
     * @param  array<string, mixed>  $settings  Settings normalizados de horarios da empresa
     * @return array<string, mixed>
     */
    public function buildDiagnostics(
        Company $company,
        CarbonInterface $ini,
        CarbonInterface $fim,
        array $settings,
        ?int $idPerson,
        int $diasCalendarioDistintos,
        int $diasInsuficientesTotal,
        int $colaboradoresAtingidos,
    ): array {
        $importsBase = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($ini, $fim): void {
                $q->whereBetween('period_ini', [$ini->toDateString(), $fim->toDateString()])
                    ->orWhereBetween('period_fim', [$ini->toDateString(), $fim->toDateString()])
                    ->orWhere(function ($q2) use ($ini, $fim): void {
                        $q2->where('period_ini', '<=', $ini->toDateString())
                            ->where('period_fim', '>=', $fim->toDateString());
                    });
            });

        if ($idPerson !== null) {
            $importsBase->where('id_person', $idPerson);
        }

        $importsCount = (clone $importsBase)->count();
        $statusBreakdown = (clone $importsBase)
            ->selectRaw('parse_status, COUNT(*) as c')
            ->groupBy('parse_status')
            ->pluck('c', 'parse_status')
            ->toArray();
        $porStatus = [
            'ok' => (int) ($statusBreakdown['ok'] ?? 0),
            'pending' => (int) ($statusBreakdown['pending'] ?? 0),
            'failed' => (int) ($statusBreakdown['failed'] ?? 0),
        ];

        $ultimosProblemas = (clone $importsBase)
            ->whereIn('parse_status', ['pending', 'failed'])
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'id_person', 'period_ini', 'period_fim', 'parse_status', 'parse_error', 'created_at'])
            ->map(static function ($r): array {
                $err = (string) ($r->parse_error ?? '');

                return [
                    'id' => (int) $r->id,
                    'id_person' => (int) $r->id_person,
                    'period_ini' => optional($r->period_ini)->toDateString(),
                    'period_fim' => optional($r->period_fim)->toDateString(),
                    'parse_status' => (string) $r->parse_status,
                    'parse_error_short' => $err !== '' ? mb_substr($err, 0, 220) : null,
                    'created_at' => optional($r->created_at)->toIso8601String(),
                ];
            })
            ->all();

        $diasConfigurados = 0;
        $diasAtivosKeys = [];
        foreach (PunchScheduleSettingsService::DAY_KEYS as $k) {
            $d = $settings['dias'][$k] ?? null;
            if (is_array($d) && ! empty($d['ativo']) && is_string($d['entrada'] ?? null)) {
                $diasConfigurados++;
                $diasAtivosKeys[] = $k;
            }
        }

        $diasUteisNoPeriodo = 0;
        $cursor = $ini->copy()->startOfDay();
        $end = $fim->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $iso = (int) $cursor->format('N');
            $k = self::DAY_KEY_BY_ISO[$iso] ?? null;
            if ($k !== null && in_array($k, $diasAtivosKeys, true)) {
                $diasUteisNoPeriodo++;
            }
            $cursor->addDay();
        }

        $temHorarios = $diasConfigurados > 0;

        $hint = $this->buildDiagnosticHint(
            $importsCount,
            $porStatus,
            $temHorarios,
            $diasUteisNoPeriodo,
            $diasCalendarioDistintos,
            $diasInsuficientesTotal,
            $colaboradoresAtingidos,
        );

        return [
            'imports_no_periodo' => $importsCount,
            'imports_por_status' => $porStatus,
            'ultimos_problemas' => $ultimosProblemas,
            'horarios_configurados' => $temHorarios,
            'dias_uteis_configurados' => $diasConfigurados,
            'dias_uteis_no_periodo' => $diasUteisNoPeriodo,
            'tolerancia_minutos' => $this->toleranceMinutes($settings),
            'hint' => $hint,
        ];
    }

    /**
     * @param  array{ok:int,pending:int,failed:int}  $porStatus
     */
    private function buildDiagnosticHint(
        int $importsCount,
        array $porStatus,
        bool $temHorarios,
        int $diasUteisNoPeriodo,
        int $diasCalendarioDistintos,
        int $diasInsuficientesTotal,
        int $colaboradoresAtingidos,
    ): ?string {
        if ($importsCount === 0) {
            return 'Nenhum espelho importado para este período. Importe PDFs na sub-aba "Espelho e importação".';
        }
        if ($porStatus['pending'] > 0 && $porStatus['ok'] === 0) {
            return $porStatus['pending'].' import(s) ainda em processamento (fila). Confirme que o worker da fila está ativo no servidor (queue:work) e aguarde alguns segundos, ou use "Reprocessar agora" para parsear sob demanda.';
        }
        if ($porStatus['failed'] > 0 && $porStatus['ok'] === 0) {
            return $porStatus['failed'].' import(s) falharam no parser. Veja o erro abaixo — comum: Python/pymupdf não instalado no container.';
        }
        if (! $temHorarios) {
            return 'Horários da empresa não foram configurados — sem escala não há como medir aderência. Acesse "Configurar horários da empresa".';
        }
        if ($diasUteisNoPeriodo === 0) {
            return 'Nenhum dia útil ativo dentro do período selecionado (segundo a escala da empresa). Ajuste o período ou ative dias da semana na configuração.';
        }
        if ($porStatus['ok'] > 0 && $colaboradoresAtingidos === 0) {
            return 'Imports concluídos, mas não há colaboradores casados com o conteúdo do PDF. Reprocesse o import ou verifique se o id_person bate com o cabeçalho do espelho.';
        }
        if ($porStatus['ok'] > 0 && $diasInsuficientesTotal > 0 && $diasCalendarioDistintos === 0) {
            return 'Os PDFs foram lidos, mas todos os dias estão com menos de 4 batidas (ent_1/sai_1/ent_2/sai_2). Verifique se o espelho está com as marcações completas.';
        }
        if ($porStatus['ok'] > 0 && $diasCalendarioDistintos === 0) {
            return 'Imports OK mas nenhum dia foi analisável (sem combinação válida entre marcações do PDF e escala da empresa).';
        }

        return null;
    }

    /**
     * Linha agregada para destaque de aderência (entrada + minutos de atraso no almoço; dias com qualquer infração de almoço).
     *
     * @param  array<string, mixed>  $p  Retorno de emptyPersonAgg + campos preenchidos
     * @return array<string, mixed>
     */
    private function buildAdherenceHighlightRow(array $p): array
    {
        $alm = (int) ($p['total_minutos_atraso_saida_almoco'] + $p['total_minutos_atraso_volta_almoco']);
        $ent = (int) $p['total_atraso_entrada_minutos'];

        return [
            'id_person' => $p['id_person'],
            'nome' => $p['nome'],
            'dias_analisados' => (int) $p['dias_analisados'],
            'dias_com_infracao_almoco' => (int) $p['dias_com_infracao_almoco'],
            'total_atraso_entrada_minutos' => $ent,
            'total_minutos_atraso_almoco' => $alm,
            'total_minutos_penalidade' => $ent + $alm,
        ];
    }

    /**
     * Marcacoes do espelho (import mais recente por dia) para um colaborador no periodo — ex.: modal de aderência.
     *
     * @return array<string, mixed>
     */
    public function personMarksForAdherencePeriod(
        Company $company,
        CarbonInterface $ini,
        CarbonInterface $fim,
        int $idPerson,
    ): array {
        $settings = $this->scheduleSettings->getForCompany($company);
        $tolerance = $this->toleranceMinutes($settings);
        $useSecond = $this->personSchedulePreferences->getUseSecondLunchInterval($company, $idPerson);
        $days = $this->loadDedupedDays($company->id, $ini, $fim, $idPerson);

        $rows = [];
        $nome = '—';

        foreach ($days as $item) {
            /** @var RhidEspelhoDay $day */
            $day = $item['day'];
            $rowJson = is_array($day->row_json) ? $day->row_json : [];
            $fragment = $this->pickFragment($rowJson, $idPerson);

            $refDate = Carbon::parse($day->ref_date)->startOfDay();
            $isoDow = (int) $refDate->format('N');
            $dayKey = self::DAY_KEY_BY_ISO[$isoDow] ?? null;

            if ($fragment !== null) {
                $n = trim((string) ($fragment['nome'] ?? ''));
                if ($n !== '') {
                    $nome = $n;
                }
            }

            $daySchedule = ($dayKey !== null) ? ($settings['dias'][$dayKey] ?? null) : null;
            $escalaAtiva = is_array($daySchedule) && ! empty($daySchedule['ativo']);

            $ent1 = $fragment !== null ? $this->normalizeSlot($fragment['ent_1'] ?? null) : null;
            $sai1 = $fragment !== null ? $this->normalizeSlot($fragment['sai_1'] ?? null) : null;
            $ent2 = $fragment !== null ? $this->normalizeSlot($fragment['ent_2'] ?? null) : null;
            $sai2 = $fragment !== null ? $this->normalizeSlot($fragment['sai_2'] ?? null) : null;

            if (! $escalaAtiva) {
                $situacao = 'sem_escala';
            } elseif ($fragment === null || $this->analyzeDayFragment($fragment, $daySchedule, $tolerance, $settings, $useSecond) === null) {
                $situacao = 'insuficiente';
            } else {
                $situacao = 'analisavel';
            }

            $rows[] = [
                'ref_date' => $refDate->toDateString(),
                'ent_1' => $ent1,
                'sai_1' => $sai1,
                'ent_2' => $ent2,
                'sai_2' => $sai2,
                'situacao' => $situacao,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['ref_date'], $b['ref_date']));

        return [
            'id_person' => $idPerson,
            'nome' => $nome,
            'periodo' => [
                'ini' => $ini->toDateString(),
                'fim' => $fim->toDateString(),
            ],
            'tolerancia_minutos' => $tolerance,
            'dias' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function toleranceMinutes(array $settings): int
    {
        $t = $settings['tolerancia_minutos'] ?? 15;
        $t = is_numeric($t) ? (int) $t : 15;

        return max(0, min(120, $t));
    }

    /**
     * @return array{h:int,m:int}|null
     */
    public function parseTime(?string $s): ?array
    {
        if ($s === null || $s === '') {
            return null;
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $s)) {
            return null;
        }
        $h = (int) substr($s, 0, 2);
        $m = (int) substr($s, 3, 2);
        if ($h > 23 || $m > 59) {
            return null;
        }

        return ['h' => $h, 'm' => $m];
    }

    /**
     * Minutos desde meia-noite (0–1439).
     */
    public function minutesSinceMidnight(?string $hhmm): ?int
    {
        $p = $this->parseTime($hhmm);
        if ($p === null) {
            return null;
        }

        return $p['h'] * 60 + $p['m'];
    }

    /**
     * Diferença actual − esperado em minutos (pode ser negativo).
     */
    public function diffMinutes(?string $actual, ?string $expected): ?int
    {
        $a = $this->minutesSinceMidnight($actual);
        $e = $this->minutesSinceMidnight($expected);
        if ($a === null || $e === null) {
            return null;
        }

        return $a - $e;
    }

    /**
     * Horarios esperados de saida/volta do almoco (1º ou 2º intervalo) conforme configuracao da empresa e preferencia do colaborador.
     *
     * @param  array<string, mixed>  $daySchedule  Um dia de settings['dias'][*]
     * @param  array<string, mixed>  $scheduleSettings  Settings normalizados completos (incl. segundo_almoco)
     * @return array{0: string, 1: string}|null [saida, volta] em HH:mm
     */
    public function expectedLunchTimesFromDay(array $daySchedule, array $scheduleSettings, bool $useSecond): ?array
    {
        if ($useSecond && ! empty($scheduleSettings['segundo_almoco'])) {
            $a = $daySchedule['almoco2_inicio'] ?? null;
            $b = $daySchedule['almoco2_fim'] ?? null;
            if (is_string($a) && is_string($b)
                && preg_match('/^\d{2}:\d{2}$/', $a) && preg_match('/^\d{2}:\d{2}$/', $b)) {
                return [$a, $b];
            }
        }

        $a = $daySchedule['saida_almoco'] ?? null;
        $b = $daySchedule['volta_almoco'] ?? null;
        if (! is_string($a) || ! is_string($b)) {
            return null;
        }

        return [$a, $b];
    }

    /**
     * @param  array<string, mixed>  $fragment
     * @param  array<string, mixed>  $daySchedule
     * @param  array<string, mixed>  $scheduleSettings  Settings completos para resolver 1º vs 2º almoco
     * @return array<string, mixed>|null null = insuficiente / não aplicável
     */
    public function analyzeDayFragment(
        array $fragment,
        array $daySchedule,
        int $tolerance,
        array $scheduleSettings = [],
        bool $useSecondLunchInterval = false,
    ): ?array {
        $ent1 = $this->normalizeSlot($fragment['ent_1'] ?? null);
        $sai1 = $this->normalizeSlot($fragment['sai_1'] ?? null);
        $ent2 = $this->normalizeSlot($fragment['ent_2'] ?? null);
        $sai2 = $this->normalizeSlot($fragment['sai_2'] ?? null);

        if ($ent1 === null || $sai1 === null || $ent2 === null || $sai2 === null) {
            return null;
        }

        $entradaEsp = $daySchedule['entrada'] ?? null;
        if (! is_string($entradaEsp)) {
            return null;
        }

        $lunchPair = $this->expectedLunchTimesFromDay($daySchedule, $scheduleSettings, $useSecondLunchInterval);
        if ($lunchPair === null) {
            return null;
        }
        [$saidaAlmocoEsp, $voltaAlmocoEsp] = $lunchPair;

        $T = $tolerance;

        $mEnt1 = $this->minutesSinceMidnight($ent1);
        $mSai1 = $this->minutesSinceMidnight($sai1);
        $mEnt2 = $this->minutesSinceMidnight($ent2);
        $mSaidaAlm = $this->minutesSinceMidnight($saidaAlmocoEsp);
        $mVoltaAlm = $this->minutesSinceMidnight($voltaAlmocoEsp);

        if ($mEnt1 === null || $mSai1 === null || $mEnt2 === null || $mSaidaAlm === null || $mVoltaAlm === null) {
            return null;
        }

        $mEntradaEsp = $this->minutesSinceMidnight($entradaEsp);
        if ($mEntradaEsp === null) {
            return null;
        }

        $atrasoEntrada = max(0, $mEnt1 - $mEntradaEsp);

        $atrasoSaidaAlmoco = max(0, $mSai1 - $mSaidaAlm - $T);
        $atrasoVoltaAlmoco = max(0, $mEnt2 - $mVoltaAlm - $T);

        $duracaoReal = $mEnt2 - $mSai1;
        $duracaoEsp = $mVoltaAlm - $mSaidaAlm;

        $almocoCurto = $duracaoReal < $duracaoEsp - $T;
        $almocoLongo = $duracaoReal > $duracaoEsp + $T;
        $saidaCedo = $mSai1 < $mSaidaAlm - $T;

        $temInfracao = $atrasoSaidaAlmoco > 0 || $atrasoVoltaAlmoco > 0 || $almocoCurto || $almocoLongo || $saidaCedo;

        return [
            'atraso_entrada_minutos' => $atrasoEntrada,
            'atraso_saida_almoco_minutos' => $atrasoSaidaAlmoco,
            'atraso_volta_almoco_minutos' => $atrasoVoltaAlmoco,
            'almoco_curto' => $almocoCurto,
            'almoco_longo' => $almocoLongo,
            'saida_almoco_cedo' => $saidaCedo,
            'tem_infracao_almoco' => $temInfracao,
        ];
    }

    private function emptyPersonAgg(int $idPerson, string $nome): array
    {
        return [
            'id_person' => $idPerson,
            'nome' => $nome,
            'dias_analisados' => 0,
            'dias_insuficientes' => 0,
            'total_atraso_entrada_minutos' => 0,
            'maior_atraso_entrada_minutos' => 0,
            'dias_com_infracao_almoco' => 0,
            'total_minutos_atraso_saida_almoco' => 0,
            'total_minutos_atraso_volta_almoco' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $rowJson
     */
    private function pickFragment(array $rowJson, int $idPerson): ?array
    {
        $cols = $rowJson['colaboradores'] ?? null;
        if (! is_array($cols) || $cols === []) {
            return null;
        }
        if (count($cols) === 1) {
            return is_array($cols[0]) ? $cols[0] : null;
        }

        foreach ($cols as $c) {
            if (! is_array($c)) {
                continue;
            }
            $pid = $c['id_person'] ?? $c['idPerson'] ?? null;
            if ($pid !== null && (int) $pid === $idPerson) {
                return $c;
            }
        }

        return is_array($cols[0]) ? $cols[0] : null;
    }

    /**
     * @return list<array{day: RhidEspelhoDay, id_person: int}>
     */
    private function loadDedupedDays(int $companyId, CarbonInterface $ini, CarbonInterface $fim, ?int $idPerson): array
    {
        $q = RhidEspelhoDay::query()
            ->select(['rhid_espelho_days.*'])
            ->join('rhid_espelho_imports as i', 'i.id', '=', 'rhid_espelho_days.import_id')
            ->where('i.company_id', $companyId)
            ->where('i.parse_status', 'ok')
            ->whereBetween('rhid_espelho_days.ref_date', [$ini->toDateString(), $fim->toDateString()]);

        if ($idPerson !== null) {
            $q->where('i.id_person', $idPerson);
        }

        /** @var Collection<int, RhidEspelhoDay> $rows */
        $rows = $q->with(['import'])->get();

        $best = [];
        foreach ($rows as $day) {
            $import = $day->import;
            if ($import === null) {
                continue;
            }
            $key = $import->id_person.'|'.$day->ref_date->toDateString();
            $ts = $import->updated_at?->getTimestamp() ?? 0;
            if (! isset($best[$key]) || $ts > $best[$key]['ts']) {
                $best[$key] = ['ts' => $ts, 'day' => $day, 'id_person' => (int) $import->id_person];
            }
        }

        $out = [];
        foreach ($best as $item) {
            $out[] = ['day' => $item['day'], 'id_person' => $item['id_person']];
        }

        return $out;
    }

    private function normalizeSlot(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $s)) {
            return $s;
        }

        return null;
    }
}
