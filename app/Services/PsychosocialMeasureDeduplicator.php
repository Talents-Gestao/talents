<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\User;
use App\Support\Nr1MeasureCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Regra única de «uma medida por dimensão» no plano de ação, usada pelo gerador (junção imediata), pela ação
 * «Unificar sugestões duplicadas» da Talents e pelo comando nr1:dedupe-engine-measures.
 *
 * Grupo = mesma campanha + mesma dimensão normalizada (Nr1MeasureCatalog::dimensionKey), em qualquer origem.
 * Mantida, nesta ordem: a que já saiu de «A aprovar» > a manual ou editada por pessoa > a com mais campos preenchidos >
 * a mais antiga. Só é removida a sugestão do motor intocada (origem engine_suggestion, «A aprovar», sem edição de
 * pessoa e sem evidência). Medida manual, editada, confirmada ou com evidência nunca é removida: quando não é a
 * mantida, fica como «requer decisão manual». Nenhuma medida existente muda, a não ser o texto da mantida quando ela
 * mesma ainda é sugestão intocada (recebe a ação das removidas, sem repetir frase). Nunca toca em action_plan_items.
 */
final class PsychosocialMeasureDeduplicator
{
    public const DECISION_MERGE = 'juntar e remover';

    public const DECISION_MANUAL = 'requer decisão manual';

    public const CONTENT_FIELDS = ['measure_description', 'indicator', 'responsible_text', 'suggested_due_date', 'suggested_due_origin', 'suggested_due_note', 'evidence_text'];

    public function __construct(private readonly PsychosocialMeasureService $measures) {}

    /**
     * Grupos com mais de uma medida, por campanha e dimensão.
     *
     * @param  Builder<PsychosocialMeasure>|null  $query  limite opcional (campanha); sem ele, todas as campanhas
     * @return list<Collection<int, PsychosocialMeasure>>
     */
    public function groups(?Builder $query = null): array
    {
        return ($query ?? PsychosocialMeasure::query())
            ->orderBy('survey_id')->orderBy('id')
            ->get()
            ->groupBy(fn (PsychosocialMeasure $m) => $m->survey_id.'|'.Nr1MeasureCatalog::dimensionKey((string) $m->factor_label))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values()
            ->all();
    }

    /** @return list<Collection<int, PsychosocialMeasure>> */
    public function groupsForSurvey(Survey $survey): array
    {
        return $this->groups(PsychosocialMeasure::query()->where('survey_id', $survey->id));
    }

    /**
     * Plano de um grupo: mantida, removidas, «requer decisão manual» e o texto da mantida após a junção.
     *
     * @param  Collection<int, PsychosocialMeasure>  $group
     * @return array{keeper: PsychosocialMeasure, remove: list<PsychosocialMeasure>, manual: list<array{measure: PsychosocialMeasure, reason: string}>, merged_description: ?string, decision: string, dimension: string}
     */
    public function plan(Collection $group): array
    {
        $info = $group->map(fn (PsychosocialMeasure $m) => $this->describe($m))->values();
        $ranked = $info->sort(fn (array $a, array $b) => [$b['advanced'], $b['by_person'], $b['filled'], $a['measure']->id]
            <=> [$a['advanced'], $a['by_person'], $a['filled'], $b['measure']->id])->values();

        $keeperInfo = $ranked->first();
        $remove = [];
        $manual = [];
        foreach ($ranked->slice(1) as $item) {
            if ($item['removable']) {
                $remove[] = $item['measure'];
            } else {
                $manual[] = ['measure' => $item['measure'], 'reason' => $this->keptReason($item)];
            }
        }

        $keeper = $keeperInfo['measure'];
        $merged = null;
        if ($keeperInfo['removable'] && $remove !== []) {
            $merged = array_reduce($remove, fn (string $text, PsychosocialMeasure $m) => Nr1MeasureCatalog::mergeDescriptions($text, (string) $m->measure_description), (string) $keeper->measure_description);
        }

        return [
            'keeper' => $keeper,
            'remove' => $remove,
            'manual' => $manual,
            'merged_description' => $merged,
            'decision' => $remove === [] ? self::DECISION_MANUAL : self::DECISION_MERGE,
            'dimension' => Nr1MeasureCatalog::v11Dimension((string) $keeper->factor_label) ?? (string) $keeper->factor_label,
        ];
    }

