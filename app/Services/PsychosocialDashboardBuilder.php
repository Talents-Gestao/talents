<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyGroupResult;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResult;
use App\Support\Nr1BandLabels;
use App\Support\Nr1GroupPrivacy;
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
        SurveyMethodVersion::CODE_TALENTS39_V1 => '1,00–2,33 Menor sinalização · 2,34–3,66 Atenção · 3,67–5,00 Maior sinalização',
    ];

    /** Método da campanha em montagem; define os rótulos de exibição das faixas. */
    private string $methodCode = SurveyMethodVersion::CODE_PRODUCTION_V1;

    public const OTHERS_LABEL = 'Outros Setores';

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
        $minGroupSize = Nr1GroupPrivacy::minFor($survey);

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
        $this->methodCode = $methodCode;

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
            'others' => $this->others($survey, $minGroupSize),
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
        if (($dashboard['others'] ?? null) !== null) {
            $rows[] = $row('outros_setores', self::OTHERS_LABEL, null, $dashboard['others']);
            foreach ($dashboard['others']['dimensions'] as $dimension) {
                $rows[] = $row('outros_setores_dimensao', self::OTHERS_LABEL, $dimension['title'], $dimension);
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
        return Nr1GroupPrivacy::publishableDepartmentIds($respondentsByDepartment, $minGroupSize);
    }

    /**
     * @return array{count: ?int, status: string, value_label: string, hint: ?string, respondents_hint: ?string, participation_label: ?string, alert: ?string}
     */
    private function eligibleCard(Survey $survey, int $respondents): array
    {
        $card = $this->eligibleBase($survey, $respondents);

        // Adesão = respondentes válidos ÷ elegíveis × 100; sem elegíveis consistentes, fica sem percentual.
        $rate = $card['status'] === self::ELIGIBLE_OK && (int) $card['count'] > 0
            ? round($respondents / (int) $card['count'] * 100, 1)
            : null;

        return $card + [
            'participation_percent' => $rate,
            'participation_percent_label' => $rate === null ? null : number_format($rate, 1, ',', '.').'%',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eligibleBase(Survey $survey, int $respondents): array
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
        // Contagens por pergunta e valor sobre a campanha inteira (nunca por setor).
        /** @var Collection<int, Collection<int, int>> $countsByQuestion */
        $countsByQuestion = SurveyAnswer::query()
            ->selectRaw('survey_answers.survey_template_question_id as question_id, survey_answers.value as answer_value, COUNT(*) as total')
            ->join('survey_responses', 'survey_responses.id', '=', 'survey_answers.survey_response_id')
            ->where('survey_responses.survey_id', $survey->id)
            ->whereNotNull('survey_responses.completed_at')
            ->whereNotNull('survey_answers.value')
            ->groupBy('survey_answers.survey_template_question_id', 'survey_answers.value')
            ->get()
            ->groupBy('question_id')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(fn ($row) => [(int) $row->answer_value => (int) $row->total]));

        $items = [];
        foreach ($survey->template?->sections ?? [] as $section) {
            foreach ($section->questions as $question) {
                $counts = [];
                for ($value = 1; $value <= 5; $value++) {
                    $counts[$value] = (int) ($countsByQuestion[$question->id][$value] ?? 0);
                }
                $n = array_sum($counts);

                // Enunciado favorável (reverse_score): favorável = Frequentemente + Sempre.
                // Enunciado desfavorável: favorável = Nunca + Raramente. Sempre sobre a base válida da pergunta.
                $positive = $counts[4] + $counts[5];
                $negative = $counts[1] + $counts[2];
                $favorable = $question->reverse_score ? $positive : $negative;
                $unfavorable = $question->reverse_score ? $negative : $positive;

                $items[] = [
                    'id' => (int) $question->id,
                    'section_title' => (string) $section->title,
                    'body' => (string) $question->body,
                    'n' => $n,
                    'quality_alert' => $n !== $respondents,
                    'counts' => $counts,
                    'statement' => $question->reverse_score ? 'favorable' : 'unfavorable',
                    'favorable_pct' => $n > 0 ? round($favorable / $n * 100, 1) : null,
                    'unfavorable_pct' => $n > 0 ? round($unfavorable / $n * 100, 1) : null,
                ];
            }
        }

        return $items;
    }

    /**
     * "Outros Setores": só aparece com respondentes suficientes; abaixo do mínimo a exibição é suprimida
     * (as respostas continuam no índice geral). Nunca traz a composição dos setores.
     *
     * @return array<string, mixed>|null
     */
    private function others(Survey $survey, int $minGroupSize): ?array
    {
        $rows = SurveyGroupResult::query()
            ->where('survey_id', $survey->id)
            ->where('group_key', SurveyGroupResult::GROUP_OTHERS)
            ->with('section')
            ->get();

        $overall = $rows->first(fn (SurveyGroupResult $r) => $r->survey_template_section_id === null);

        if ($overall === null || (int) $overall->respondent_count < $minGroupSize) {
            return null;
        }

        return array_merge($this->presentRow($overall), [
            'name' => self::OTHERS_LABEL,
            'dimensions' => $rows
                ->filter(fn (SurveyGroupResult $r) => $r->survey_template_section_id !== null)
                ->map(fn (SurveyGroupResult $r) => $this->presentDimension($r))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array{score: string, risk_level: string, band_label: string, n: int}
     */
    private function presentRow(SurveyResult|SurveyGroupResult $result): array
    {
        return [
            'score' => (string) $result->getRawOriginal('average_score'),
            'risk_level' => (string) $result->risk_level,
            'band_label' => Nr1BandLabels::label($result->risk_level, $this->methodCode),
            'n' => (int) $result->respondent_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDimension(SurveyResult|SurveyGroupResult $result): array
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
