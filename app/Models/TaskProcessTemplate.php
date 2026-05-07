<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskProcessTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TaskTemplateList::class, 'process_template_id')->orderBy('position')->orderBy('id');
    }

    public function boards(): HasMany
    {
        return $this->hasMany(TaskBoard::class, 'process_template_id');
    }
}
