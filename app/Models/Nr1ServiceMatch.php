<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regra «dimensão em atenção → serviço Talents». Configurável pela Talents; só vai à empresa depois de revisada.
 */
class Nr1ServiceMatch extends Model
{
    public const CONFIDENCE_LABELS = [
        'alta' => 'Alta',
        'media' => 'Média',
        'baixa' => 'Baixa',
    ];

    protected $fillable = [
        'factor_label',
        'service_name',
        'eligible_services',
        'consultative_text',
        'confidence',
        'active',
        'sort_order',
        'commercial_owner_id',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    public function commercialOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_owner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
