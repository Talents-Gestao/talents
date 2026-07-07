<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'description',
        'is_default',
        'is_active',
        'version',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FeedbackTemplateSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FeedbackSession::class);
    }

    public function isGlobal(): bool
    {
        return $this->company_id === null;
    }
}
