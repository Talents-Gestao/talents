<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sugestão de serviço do catálogo para uma campanha. Nasce pendente; a empresa só vê depois que a Talents libera.
 */
class SurveyServiceSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Aguardando aprovação',
        self::STATUS_APPROVED => 'Liberado para a empresa',
        self::STATUS_DISMISSED => 'Dispensado',
    ];

    protected $fillable = [
        'survey_id',
        'company_id',
        'commercial_product_id',
        'product_name',
        'rationale',
        'status',
        'sort_order',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CommercialProduct::class, 'commercial_product_id');
    }
}
