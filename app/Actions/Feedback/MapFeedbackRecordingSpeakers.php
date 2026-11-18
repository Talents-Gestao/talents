<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\User;
use App\Support\Feedback\TranscriptSpeakers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirma quem é quem na transcrição (rótulo do Plaud → líder, colaborador ou outra pessoa) antes de processar.
 * Exige ao menos um líder e um colaborador. Só antes de existir transcrição limpa: depois, os papéis já foram usados.
 */
class MapFeedbackRecordingSpeakers
{
    /**
     * @param  array<string, string>  $map  rótulo => papel
     */
    public function execute(FeedbackRecording $recording, User $actor, array $map): FeedbackRecording
    {
        if ($recording->processing_status === FeedbackRecording::STATUS_PROCESSING || $recording->hasCleanText()) {
            throw ValidationException::withMessages(['speakers' => 'Os falantes não podem mais ser alterados: a transcrição já foi processada.']);
        }

        $detected = [];
        foreach (TranscriptSpeakers::labels($recording->safeOriginalText()) as $item) {
            $detected[TranscriptSpeakers::key($item['label'])] = $item['label'];
        }

        $normalized = [];
        foreach ($map as $label => $role) {
            $key = TranscriptSpeakers::key((string) $label);
            if (! isset($detected[$key]) || ! in_array($role, FeedbackRecording::ROLES, true)) {
                throw ValidationException::withMessages(['speakers' => 'Escolha um papel válido para cada pessoa da transcrição.']);
            }
            $normalized[$detected[$key]] = $role;
        }

        if (count($normalized) !== count($detected)) {
            throw ValidationException::withMessages(['speakers' => 'Escolha o papel de todas as pessoas da transcrição.']);
        }

        $roles = array_values($normalized);
        if (! in_array(FeedbackRecording::ROLE_LEADER, $roles, true) || ! in_array(FeedbackRecording::ROLE_EMPLOYEE, $roles, true)) {
            throw ValidationException::withMessages(['speakers' => 'Indique quem é o líder e quem é o colaborador.']);
        }

        return DB::transaction(function () use ($recording, $actor, $normalized) {
            $recording->forceFill([
                'speaker_map' => $normalized,
                'speakers_mapped_by_user_id' => $actor->id,
                'speakers_mapped_at' => now(),
            ])->save();

            $recording->loadMissing('session');
            FeedbackAuditLog::query()->create([
                'company_id' => $recording->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_RECORDING_SPEAKERS_MAPPED,
                'subject_type' => FeedbackAuditLog::SUBJECT_RECORDING,
                'subject_id' => $recording->id,
                'subject_label' => $recording->session?->title,
                'details' => ['feedback_session_id' => $recording->feedback_session_id, 'roles' => array_count_values(array_values($normalized))],
            ]);

            return $recording;
        });
    }
}
