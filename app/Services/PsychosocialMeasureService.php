<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PsychosocialMeasureException;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialMeasureEvent;
use App\Models\PsychosocialMeasureEvidence;
use App\Models\Survey;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Support\SurveyPublicationGate;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        'effectiveness_result',
        'sort_order',
        'objective',
        'expected_result',
        'suggested_due_origin',
        'suggested_due_note',
        'indicator',
    ];

    /** O que a Talents sugere e pode editar até a publicação (nunca marca confirmação). */
    public const SUGGESTION_FIELDS = [
        'factor_label',
        'evidence_basis',
        'measure_description',
        'objective',
        'expected_result',
        'responsible_text',
        'suggested_due_date',
        'suggested_due_origin',
        'suggested_due_note',
        'indicator',
    ];

    public const DUE_ORIGINS = ['talents' => 'Sugerido pela Talents', 'legal' => 'Prazo legal ou contratual', 'company' => 'Pedido da empresa'];

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

    /* ------------------------------------------------------------------
     | Plano de ação (Caderno §11)
     | Talents propõe e edita até "Publicado". A empresa confirma, executa e mede depois de "Publicado".
     | Campanhas anteriores ao gate (requires_publication = false) seguem abertas para os dois lados (D9).
     ------------------------------------------------------------------ */

    /**
     * Talents: edita a sugestão (ação, objetivo, responsável e prazo sugeridos, indicador). Nunca confirma.
     *
     * @param  array<string, mixed>  $changes
     */
    public function updateSuggestion(PsychosocialMeasure $measure, array $changes, User $actor, ?string $reason = null): PsychosocialMeasure
    {
        $this->assertTalentsActor($actor);
        $this->assertTalentsMayEdit($measure->survey);

        $notSuggestion = array_diff(array_keys($changes), self::SUGGESTION_FIELDS);
        if ($notSuggestion !== []) {
            throw new PsychosocialMeasureException('Campos fora da sugestão da Talents: '.implode(', ', $notSuggestion).'.');
        }

        if (isset($changes['suggested_due_origin']) && ! array_key_exists($changes['suggested_due_origin'], self::DUE_ORIGINS)) {
            throw new PsychosocialMeasureException('Origem de prazo inválida.');
        }

        $replacesValue = false;
        foreach ($changes as $field => $value) {
            $old = $this->stringify($measure->getAttribute($field));
            if ($old !== null && $old !== $this->stringify($value)) {
                $replacesValue = true;
            }
        }
        if ($replacesValue && blank($reason)) {
            throw new PsychosocialMeasureException('Informe a justificativa ao alterar uma sugestão já registrada.');
        }

        return $this->update($measure, $changes, $actor, $reason);
    }

    /**
     * Empresa: aprova responsável e prazo. Proposta → Confirmada.
     */
    public function confirm(PsychosocialMeasure $measure, User $actor, ?int $responsibleUserId, ?string $responsibleText, string $dueDate, ?string $reason = null): PsychosocialMeasure
    {
        $this->assertCompanyMayAct($measure, $actor);
        $this->assertPlanTransition($measure, PsychosocialMeasure::STATUS_PENDING);

        [$userId, $text] = $this->normalizeResponsible($measure, $responsibleUserId, $responsibleText);
        $due = $this->normalizeDueDate($dueDate);

        return DB::transaction(function () use ($measure, $actor, $userId, $text, $due, $reason): PsychosocialMeasure {
            $previousStatus = $measure->status;
            $measure->fill([
                'confirmed_responsible_user_id' => $userId,
                'confirmed_responsible_text' => $text,
                'confirmed_due_date' => $due,
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
                'status' => PsychosocialMeasure::STATUS_PENDING,
            ])->save();

            $this->recordEvent($measure, $actor, 'confirmed_responsible', $measure->responsible_text, $this->describeResponsible($userId, $text), $reason);
            $this->recordEvent($measure, $actor, 'confirmed_due_date', $measure->suggested_due_date?->toDateString(), $due, $reason);
            $this->recordEvent($measure, $actor, 'status', $previousStatus, $measure->status, $reason);

            return $measure;
        });
    }

    /**
     * Empresa: troca responsável e/ou prazo já confirmados. Exige justificativa; o histórico guarda antes e depois.
     */
    public function adjustConfirmation(PsychosocialMeasure $measure, User $actor, ?int $responsibleUserId, ?string $responsibleText, string $dueDate, string $reason): PsychosocialMeasure
    {
        $this->assertCompanyMayAct($measure, $actor);

        if (! $measure->hasConfirmedResponsible() || $measure->status === PsychosocialMeasure::STATUS_IMPLEMENTED) {
            throw new PsychosocialMeasureException('Só é possível ajustar uma medida confirmada e ainda não concluída.');
        }
        if (blank($reason)) {
            throw new PsychosocialMeasureException('Informe a justificativa do ajuste.');
        }

        [$userId, $text] = $this->normalizeResponsible($measure, $responsibleUserId, $responsibleText);
        $due = $this->normalizeDueDate($dueDate);

        return DB::transaction(function () use ($measure, $actor, $userId, $text, $due, $reason): PsychosocialMeasure {
            $oldResponsible = $this->describeResponsible($measure->confirmed_responsible_user_id, $measure->confirmed_responsible_text);
            $oldDue = $measure->confirmed_due_date?->toDateString();

            $measure->fill([
                'confirmed_responsible_user_id' => $userId,
                'confirmed_responsible_text' => $text,
                'confirmed_due_date' => $due,
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
            ])->save();

            $newResponsible = $this->describeResponsible($userId, $text);
            if ($newResponsible !== $oldResponsible) {
                $this->recordEvent($measure, $actor, 'confirmed_responsible', $oldResponsible, $newResponsible, $reason);
            }
            if ($due !== $oldDue) {
                $this->recordEvent($measure, $actor, 'confirmed_due_date', $oldDue, $due, $reason);
            }

            return $measure;
        });
    }

    /** Confirmada ou Replanejada → Em andamento. */
    public function start(PsychosocialMeasure $measure, User $actor): PsychosocialMeasure
    {
        $this->assertCompanyMayAct($measure, $actor);

        return $this->planTransition($measure, PsychosocialMeasure::STATUS_IN_PROGRESS, $actor, null);
    }

    /** Em andamento → Concluída. Exige ao menos uma evidência de execução: imprimir ou publicar não conclui nada. */
    public function complete(PsychosocialMeasure $measure, User $actor): PsychosocialMeasure
    {
        $this->assertCompanyMayAct($measure, $actor);

        if (! $measure->evidences()->exists()) {
            throw new PsychosocialMeasureException('Para concluir, registre ao menos uma evidência de execução.');
        }

        return $this->planTransition($measure, PsychosocialMeasure::STATUS_IMPLEMENTED, $actor, null);
    }

    /** Confirmada ou Em andamento → Replanejada, com novo prazo e justificativa obrigatória. */
    public function replan(PsychosocialMeasure $measure, User $actor, string $newDueDate, string $reason): PsychosocialMeasure
    {
        $this->assertCompanyMayAct($measure, $actor);
        $this->assertPlanTransition($measure, PsychosocialMeasure::STATUS_REPLANNED);

        if (blank($reason)) {
            throw new PsychosocialMeasureException('Informe a justificativa do replanejamento.');
        }

        $due = $this->normalizeDueDate($newDueDate);

        return DB::transaction(function () use ($measure, $actor, $due, $reason): PsychosocialMeasure {
            $oldDue = $measure->confirmed_due_date?->toDateString();
            $previousStatus = $measure->status;

            $measure->fill([
                'confirmed_due_date' => $due,
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
                'status' => PsychosocialMeasure::STATUS_REPLANNED,
            ])->save();

            $this->recordEvent($measure, $actor, 'confirmed_due_date', $oldDue, $due, $reason);
            $this->recordEvent($measure, $actor, 'status', $previousStatus, $measure->status, $reason);

            return $measure;
        });
    }

    /**
     * Empresa: registra evidência de execução (arquivo ou texto). Só em medida já confirmada.
     */
    public function addEvidence(PsychosocialMeasure $measure, User $actor, string $description, ?UploadedFile $file = null): PsychosocialMeasureEvidence
    {
        $this->assertCompanyMayAct($measure, $actor);

        if ($measure->status === PsychosocialMeasure::STATUS_TO_APPROVE) {
            throw new PsychosocialMeasureException('Confirme responsável e prazo antes de registrar evidências.');
        }

        $attributes = [
            'measure_id' => $measure->id,
            'user_id' => $actor->id,
            'kind' => PsychosocialMeasureEvidence::KIND_TEXT,
            'description' => trim($description),
        ];

        if ($file !== null) {
            $bytes = (string) file_get_contents($file->getRealPath());
            $hash = hash('sha256', $bytes);
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $path = "psychosocial/measures/{$measure->id}/{$hash}.{$extension}";
            Storage::disk('local')->put($path, $bytes);

            $attributes = array_merge($attributes, [
                'kind' => PsychosocialMeasureEvidence::KIND_FILE,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_sha256' => $hash,
            ]);
        }

        return DB::transaction(function () use ($measure, $actor, $attributes): PsychosocialMeasureEvidence {
            $evidence = PsychosocialMeasureEvidence::query()->create($attributes);
            $this->recordEvent($measure, $actor, 'evidence', null, $attributes['description'], null);

            return $evidence;
        });
    }

    /**
     * Empresa: verificação de eficácia (nota, resultado e data), só depois de Concluída.
     */
    public function recordEffectiveness(PsychosocialMeasure $measure, User $actor, string $note, string $result, ?string $checkedAt = null): PsychosocialMeasure
    {
        $this->assertCompanyMayAct($measure, $actor);

        if ($measure->status !== PsychosocialMeasure::STATUS_IMPLEMENTED) {
            throw new PsychosocialMeasureException('A eficácia só é verificada depois que a medida é concluída.');
        }
        if (blank($note)) {
            throw new PsychosocialMeasureException('Descreva a verificação de eficácia.');
        }
        if (! array_key_exists($result, PsychosocialMeasure::EFFECTIVENESS_RESULTS)) {
            throw new PsychosocialMeasureException('Resultado de eficácia inválido.');
        }

        return $this->update($measure, [
            'effectiveness_note' => trim($note),
            'effectiveness_result' => $result,
            'effectiveness_checked_at' => $checkedAt !== null && $checkedAt !== '' ? Carbon::parse($checkedAt) : now(),
        ], $actor, null);
    }

    /* ----- regras de quem pode agir e quando ----- */

    private function assertTalentsActor(User $actor): void
    {
        if (! $actor->isSuperAdmin() && ! $actor->isOwner()) {
            throw new PsychosocialMeasureException('Só a Talents edita a sugestão do plano.');
        }
    }

    private function assertTalentsMayEdit(Survey $survey): void
    {
        if ($survey->requires_publication && $survey->published_at !== null) {
            throw new PsychosocialMeasureException('A campanha já foi publicada: a Talents não edita mais a sugestão. Peça nova revisão.');
        }
    }

    /**
     * A empresa age só depois de "Publicado", como usuário da própria empresa (nunca a Talents).
     */
    private function assertCompanyMayAct(PsychosocialMeasure $measure, User $actor): void
    {
        if ($actor->isSuperAdmin() || $actor->isOwner()) {
            throw new PsychosocialMeasureException('Confirmar e executar o plano é papel da empresa.');
        }

        if ((int) $actor->contextCompanyId() !== (int) $measure->company_id) {
            throw new PsychosocialMeasureException('Esta medida não pertence à sua empresa.');
        }

        if (! SurveyPublicationGate::allows($measure->survey)) {
            throw new PsychosocialMeasureException('A empresa só age no plano depois que a campanha for publicada.');
        }
    }

    private function assertPlanTransition(PsychosocialMeasure $measure, string $to): void
    {
        $allowed = PsychosocialMeasure::PLAN_TRANSITIONS[$measure->status] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new PsychosocialMeasureException(sprintf(
                'Não é possível passar de «%s» para «%s».',
                PsychosocialMeasure::PLAN_LABELS[$measure->status] ?? $measure->status,
                PsychosocialMeasure::PLAN_LABELS[$to] ?? $to,
            ));
        }
    }

    private function planTransition(PsychosocialMeasure $measure, string $to, User $actor, ?string $reason): PsychosocialMeasure
    {
        $this->assertPlanTransition($measure, $to);

        return $this->changeStatus($measure, $to, $actor, $reason);
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function normalizeResponsible(PsychosocialMeasure $measure, ?int $userId, ?string $text): array
    {
        $text = filled($text) ? trim((string) $text) : null;

        if ($userId === null && $text === null) {
            throw new PsychosocialMeasureException('Informe o responsável (usuário da empresa ou nome, função e contato).');
        }

        if ($userId !== null) {
            $belongs = UserWorkspace::query()
                ->where('user_id', $userId)
                ->where('company_id', $measure->company_id)
                ->where('is_active', true)
                ->exists();

            if (! $belongs) {
                throw new PsychosocialMeasureException('O responsável precisa ser um usuário ativo desta empresa.');
            }
        }

        return [$userId, $text];
    }

    private function normalizeDueDate(string $date): string
    {
        $due = Carbon::parse($date)->startOfDay();

        if ($due->lt(today())) {
            throw new PsychosocialMeasureException('O prazo não pode estar no passado.');
        }

        return $due->toDateString();
    }

    private function describeResponsible(?int $userId, ?string $text): ?string
    {
        $parts = array_filter([
            $userId !== null ? (User::query()->whereKey($userId)->value('name') ?? "usuário #{$userId}") : null,
            $text,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
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