    /**
     * Aplica um plano: evento «Junção de sugestões duplicadas» na mantida (só IDs, sem conteúdo) e remoção das
     * sugestões intocadas. Revalida cada removida dentro da transação; a que deixou de ser removível fica.
     *
     * @param  array{keeper: PsychosocialMeasure, remove: list<PsychosocialMeasure>, merged_description: ?string}  $plan
     * @return list<int> IDs removidos
     */
    public function apply(array $plan, ?User $actor = null): array
    {
        if ($plan['remove'] === []) {
            return [];
        }

        return DB::transaction(function () use ($plan, $actor): array {
            $ids = array_map(fn (PsychosocialMeasure $m) => $m->id, $plan['remove']);
            $removed = PsychosocialMeasure::query()->whereKey($ids)->lockForUpdate()->get()
                ->filter(fn (PsychosocialMeasure $m) => (int) $m->survey_id === (int) $plan['keeper']->survey_id && $this->describe($m)['removable'])
                ->values();
            if ($removed->isEmpty()) {
                return [];
            }

            $merged = $plan['merged_description'];
            if ($merged !== null && $removed->count() !== count($ids)) {
                $merged = $removed->reduce(fn (string $text, PsychosocialMeasure $m) => Nr1MeasureCatalog::mergeDescriptions($text, (string) $m->measure_description), (string) $plan['keeper']->measure_description);
            }

            $this->measures->recordMerge($plan['keeper'], [
                'removed_ids' => $removed->pluck('id')->all(),
                'text_merged_into_kept' => $merged !== null && $merged !== $plan['keeper']->measure_description,
            ], $merged, $actor);
            $removed->each(fn (PsychosocialMeasure $m) => $m->delete());

            return $removed->pluck('id')->all();
        });
    }

    /**
     * Prévia da campanha para a tela: uma linha por grupo, sem dado de outra campanha.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(Survey $survey): array
    {
        return array_map(function (Collection $group): array {
            $plan = $this->plan($group);
            $row = fn (PsychosocialMeasure $m, ?string $reason = null) => [
                'id' => $m->id,
                'factor_label' => $m->factor_label,
                'origin_label' => $m->origin === PsychosocialMeasure::ORIGIN_MANUAL ? 'Medida da Talents' : 'Sugestão da Mia',
                'status_label' => $m->statusLabel(),
                'measure_description' => mb_strimwidth((string) $m->measure_description, 0, 160, '…'),
                'reason' => $reason,
            ];

            return [
                'dimension' => $plan['dimension'],
                'decision' => $plan['decision'],
                'kept' => $row($plan['keeper']),
                'merged' => $plan['merged_description'] !== null && $plan['merged_description'] !== $plan['keeper']->measure_description,
                'removed' => array_map(fn (PsychosocialMeasure $m) => $row($m), $plan['remove']),
                'manual' => array_map(fn (array $item) => $row($item['measure'], $item['reason']), $plan['manual']),
            ];
        }, $this->groupsForSurvey($survey));
    }

    /** @return list<int> IDs que a unificação removeria agora (para conferir a confirmação com a prévia). */
    public function removableIds(Survey $survey): array
    {
        $ids = [];
        foreach ($this->groupsForSurvey($survey) as $group) {
            array_push($ids, ...array_map(fn (PsychosocialMeasure $m) => $m->id, $this->plan($group)['remove']));
        }
        sort($ids);

        return $ids;
    }

    /**
     * Unifica todos os grupos da campanha.
     *
     * @return list<int> IDs removidos
     */
    public function applyForSurvey(Survey $survey, ?User $actor = null): array
    {
        $removed = [];
        foreach ($this->groupsForSurvey($survey) as $group) {
            array_push($removed, ...$this->apply($this->plan($group), $actor));
        }

        return $removed;
    }

    /** Sugestão do motor intocada: a única que a junção pode remover. */
    public function isRemovable(PsychosocialMeasure $measure): bool
    {
        return $this->describe($measure)['removable'];
    }

    /** @return array{measure: PsychosocialMeasure, advanced: bool, by_person: bool, edited: bool, evidences: int, filled: int, removable: bool} */
    private function describe(PsychosocialMeasure $measure): array
    {
        $advanced = $measure->status !== PsychosocialMeasure::STATUS_TO_APPROVE;
        $edited = $this->measures->isEditedByTalents($measure);
        $evidences = $measure->evidences()->count();
        $manual = $measure->origin === PsychosocialMeasure::ORIGIN_MANUAL;

        return [
            'measure' => $measure,
            'advanced' => $advanced,
            'by_person' => $manual || $edited,
            'edited' => $edited,
            'evidences' => $evidences,
            'filled' => collect(self::CONTENT_FIELDS)->filter(fn (string $field) => filled($measure->getAttribute($field)))->count(),
            'removable' => ! $manual && ! $advanced && ! $edited && $evidences === 0,
        ];
    }

    /** @param  array{measure: PsychosocialMeasure, advanced: bool, edited: bool, evidences: int}  $item */
    private function keptReason(array $item): string
    {
        return match (true) {
            $item['evidences'] > 0 => 'Tem evidência registrada.',
            $item['advanced'] => 'Já saiu de «A aprovar».',
            $item['measure']->origin === PsychosocialMeasure::ORIGIN_MANUAL => 'Medida criada pela Talents.',
            default => 'Tem alteração feita por pessoa.',
        };
    }
}
