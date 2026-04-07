<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MethodologyFormSection extends Model
{
    protected $fillable = [
        'methodology_form_template_id',
        'title',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MethodologyFormTemplate::class, 'methodology_form_template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(MethodologyFormQuestion::class)->orderBy('sort_order');
    }
}
