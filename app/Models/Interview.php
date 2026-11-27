<?php

namespace App\Models;

use App\Enums\InterviewStatus;
use App\Enums\InterviewTranscriptSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Entrevista de contratação. Pode estar vinculada a um candidato e a uma candidatura (vaga);
 * entrevistas antigas ficam sem vínculo até alguém confirmar o vínculo explicitamente.
 * Excluir é recuperável (soft delete) e nunca exclui o candidato.
 */
class Interview extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'questionnaire_id',
        'talent_candidate_id',
        'hiring_application_id',
        'transcript_source',
        'reviewed_at',
        'reviewed_by',
        'candidate_name',
        'position_title',
        'company_id',
        'status',
        'audio_path',
        'audio_mime',
        'audio_size',
        'duration_seconds',
        'transcript_text',
        'failure_reason',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => InterviewStatus::class,
            'transcript_source' => InterviewTranscriptSource::class,
            'reviewed_at' => 'datetime',
            'audio_size' => 'integer',
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(InterviewQuestionnaire::class, 'questionnaire_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(InterviewAnswer::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(TalentCandidate::class, 'talent_candidate_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(HiringApplication::class, 'hiring_application_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    public function isLinked(): bool
    {
        return $this->talent_candidate_id !== null;
    }

    public function source(): InterviewTranscriptSource
    {
        return $this->transcript_source ?? InterviewTranscriptSource::AudioWhisper;
    }

    public function audioAbsolutePath(): ?string
    {
        if (! $this->audio_path) {
            return null;
        }

        return Storage::disk('local')->path($this->audio_path);
    }

    public function markProcessing(InterviewStatus $status): void
    {
        $this->update([
            'status' => $status,
            'failure_reason' => null,
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => InterviewStatus::Completed,
            'finished_at' => now(),
            'failure_reason' => null,
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => InterviewStatus::Failed,
            'failure_reason' => $reason,
            'finished_at' => now(),
        ]);
    }
}
