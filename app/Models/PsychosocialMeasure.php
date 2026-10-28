<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialMeasureException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Medida do acompanhamento novo de riscos psicossociais. Não tem relação com action_plan_items.
 */
class PsychosocialMeasure extends Model
{
    public const STATUS_TO_APPROVE = 'to_approve';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_IMPLEMENTED = 'implemented';

    public const STATUSES = [
        self::STATUS_TO_APPROVE,
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_IMPLEMENTED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_TO_APPROVE => 'A aprovar',
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_IN_PROGRESS => 'Em andamento',
        self::STATUS_IMPLEMENTED => 'Implantada',
    ];

    public const ORIGIN_MANUAL = 'manual';

    public const ORIGIN_ENGINE_SUGGESTION = 'engine_suggestion';

    public const ORIGINS = [self::ORIGIN_MANUAL, self::ORIGIN_ENGINE_SUGGESTION];

    public const BASIS_QUESTIONNAIRE_INDICATION = 'questionnaire_indication';

    public const BASIS_VALIDATED_FACTOR = 'validated_factor';

    public const EVIDENCE_BASES = [self::BASIS_QUESTIONNAIRE_INDICATION, self::BASIS_VALIDATED_FACTOR];

    protected $fillable = [
        'survey_id',
        'company_id',
        'factor_label',
        'evidence_basis',
        'measure_description',
        'responsible_text',
        'suggested_due_date',
        'sst_priority',
        'evidence_text',
        'status',
        'implemented_at',
        'implemented_by',
        'effectiveness_note',
        'effectiveness_checked_at',
        'origin',
        'sort_order',
    ];

    protected $attributes = [
        'status' => self::STATUS_TO_APPROVE,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'suggested_due_date' => 'date',
            'implemented_at' => 'datetime',
            'effectiveness_checked_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Espelha as CHECK do Postgres para que a regra valha também em SQLite e fora do serviço.
        static::saving(function (PsychosocialMeasure $measure): void {
            if (! in_array($measure->status, self::STATUSES, true)) {
                throw new PsychosocialMeasureException("Status de medida inválido: «{$measure->status}».");
            }

            if (! in_array($measure->origin, self::ORIGINS, true)) {
                throw new PsychosocialMeasureException("Origem de medida inválida: «{$measure->origin}».");
            }

            if (! in_array($measure->evidence_basis, self::EVIDENCE_BASES, true)) {
                throw new PsychosocialMeasureException("Base de evidência inválida: «{$measure->evidence_basis}».");
            }

            if ($measure->status === self::STATUS_IMPLEMENTED
                && ($measure->implemented_at === null || $measure->implemented_by === null)) {
                throw new PsychosocialMeasureException('Medida implantada exige data e usuário responsável pelo registro.');
            }
        });
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function implementedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'implemented_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PsychosocialMeasureEvent::class, 'measure_id')->orderBy('id');
    }
}
