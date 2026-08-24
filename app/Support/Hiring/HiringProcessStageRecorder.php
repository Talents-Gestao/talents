<?php

declare(strict_types=1);

namespace App\Support\Hiring;

use App\Enums\HiringProcessStage;
use App\Models\HiringProcess;
use App\Models\HiringProcessStageEntry;
use Illuminate\Support\Facades\DB;

/**
 * Persiste a ficha por etapa (notes / candidatos) e sincroniza o rascunho no processo.
 */
final class HiringProcessStageRecorder
{
    /**
     * @param  array{notes?: mixed, candidates_count?: mixed}|null  $payload
     */
    public function upsertCurrentStage(
        HiringProcess $process,
        ?array $payload,
        ?int $userId,
        bool $onlyIfFilled = false,
    ): ?HiringProcessStageEntry {
        $notes = array_key_exists('notes', $payload ?? [])
            ? $this->normalizeNotes($payload['notes'] ?? null)
            : $this->normalizeNotes($process->notes);

        $candidatesCount = array_key_exists('candidates_count', $payload ?? [])
            ? $this->normalizeCandidatesCount($payload['candidates_count'] ?? null)
            : $process->candidates_count;

        if ($onlyIfFilled && $notes === null && $candidatesCount === null) {
            return null;
        }

        $this->applyDraftToProcess($process, $notes, $candidatesCount);

        return $this->upsertEntry(
            $process,
            $process->current_stage,
            $notes,
            $candidatesCount,
            $userId,
        );
    }

    /**
     * Guarda a etapa atual (se houver dados), avança e carrega o rascunho da nova etapa.
     *
     * @param  array{notes?: mixed, candidates_count?: mixed}|null  $payload
     */
    public function advance(HiringProcess $process, ?array $payload, ?int $userId): HiringProcessStage
    {
        $next = $process->current_stage->next();
        if ($next === null) {
            throw new \RuntimeException('Este processo já está na última fase.');
        }

        return DB::transaction(function () use ($process, $payload, $userId, $next) {
            $this->upsertCurrentStage($process, $payload, $userId, onlyIfFilled: true);

            $process->current_stage = $next;
            $this->loadStageDraftOntoProcess($process, $next);
            $process->updated_by = $userId;
            $process->save();

            return $next;
        });
    }

    public function retreat(HiringProcess $process, ?int $userId): HiringProcessStage
    {
        $previous = $process->current_stage->previous();
        if ($previous === null) {
            throw new \RuntimeException('Este processo já está na primeira fase.');
        }

        return DB::transaction(function () use ($process, $userId, $previous) {
            // Não apaga histórico; só volta a etapa e restaura o rascunho dessa ficha.
            $process->current_stage = $previous;
            $this->loadStageDraftOntoProcess($process, $previous);
            $process->updated_by = $userId;
            $process->save();

            return $previous;
        });
    }

    /**
     * Ao saltar de etapa via update, grava a ficha atual e carrega a da destino.
     *
     * @param  array{notes?: mixed, candidates_count?: mixed}|null  $payload
     */
    public function changeStage(
        HiringProcess $process,
        HiringProcessStage $target,
        ?array $payload,
        ?int $userId,
    ): void {
        if ($process->current_stage === $target) {
            return;
        }

        DB::transaction(function () use ($process, $target, $payload, $userId) {
            $this->upsertCurrentStage($process, $payload, $userId, onlyIfFilled: true);

            $process->current_stage = $target;
            $this->loadStageDraftOntoProcess($process, $target);
            $process->updated_by = $userId;
            $process->save();
        });
    }

    public function upsertEntry(
        HiringProcess $process,
        HiringProcessStage $stage,
        ?string $notes,
        ?int $candidatesCount,
        ?int $userId,
    ): HiringProcessStageEntry {
        /** @var HiringProcessStageEntry $entry */
        $entry = HiringProcessStageEntry::query()->firstOrNew([
            'hiring_process_id' => $process->id,
            'stage' => $stage->value,
        ]);

        $entry->notes = $notes;
        $entry->candidates_count = $candidatesCount;
        if (! $entry->exists) {
            $entry->created_by = $userId;
        }
        $entry->save();

        return $entry;
    }

    public function applyDraftToProcess(HiringProcess $process, ?string $notes, ?int $candidatesCount): void
    {
        $process->notes = $notes;
        $process->notes_at = now();
        $process->candidates_count = $candidatesCount;
        $process->candidates_count_at = now();
    }

    public function clearProcessDraft(HiringProcess $process): void
    {
        $process->notes = null;
        $process->notes_at = null;
        $process->candidates_count = null;
        $process->candidates_count_at = null;
    }

    public function loadStageDraftOntoProcess(HiringProcess $process, HiringProcessStage $stage): void
    {
        $entry = HiringProcessStageEntry::query()
            ->where('hiring_process_id', $process->id)
            ->where('stage', $stage->value)
            ->first();

        if ($entry === null) {
            $this->clearProcessDraft($process);

            return;
        }

        $process->notes = $entry->notes;
        $process->notes_at = $entry->updated_at;
        $process->candidates_count = $entry->candidates_count;
        $process->candidates_count_at = $entry->updated_at;
    }

    public function normalizeNotes(mixed $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $trimmed = trim((string) $notes);

        return $trimmed === '' ? null : $trimmed;
    }

    public function normalizeCandidatesCount(mixed $count): ?int
    {
        if ($count === null || $count === '') {
            return null;
        }

        return (int) $count;
    }
}
