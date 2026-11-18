<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma chamada de IA: finalidade, empresa, provedor, modelo, tokens, custo estimado (centavos), duração e sucesso.
 * Nunca guarda o texto enviado nem o recebido.
 */
class AiUsageLog extends Model
{
    public const PURPOSE_FEEDBACK_CLEAN = 'feedback_transcript_clean';

    public const PURPOSE_FEEDBACK_REPORT = 'feedback_report';

    public const FEEDBACK_PURPOSES = [self::PURPOSE_FEEDBACK_CLEAN, self::PURPOSE_FEEDBACK_REPORT];

    protected $fillable = [
        'company_id',
        'user_id',
        'purpose',
        'subject_type',
        'subject_id',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'estimated_cost_cents',
        'duration_ms',
        'success',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_cents' => 'integer',
            'duration_ms' => 'integer',
            'success' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
