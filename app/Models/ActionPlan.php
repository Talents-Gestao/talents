<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActionPlan extends Model
{
    protected $fillable = [
        'company_id',
        'survey_id',
        'status',
        'admin_published_at',
    ];

    protected function casts(): array
    {
        return [
            'admin_published_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ActionPlanItem::class)->orderBy('sort_order');
    }
}
