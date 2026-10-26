<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Treinamento Online ou Presencial de um público (Time, Líderes) numa campanha NR-1 (Caderno §6).
 * Só a Talents marca como contratado. O colaborador só vê o que está contratado E liberado ao seu público.
 */
class Nr1Training extends Model
{
    public const MODALITY_ONLINE = 'online';

    public const MODALITY_IN_PERSON = 'presencial';

    public const MODALITIES = [self::MODALITY_ONLINE, self::MODALITY_IN_PERSON];

    public const MODALITY_LABELS = [
        self::MODALITY_ONLINE => 'Treinamento Online',
        self::MODALITY_IN_PERSON => 'Treinamento Presencial',
    ];

    protected $fillable = [
        'survey_id',
        'modality',
        'audience',
        'contracted_at',
        'contracted_by',
        'price_cents',
        'scope',
        'starts_on',
        'ends_on',
        'link_url',
        'material_path',
        'material_name',
        'responsible',
        'location',
        'event_at',
        'facilitator',
        'capacity',
        'attendance',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'contracted_at' => 'datetime',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'event_at' => 'datetime',
            'released_at' => 'datetime',
            'price_cents' => 'integer',
            'capacity' => 'integer',
            'attendance' => 'integer',
        ];
    }

    public function isContracted(): bool
    {
        return $this->contracted_at !== null;
    }

    /** Contratado e liberado ao público do colaborador (T05/T06). */
    public function isAvailableToEmployees(): bool
    {
        return $this->contracted_at !== null && $this->released_at !== null;
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function contractedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contracted_by');
    }
}
