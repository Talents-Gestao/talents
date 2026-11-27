<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HiringAssessmentDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avaliação e dossiê de uma candidatura (uma por hiring_application). Decisão humana; a IA não decide.
 * Uso interno da Talents: nada vai para o portal da empresa.
 *
 * criteria: list<array{criterio: string, evidencia: string|null, origem: string|null, avaliacao: string}>
 */
class HiringAssessment extends Model
{
    protected $fillable = [
        'hiring_application_id',
        'success_profile',
        'case_description',
        'case_result',
        'criteria',
        'interviewer_summary',
        'limitations',
        'decision',
        'approved_at',
        'approved_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'decision' => HiringAssessmentDecision::class,
            'approved_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(HiringApplication::class, 'hiring_application_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }
}
