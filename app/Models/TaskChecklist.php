<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskChecklist extends Model
{
    protected $fillable = [
        'task_card_id',
        'name',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'float',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(TaskCard::class, 'task_card_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class, 'task_checklist_id')->orderBy('position')->orderBy('id');
    }
}
