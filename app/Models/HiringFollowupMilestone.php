<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentCycleStatus;
use App\Enums\FollowupMoment;
use App\Enums\FollowupStatus;
use App\Support\Hiring\FollowupMilestoneStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Marco D15/D30/D60/D90 do ciclo. Concluído com resposta ou pulado; nada é enviado automaticamente. */
class HiringFollowupMilestone extends Model
{
    protected $fillable = [
        'employment_cycle_id',
        'moment',
        'due_on',
        'skipped_at',
        'skip_reason',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'moment' => FollowupMoment::class,
            'due_on' => 'date',
            'skipped_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(EmploymentCycle::class, 'employment_cycle_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HiringFollowupInvitation::class, 'milestone_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null || $this->skipped_at !== null;
    }

    public function status(EmploymentCycleStatus $cycleStatus, ?\DateTimeInterface $today = null): FollowupStatus
    {
        return FollowupMilestoneStatus::forMilestone(
            $cycleStatus,
            $this->due_on,
            $this->isDone(),
            $today ?? now('America/Sao_Paulo'),
        );
    }
}
