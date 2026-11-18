<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\FeedbackSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grava a transcrição original de um feedback (texto já extraído, criptografado pelo modelo) com o hash do
 * arquivo e o consentimento, e registra o envio na trilha. Uma gravação por feedback: para trocar, exclua a atual.
 */
class StoreFeedbackRecording
{
    public const ALREADY_EXISTS_MESSAGE = 'Este feedback já tem uma gravação. Exclua a atual para enviar outra.';

    /**
     * @param  array{format: string, filename: string, sha256: string, size: int, text: string, has_speakers: bool, has_timestamps: bool}  $file
     */
    public function execute(FeedbackSession $session, User $actor, array $file): FeedbackRecording
    {
        return DB::transaction(function () use ($session, $actor, $file) {
            FeedbackSession::query()->whereKey($session->id)->lockForUpdate()->first();

            if ($session->recordings()->exists()) {
                throw ValidationException::withMessages(['file' => self::ALREADY_EXISTS_MESSAGE]);
            }

            $now = now();

            $recording = FeedbackRecording::query()->create([
                'company_id' => $session->company_id,
                'feedback_session_id' => $session->id,
                'source_format' => $file['format'],
                'original_filename' => $file['filename'],
                'file_sha256' => $file['sha256'],
                'file_size_bytes' => $file['size'],
                'original_text' => $file['text'],
                'original_chars' => mb_strlen($file['text']),
                'has_speaker_labels' => $file['has_speakers'],
                'has_timestamps' => $file['has_timestamps'],
                'uploaded_by_user_id' => $actor->id,
                'consent_by_user_id' => $actor->id,
                'consent_confirmed_at' => $now,
            ]);

            FeedbackAuditLog::query()->create([
                'company_id' => $session->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_RECORDING_UPLOADED,
                'subject_type' => FeedbackAuditLog::SUBJECT_RECORDING,
                'subject_id' => $recording->id,
                'subject_label' => $session->title,
                'details' => [
                    'feedback_session_id' => $session->id,
                    'format' => $file['format'],
                    'sha256' => $file['sha256'],
                    'size_bytes' => $file['size'],
                    'chars' => $recording->original_chars,
                    'has_speaker_labels' => $file['has_speakers'],
                    'has_timestamps' => $file['has_timestamps'],
                    'consent_confirmed_at' => $now->toIso8601String(),
                    'file_discarded' => true,
                ],
            ]);

            return $recording;
        });
    }
}
