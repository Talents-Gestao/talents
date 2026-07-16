<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HiringProcessStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HiringProcess extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'current_stage',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'current_stage' => HiringProcessStage::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
