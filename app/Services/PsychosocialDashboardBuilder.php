<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResult;
use Illuminate\Support\Collection;

/**
 * Painel gerencial novo, só de leitura. Mostra os números já gravados em survey_results;
 * nunca recalcula nem grava índice ou faixa. Idade, tempo de casa e respostas individuais não entram.
 */
class PsychosocialDashboardBuilder
{
    public const BAND_DISCLAIMER = 'Faixa do questionário. Não é classificação de risco ocupacional do PGR.';

    public const POLARITY_WARNING = 'Polaridade não revalidada';

    public const BAND_LABELS = [
        'green' => 'Leve',
        'yellow' => 'Moderado',
        'red' => 'Grave',
    ];

    public const LEGENDS = [
        SurveyMethodVersion::CODE_PRODUCTION_V1 => 'até 2,33 Leve · acima de 2,33 até 3,66 Moderado · acima de 3,66 Grave',
        SurveyMethodVersion::CODE_TALENTS39_V1 => '1,00–2,33 Leve · 2,34–3,66 Moderado · 3,67–5,00 Grave',
    ];

    public const ELIGIBLE_MISSING = 'missing';

    public const ELIGIBLE_INCONSISTENT = 'inconsistent';

    public const ELIGIBLE_OK = 'ok';

    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey): array
    {
        $survey->loadMissing(['template.sections.questions', 'methodVersion', 'instrumentVersion']);

        $results = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->with(['section', 'department'])
            ->orderBy('survey_template_section_id')
            ->orderBy('department_id')
            ->get();

        $overallRow = $results->first(fn (SurveyResult $r) => $r->survey_template_section_id === null && $r->department_id === null);
        $respondents = (int) ($overallRow?->respondent_count ?? 0);
        $minGroupSize = (int) ($survey->methodVersion?->min_group_size_new_outputs ?? 5);

        $sectorRows = $results->filter(fn (SurveyResult $r) => $r->department_id !== null && $r->survey_template_section_id === null);
        $publishedIds = self::publishableSectorIds(
            $sectorRows->mapWithKeys(fn (SurveyResult $r) => [(int) $r->department_id => (int) $r->respondent_count])->all(),
            $minGroupSize,
        );
        $sectorDimensionRows = $results
            ->filter(fn (SurveyResult $r) => $r->department_id !== null && $r->survey_template_section_id !== null)
            ->groupBy(fn (SurveyResult $r) => (int) $r->department_id);

        $sectors = $sectorRows
            ->filter(fn (SurveyResult $r) => in_array((int) $r->department_id, $publishedIds, true))
            ->sortBy(fn (SurveyResult $r) => mb_strtolower((string) ($r->department?->name ?? '')))
            ->map(fn (SurveyResult $r) => array_merge($this->presentRow($r), [
                'department_id' => (int) $r->department_id,
                'department_name' => $this->departmentName($r),
                'dimensions' => ($sectorDimensionRows->get((int) $r->department_id) ?? collect())
                    ->map(fn (SurveyResult $d) => $this->presentDimension($d))
                    ->values()
                    ->all(),
            ]))
            ->values()
            ->all();

        $methodCode = (string) ($survey->methodVersion?->code ?? SurveyMethodVersion::CODE_PRODUCTION_V1);

        return [
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
            ],
            'method' => [
                'code' => $methodCode,
                'label' => $survey->methodVersion?->label,
                'legend' => self::LEGENDS[$methodCode] ?? self::LEGENDS[SurveyMethodVersion::CODE_PRODUCTION_V1],
            ],
            'band_disclaimer' => self::BAND_DISCLAIMER,
            'polarity_warning' => $survey->instrumentVersion?->isPolarityAuditPending() ? self::POLARITY_WARNING : null,
            'min_group_size' => $minGroupSize,
            'has_results' => $overallRow !== null,
            'respondents' => $respondents,
            'eligible' => $this->eligibleCard($survey, $respondents),
            'overall' => $overallRow ? $this->presentRow($overallRow) : null,
            'dimensions' => $results
                ->filter(fn (SurveyResult $r) => $r->survey_template_section_id !== null && $r->department_id === null)
                ->map(fn (SurveyResult $r) => $this->presentDimension($r))
                ->values()
                ->all(),
            'sectors' => $sectors,
            'items' => $this->items($survey, $respondents),
        ];
    }

    /**
     * Linhas agregadas publicáveis: geral, dimensão, setor que sobreviveu à cascata e dimensão desse setor.
     *
     * @param  array<string, mixed>  $dashboard
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $dashboard): array
    {
        $method = $dashboard['method']['code'];
        $row = fn (string $scope, ?string $sector, ?string $dimension, array $cut): array => [
            'recorte' => $scope,
            'setor' => $sector,
            'dimensao' => $dimension,
            'n' => $cut['n'],
            'indice_gravado' => $cut['score'],
            'faixa_gravada' => $cut['risk_level'],
            'faixa' => $cut['band_label'],
            'metodo' => $method,
            'observacao' => self::BAND_DISCLAIMER,
        ];

        $rows = [];
        if ($dashboard['overall'] !== null) {
            $rows[] = $row('geral', null, null, $dashboard['overall']);
        }
        foreach ($dashboard['dimensions'] as $dimension) {
            $rows[] = $row('dimensao', null, $dimension['title'], $dimension);
        }
        foreach ($dashboard['sectors'] as $sector) {
            $rows[] = $row('setor', $sector['department_name'], null, $sector);
            foreach ($sector['dimensions'] as $dimension) {
                $rows[] = $row('setor_dimensao', $sector['department_name'], $dimension['title'], $dimension);
            }
        }

        return $rows;
    }

    /**
     * Supressão em cascata dos setores: oculta n < mínimo e, enquanto a soma dos ocultos ficar abaixo
     * do mínimo, oculta também o menor setor visível. Assim nenhum grupo pequeno sai por subtração.
     *
     * @param  array<int, int>  $respondentsByDepartment  department_id => n
     * @return list<int> department_id publicáveis
     */
    public static function publishableSectorIds(array $respondentsByDepartment, int $minGroupSize): array
    {
        $ordered = collect($respondentsByDepartment)
            ->map(fn (int $n, int $departmentId) => ['id' => $departmentId, 'n' => $n])
            ->sort(fn (array $a, array $b) => [$a['n'], $a['id']] <=> [$b['n'], $b['id']])
            ->values();

        [$hidden, $visible] = $ordered->partition(fn (array $sector) => $sector['n'] < $minGroupSize);
        $hiddenSum = $hidden->sum('n');
        $visible = $visible->values();

        while ($hiddenSum > 0 && $hiddenSum < $minGroupSize && $visible->isNotEmpty()) {
            $hiddenSum += $visible->shift()['n'];
        }

        return $visible->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * @return array{count: ?int, status: string, value_label: string, hint: ?string, respondents_hint: ?string, participation_label: ?string, alert: ?string}
     */
    private function eligibleCard(Survey $survey, int $respondents): array
    {
        $eligible = SurveyCampaignRegistration::query()->where('survey_id', $survey->id)->value('eligible_count');

        if ($eligible === null) {
            return [
                'count' => null,
                'status' => self::ELIGIBLE_MISSING,
                'value_label' => '—',
                'hint' => 'Aguardando total elegível',
                'respondents_hint' => 'Elegíveis não informados',
                'participation_label' => null,
                'alert' => null,
            ];
        }

        $eligible = (int) $eligible;

        if ($eligible < $respondents) {
            return [
                'count' => $eligible,
                'status' => self::ELIGIBLE_INCONSISTENT,
                'value_label' => (string) $eligible,
                'hint' => null,
                'respondents_hint' => null,
                'participation_label' => null,
                'alert' => 'O total de elegíveis informado é menor que o número de respondentes. Revise o cadastro técnico da campanha.',
            ];
        }

        return [
            'count' => $eligible,
            'status' => self::ELIGIBLE_OK,
            'value_label' => (string) $eligible,
            'hint' => null,
            'respondents_hint' => null,
            'participation_label' => "{$respondents} de {$eligible}",
            'alert' => null,
        ];
    }

    /**
     * @return list<array{id: int, section_title: string, body: string, n: int, quality_alert: bool}>
     */
    private function items(Survey $survey, int $respondents): array
    {
        /** @var Collection<int, int> $answeredByQuestion */
        $answeredByQuestion = SurveyAnswer::query()
            ->selectRaw('survey_answers.survey_template_question_id as question_id, COUNT(*) as total')
            ->join('survey_responses', 'survey_responses.id', '=', 'survey_answers.survey_response_id')
            ->where('survey_responses.survey_id', $survey->id)
            ->whereNotNull('survey_responses.completed_at')
            ->whereNotNull('survey_answers.value')
            ->groupBy('survey_answers.survey_template_question_id')
            ->pluck('total', 'question_id')
            ->map(fn ($total) => (int) $total);

        $items = [];
        foreach ($survey->template?->sections ?? [] as $section) {
            foreach ($section->questions as $question) {
                $n = (int) ($answeredByQuestion[$question->id] ?? 0);
                $items[] = [
                    'id' => (int) $question->id,
                    'section_title' => (string) $section->title,
                    'body' => (string) $question->body,
                    'n' => $n,
                    'quality_alert' => $n !== $respondents,
                ];
            }
        }

        return $items;
    }

    /**
     * @return array{score: string, risk_level: string, band_label: string, n: int}
     */
    private function presentRow(SurveyResult $result): array
    {
        return [
            'score' => (string) $result->getRawOriginal('average_score'),
            'risk_level' => (string) $result->risk_level,
            'band_label' => self::BAND_LABELS[$result->risk_level] ?? (string) $result->risk_level,
            'n' => (int) $result->respondent_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDimension(SurveyResult $result): array
    {
        return array_merge($this->presentRow($result), [
            'section_id' => (int) $result->survey_template_section_id,
            'title' => (string) ($result->section?->title ?? $result->meta['section_title'] ?? 'Dimensão'),
        ]);
    }

    private function departmentName(SurveyResult $result): string
    {
        return $result->department?->name ?? 'Setor #'.$result->department_id;
    }
}
