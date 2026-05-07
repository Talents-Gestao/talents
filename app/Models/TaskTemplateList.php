<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskTemplateList extends Model
{
    protected $fillable = [
        'process_template_id',
        'name',
        'position',
        'default_visibility',
        'allow_company_drop_in',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'float',
            'allow_company_drop_in' => 'boolean',
        ];
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskProcessTemplate::class, 'process_template_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(TaskTemplateCard::class, 'template_list_id')->orderBy('position')->orderBy('id');
    }
}
