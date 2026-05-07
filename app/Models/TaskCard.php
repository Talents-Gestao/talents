<?php

namespace App\Models;

use App\Support\Tasks\TaskCardVisibility as TaskCardVisibilityHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class TaskCard extends Model
{
    protected $fillable = [
        'list_id',
        'title',
        'description',
        'position',
        'visibility',
        'cover_color',
        'cover_attachment_id',
        'start_date',
        'due_date',
        'completed_at',
        'is_archived',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'float',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'list_id');
    }

    public function board(): HasOneThrough
    {
        return $this->hasOneThrough(
            TaskBoard::class,
            TaskList::class,
            'id',
            'id',
            'list_id',
            'board_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function coverAttachment(): BelongsTo
    {
        return $this->belongsTo(TaskAttachment::class, 'cover_attachment_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_card_label', 'task_card_id', 'task_label_id')
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_card_members', 'task_card_id', 'user_id')
            ->withTimestamps();
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class, 'task_card_id')->orderBy('position')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class, 'task_card_id')->orderByDesc('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_card_id')->orderBy('created_at');
    }

    public function isVisibleToCompany(): bool
    {
        return TaskCardVisibilityHelper::isVisibleToCompany($this);
    }

    /**
     * Cards visíveis para utilizadores da empresa no cliente.
     */
    public function scopeVisibleToCompany(Builder $query): Builder
    {
        return $query->whereHas('list', function (Builder $q) {
            $q->where('visibility', 'company')->where('is_archived', false);
        })->where(function (Builder $q) {
            $q->where('visibility', 'company')
                ->orWhere('visibility', 'inherit');
        })->where('is_archived', false);
    }
}
