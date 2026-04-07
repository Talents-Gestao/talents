<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MethodologyFormQuestion extends Model
{
    protected $fillable = [
        'methodology_form_section_id',
        'body',
        'type',
        'is_required',
        'scale_min',
        'scale_max',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'scale_min' => 'integer',
            'scale_max' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(MethodologyFormSection::class, 'methodology_form_section_id');
    }

    public function isScale(): bool
    {
        return $this->type === 'scale';
    }

    public function isText(): bool
    {
        return $this->type === 'text';
    }
}
