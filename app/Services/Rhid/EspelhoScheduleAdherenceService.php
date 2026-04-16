<?php

namespace App\Services\Rhid;

use App\Models\Company;
use App\Models\RhidEspelhoDay;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class EspelhoScheduleAdherenceService
{
    public const MAX_RANGE_DAYS = 93;

    public const TOP_RANK = 10;

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

        $byPerson = [];

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

            $analysis = $this->analyzeDayFragment($fragment, $daySchedule, $tolerance);
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

        $diasAnalisadosTotal = array_sum(array_column($byPerson, 'dias_analisados'));

        return [
            'resumo' => [
                'ini' => $ini->toDateString(),
                'fim' => $fim->toDateString(),
                'tolerancia_minutos' => $tolerance,
                'dias_registro_analisados' => $diasAnalisadosTotal,
                'colaboradores_com_dados' => count(array_filter($byPerson, fn ($p) => $p['dias_analisados'] > 0 || $p['dias_insuficientes'] > 0)),
            ],
            'ranking_atrasos_entrada' => $rankingAtrasos,
            'ranking_infracoes_almoco' => $rankingAlmoco,
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
     * @param  array<string, mixed>  $fragment
     * @param  array<string, mixed>  $daySchedule
     * @return array<string, mixed>|null null = insuficiente / não aplicável
     */
    public function analyzeDayFragment(array $fragment, array $daySchedule, int $tolerance): ?array
    {
        $ent1 = $this->normalizeSlot($fragment['ent_1'] ?? null);
        $sai1 = $this->normalizeSlot($fragment['sai_1'] ?? null);
        $ent2 = $this->normalizeSlot($fragment['ent_2'] ?? null);
        $sai2 = $this->normalizeSlot($fragment['sai_2'] ?? null);

        if ($ent1 === null || $sai1 === null || $ent2 === null || $sai2 === null) {
            return null;
        }

        $entradaEsp = $daySchedule['entrada'] ?? null;
        $saidaAlmocoEsp = $daySchedule['saida_almoco'] ?? null;
        $voltaAlmocoEsp = $daySchedule['volta_almoco'] ?? null;

        if (! is_string($entradaEsp) || ! is_string($saidaAlmocoEsp) || ! is_string($voltaAlmocoEsp)) {
            return null;
        }

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
