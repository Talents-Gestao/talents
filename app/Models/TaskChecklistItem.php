<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class TaskChecklistItem extends Model
{
    protected $fillable = [
        'task_checklist_id',
        'text',
        'description',
        'position',
        'is_completed',
        'due_date',
        'assignee_user_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'float',
            'is_completed' => 'boolean',
            'due_date' => 'date',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TaskChecklist::class, 'task_checklist_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function card(): HasOneThrough
    {
        return $this->hasOneThrough(
            TaskCard::class,
            TaskChecklist::class,
            'id',
            'id',
            'task_checklist_id',
            'task_card_id'
        );
    }
}
