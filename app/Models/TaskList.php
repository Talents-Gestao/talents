<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskList extends Model
{
    protected $fillable = [
        'board_id',
        'name',
        'color',
        'position',
        'visibility',
        'allow_company_drop_in',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'float',
            'allow_company_drop_in' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'board_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(TaskCard::class, 'list_id')->orderBy('position')->orderBy('id');
    }

    /**
     * Listas que a empresa pode ver no quadro (somente company visibility).
     */
    public function scopeVisibleToCompany(Builder $query): Builder
    {
        return $query->where('visibility', 'company')->where('is_archived', false);
    }
}
