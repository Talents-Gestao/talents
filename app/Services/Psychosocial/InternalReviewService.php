<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Exceptions\PsychosocialDocumentException;
use App\Models\PsychosocialInternalReview;
use App\Models\Survey;
use App\Models\User;

/**
 * Revisão interna da Talents (Caderno §9). Só a consultoria registra e lê. Não cria contratação, oportunidade nem
 * documento: é uma anotação de verificação antes de emitir o relatório.
 */
class InternalReviewService
{
    public const MIN_NOTE_LENGTH = 5;

    /** Itens fixos do HTML aprovado. */
    private const FIXED_ITEMS = [
        'work_demands' => ['Demandas de trabalho', 'Conferir se informações disponíveis sobre metas, ritmo e dimensionamento são suficientes.'],
        'existing_measures' => ['Medidas preventivas já existentes', 'Solicitar à empresa a descrição de práticas e controles quando necessário.'],
    ];

    /**
     * Itens de verificação: os dois do HTML mais um por dimensão em atenção (recomendação para a Suzane, pergunta 13).
     *
     * @param  list<array<string, mixed>>  $attention  `dashboard['attention']`
     * @return list<array{key: string, title: string, hint: string}>
     */
    public function items(array $attention): array
    {
        $items = [];
        foreach (self::FIXED_ITEMS as $key => [$title, $hint]) {
            $items[] = ['key' => $key, 'title' => $title, 'hint' => $hint];
        }

        foreach ($attention as $dimension) {
            $items[] = [
                'key' => 'dimension:'.$dimension['section_id'],
                'title' => (string) $dimension['title'],
                'hint' => 'Dimensão em atenção: conferir se o contexto da empresa explica o resultado antes de emitir o relatório.',
            ];
        }

        return $items;
    }

    /**
     * @param  list<string>  $checked
     * @param  list<array{key: string}>  $allowedItems
     */
    public function save(Survey $survey, User $actor, string $decision, ?string $note, array $checked, array $allowedItems): PsychosocialInternalReview
    {
        if (! $actor->isSuperAdmin() && ! $actor->isOwner()) {
            throw new PsychosocialDocumentException('A revisão interna é só da consultoria.');
        }

        if (! array_key_exists($decision, PsychosocialInternalReview::DECISION_LABELS)) {
            throw new PsychosocialDocumentException('Escolha o encaminhamento.');
        }

        $note = filled($note) ? trim((string) $note) : null;
        if ($decision !== PsychosocialInternalReview::DECISION_SUFFICIENT && ($note === null || mb_strlen($note) < self::MIN_NOTE_LENGTH)) {
            throw new PsychosocialDocumentException('Registre o motivo e os próximos passos nas anotações.');
        }

        $allowed = array_column($allowedItems, 'key');
        $checked = array_values(array_unique(array_intersect($checked, $allowed)));

        return PsychosocialInternalReview::query()->create([
            'survey_id' => $survey->id,
            'user_id' => $actor->id,
            'decision' => $decision,
            'note' => $note,
            'checked_items' => $checked,
        ]);
    }

    /**
     * @return array{current: ?array<string, mixed>, history: list<array<string, mixed>>}
     */
    public function state(Survey $survey): array
    {
        $rows = PsychosocialInternalReview::query()->where('survey_id', $survey->id)->with('user:id,name')->orderByDesc('id')->get();

        $present = fn (PsychosocialInternalReview $row): array => [
            'id' => $row->id,
            'decision' => $row->decision,
            'decision_label' => PsychosocialInternalReview::DECISION_LABELS[$row->decision] ?? $row->decision,
            'note' => $row->note,
            'checked_items' => $row->checked_items ?? [],
            'author' => $row->user?->name ?? 'Sistema',
            'at' => $row->created_at?->format('d/m/Y H:i') ?? '',
        ];

        return [
            'current' => $rows->isEmpty() ? null : $present($rows->first()),
            'history' => $rows->map($present)->all(),
        ];
    }
}
