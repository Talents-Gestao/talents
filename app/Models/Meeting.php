<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Meeting extends Model
{
    protected $fillable = [
        'title',
        'company_id',
        'participants_text',
        'status',
        'audio_path',
        'audio_mime',
        'audio_size',
        'duration_seconds',
        'transcript_text',
        'minutes_text',
        'failure_reason',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MeetingStatus::class,
            'audio_size' => 'integer',
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function audioAbsolutePath(): ?string
    {
        if (! $this->audio_path) {
            return null;
        }

        return Storage::disk('local')->path($this->audio_path);
    }

    public function markProcessing(MeetingStatus $status): void
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
            'status' => MeetingStatus::Completed,
            'finished_at' => now(),
            'failure_reason' => null,
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => MeetingStatus::Failed,
            'failure_reason' => $reason,
            'finished_at' => now(),
        ]);
    }
}
