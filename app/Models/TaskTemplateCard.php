<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplateCard extends Model
{
    protected $fillable = [
        'template_list_id',
        'title',
        'description',
        'position',
        'default_visibility',
        'default_due_offset_days',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'float',
            'default_due_offset_days' => 'integer',
        ];
    }

    public function templateList(): BelongsTo
    {
        return $this->belongsTo(TaskTemplateList::class, 'template_list_id');
    }
}
