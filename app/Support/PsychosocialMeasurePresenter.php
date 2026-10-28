<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialMeasureEvent;
use App\Models\PsychosocialMeasureEvidence;
use App\Services\PsychosocialMeasureService;

/**
 * Forma única de mostrar uma medida do plano (Talents e empresa): campos do Caderno §11,
 * sugerido × confirmado, aferição, evidências e histórico antes/depois.
 */
final class PsychosocialMeasurePresenter
{
    /** Nome legível de cada campo que aparece no histórico. */
    public const FIELD_LABELS = [
        'created' => 'Criação',
        'status' => 'Status',
        'factor_label' => 'Fator / dimensão',
        'measure_description' => 'Ação proposta',
        'objective' => 'Objetivo',
        'expected_result' => 'Resultado esperado',
        'responsible_text' => 'Responsável sugerido',
        'suggested_due_date' => 'Prazo sugerido',
        'suggested_due_origin' => 'Origem do prazo sugerido',
        'suggested_due_note' => 'Justificativa do prazo sugerido',
        'indicator' => 'Indicador',
        'evidence_basis' => 'Base da evidência',
        'confirmed_responsible' => 'Responsável confirmado',
        'confirmed_due_date' => 'Prazo confirmado',
        'effectiveness_note' => 'Verificação de eficácia',
        'effectiveness_result' => 'Resultado da eficácia',
        'effectiveness_checked_at' => 'Data da verificação',
        'evidence' => 'Evidência de execução',
        'sst_priority' => 'Prioridade SST',
        'evidence_text' => 'Texto da evidência',
        'sort_order' => 'Ordem',
    ];

    /**
     * @param  array<string, string>  $evidenceDownloadUrls  evidence id => url (vazio quando a tela não baixa)
     * @return array<string, mixed>
     */
    public static function present(PsychosocialMeasure $measure, array $evidenceDownloadUrls = []): array
    {
        $measure->loadMissing(['evidences.user:id,name', 'confirmedBy:id,name', 'confirmedResponsibleUser:id,name']);

        return [
            'id' => $measure->id,
            'status' => $measure->status,
            'status_label' => $measure->planLabel(),
            'factor_label' => $measure->factor_label,
            'measure_description' => $measure->measure_description,
            'objective' => $measure->objective,
            'expected_result' => $measure->expected_result,
            'indicator' => $measure->indicator,
            'origin' => $measure->origin,
            'suggested' => [
                'responsible' => $measure->responsible_text,
                'due_date' => $measure->suggested_due_date?->toDateString(),
                'due_origin' => $measure->suggested_due_origin,
                'due_origin_label' => PsychosocialMeasureService::DUE_ORIGINS[$measure->suggested_due_origin] ?? null,
                'due_note' => $measure->suggested_due_note,
            ],
            'confirmed' => $measure->hasConfirmedResponsible() ? [
                'responsible_user_id' => $measure->confirmed_responsible_user_id,
                'responsible' => collect([$measure->confirmedResponsibleUser?->name, $measure->confirmed_responsible_text])->filter()->implode(' · '),
                'responsible_text' => $measure->confirmed_responsible_text,
                'due_date' => $measure->confirmed_due_date?->toDateString(),
                'approved_at' => $measure->confirmed_at?->format('d/m/Y'),
                'approved_by' => $measure->confirmedBy?->name,
            ] : null,
            'effectiveness' => [
                'note' => $measure->effectiveness_note,
                'result' => $measure->effectiveness_result,
                'result_label' => PsychosocialMeasure::EFFECTIVENESS_RESULTS[$measure->effectiveness_result] ?? null,
                'checked_at' => $measure->effectiveness_checked_at?->format('d/m/Y'),
            ],
            'evidences' => $measure->evidences->map(fn (PsychosocialMeasureEvidence $evidence) => [
                'id' => $evidence->id,
                'kind' => $evidence->kind,
                'description' => $evidence->description,
                'file_name' => $evidence->file_name,
                'author' => $evidence->user?->name,
                'created_at' => $evidence->created_at?->format('d/m/Y H:i'),
                'download_url' => $evidenceDownloadUrls[$evidence->id] ?? null,
            ])->values()->all(),
            'history' => self::history($measure),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function history(PsychosocialMeasure $measure): array
    {
        return $measure->events()->with('user:id,name')->get()
            ->map(fn (PsychosocialMeasureEvent $event) => [
                'id' => $event->id,
                'field' => $event->field,
                'field_label' => self::FIELD_LABELS[$event->field] ?? $event->field,
                'old' => self::value($event->field, $event->old_value),
                'new' => self::value($event->field, $event->new_value),
                'author' => $event->user?->name ?? 'Sistema',
                'at' => $event->created_at?->format('d/m/Y H:i'),
                'reason' => $event->reason,
            ])
            ->values()
            ->all();
    }

    private static function value(string $field, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($field) {
            'status' => PsychosocialMeasure::PLAN_LABELS[$value] ?? $value,
            'effectiveness_result' => PsychosocialMeasure::EFFECTIVENESS_RESULTS[$value] ?? $value,
            default => $value,
        };
    }
}
