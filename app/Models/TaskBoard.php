<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskBoard extends Model
{
    protected $fillable = [
        'company_id',
        'process_template_id',
        'name',
        'description',
        'cover_color',
        'is_archived',
        'position',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TaskBoard $board): void {
            if ((int) ($board->position ?? 0) > 0) {
                return;
            }

            $board->position = self::nextPosition();
        });
    }

    public static function nextPosition(): int
    {
        $max = (int) self::query()->max('position');

        return $max > 0 ? $max + 1000 : 1000;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskProcessTemplate::class, 'process_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class, 'board_id')->orderBy('position')->orderBy('id');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(TaskLabel::class, 'board_id')->orderBy('position')->orderBy('id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_board_members', 'board_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TaskActivityLog::class, 'board_id')->orderByDesc('created_at');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeAccessibleByCompanyUser(Builder $query, int $userId, int $companyId): Builder
    {
        return $query->where(function (Builder $q) use ($userId, $companyId) {
            $q->whereHas('members', fn (Builder $m) => $m->where('users.id', $userId))
                ->orWhereHas('lists.cards', function (Builder $c) use ($userId, $companyId) {
                    $c->where('is_archived', false)
                        ->visibleToCompany($companyId)
                        ->whereHas('members', fn (Builder $m) => $m->where('users.id', $userId));
                });
        });
    }

    public function hasMember(int $userId): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains('id', $userId);
        }

        return $this->members()->where('users.id', $userId)->exists();
    }

    public function isInternalTalentsBoard(): bool
    {
        return $this->company_id === null;
    }
}
