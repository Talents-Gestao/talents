<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PsychosocialMeasureException;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialMeasureEvent;
use App\Models\Survey;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Regras do acompanhamento novo de medidas. Não lê nem escreve action_plan_items.
 */
final class PsychosocialMeasureService
{
    /** Campos editáveis por pessoa; status tem fluxo próprio e origem/campanha são fixas. */
    private const EDITABLE_FIELDS = [
        'factor_label',
        'evidence_basis',
        'measure_description',
        'responsible_text',
        'suggested_due_date',
        'sst_priority',
        'evidence_text',
        'effectiveness_note',
        'effectiveness_checked_at',
        'sort_order',
    ];

    /** O motor nunca preenche responsável, prioridade SST, status nem implantação. */
    private const ENGINE_FORBIDDEN_FIELDS = [
        'responsible_text',
        'sst_priority',
        'status',
        'implemented_at',
        'implemented_by',
        'effectiveness_note',
        'effectiveness_checked_at',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function createManual(Survey $survey, array $data, User $actor): PsychosocialMeasure
    {
        $status = (string) ($data['status'] ?? PsychosocialMeasure::STATUS_TO_APPROVE);
        $this->assertKnownStatus($status);

        $attributes = array_merge($this->editableSubset($data), [
            'survey_id' => $survey->id,
            'company_id' => $survey->company_id,
            'origin' => PsychosocialMeasure::ORIGIN_MANUAL,
            'status' => $status,
        ]);

        if ($status === PsychosocialMeasure::STATUS_IMPLEMENTED) {
            $attributes['implemented_at'] = now();
            $attributes['implemented_by'] = $actor->id;
        }

        return $this->createWithEvent($attributes, $actor, $data['reason'] ?? null);
    }

    /**
     * Sugestão do motor: nasce «A aprovar», como indício do questionário, sem responsável nem prioridade.
     *
     * @param  array<string, mixed>  $data
     */
    public function createEngineSuggestion(Survey $survey, array $data): PsychosocialMeasure
    {
        $forbidden = array_values(array_filter(
            self::ENGINE_FORBIDDEN_FIELDS,
            fn (string $field): bool => array_key_exists($field, $data) && $data[$field] !== null
        ));

        if ($forbidden !== []) {
            throw new PsychosocialMeasureException(
                'Sugestão do motor não pode preencher: '.implode(', ', $forbidden).'.'
            );
        }

        $basis = $data['evidence_basis'] ?? PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION;
        if ($basis !== PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION) {
            throw new PsychosocialMeasureException('Sugestão do motor só pode registrar indício do questionário.');
        }

        $attributes = array_merge($this->editableSubset($data), [
            'survey_id' => $survey->id,
            'company_id' => $survey->company_id,
            'origin' => PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION,
            'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
            'status' => PsychosocialMeasure::STATUS_TO_APPROVE,
        ]);

        return $this->createWithEvent($attributes, null, null);
    }

    public function changeStatus(PsychosocialMeasure $measure, string $status, ?User $actor, ?string $reason = null): PsychosocialMeasure
    {
        $this->assertKnownStatus($status);

        if ($status === PsychosocialMeasure::STATUS_IMPLEMENTED && $actor === null) {
            throw new PsychosocialMeasureException('«Implantada» só pode ser registrada por um usuário.');
        }

        $previous = $measure->status;
        if ($previous === $status) {
            return $measure;
        }

        return DB::transaction(function () use ($measure, $status, $actor, $reason, $previous): PsychosocialMeasure {
            $measure->status = $status;

            if ($status === PsychosocialMeasure::STATUS_IMPLEMENTED) {
                $measure->implemented_at = now();
                $measure->implemented_by = $actor?->id;
            } else {
                $measure->implemented_at = null;
                $measure->implemented_by = null;
            }

            $measure->save();
            $this->recordEvent($measure, $actor, 'status', $previous, $status, $reason);

            return $measure;
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(PsychosocialMeasure $measure, array $changes, User $actor, ?string $reason = null): PsychosocialMeasure
    {
        $notEditable = array_diff(array_keys($changes), self::EDITABLE_FIELDS);
        if ($notEditable !== []) {
            throw new PsychosocialMeasureException('Campos não editáveis por esta operação: '.implode(', ', $notEditable).'.');
        }

        return DB::transaction(function () use ($measure, $changes, $actor, $reason): PsychosocialMeasure {
            $before = [];
            foreach (array_keys($changes) as $field) {
                $before[$field] = $this->stringify($measure->getAttribute($field));
            }

            $measure->fill($changes);
            $measure->save();

            foreach (array_keys($changes) as $field) {
                $after = $this->stringify($measure->getAttribute($field));
                if ($after !== $before[$field]) {
                    $this->recordEvent($measure, $actor, $field, $before[$field], $after, $reason);
                }
            }

            return $measure;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createWithEvent(array $attributes, ?User $actor, ?string $reason): PsychosocialMeasure
    {
        return DB::transaction(function () use ($attributes, $actor, $reason): PsychosocialMeasure {
            $measure = PsychosocialMeasure::query()->create($attributes);
            $this->recordEvent($measure, $actor, PsychosocialMeasureEvent::FIELD_CREATED, null, $measure->status, $reason);

            return $measure;
        });
    }

    private function recordEvent(
        PsychosocialMeasure $measure,
        ?User $actor,
        string $field,
        ?string $oldValue,
        ?string $newValue,
        ?string $reason
    ): void {
        PsychosocialMeasureEvent::query()->create([
            'measure_id' => $measure->id,
            'user_id' => $actor?->id,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
        ]);
    }

    private function assertKnownStatus(string $status): void
    {
        if (! in_array($status, PsychosocialMeasure::STATUSES, true)) {
            throw new PsychosocialMeasureException("Status de medida inválido: «{$status}».");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function editableSubset(array $data): array
    {
        return array_intersect_key($data, array_flip(self::EDITABLE_FIELDS));
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
