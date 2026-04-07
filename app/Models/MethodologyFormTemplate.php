<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MethodologyFormTemplate extends Model
{
    protected $fillable = [
        'title',
        'description',
        'step_number',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'step_number' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MethodologyFormSection::class)->orderBy('sort_order');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(MethodologySurvey::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_methodology_form_template')
            ->withTimestamps();
    }
}
