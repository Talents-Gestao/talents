<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Oportunidade comercial do NR-1 (Caderno §14). Origem treinamento (a empresa pediu proposta) ou solução (sugestão
 * automática ou conversa solicitada). Não guarda resposta de colaborador: só empresa, campanha, serviço e contexto.
 */
class Nr1Opportunity extends Model
{
    public const ORIGIN_TRAINING = 'training_request';

    public const ORIGIN_SOLUTION_SUGGESTION = 'solution_suggestion';

    public const ORIGIN_SOLUTION_REQUEST = 'solution_request';

    public const ORIGIN_LABELS = [
        self::ORIGIN_TRAINING => 'Treinamento',
        self::ORIGIN_SOLUTION_SUGGESTION => 'Solução sugerida',
        self::ORIGIN_SOLUTION_REQUEST => 'Solução solicitada',
    ];

    public const STATUS_NEW = 'new';

    public const STATUS_TO_QUALIFY = 'to_qualify';

    public const STATUS_IN_CONTACT = 'in_contact';

    public const STATUS_PROPOSAL_SENT = 'proposal_sent';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public const STATUS_LABELS = [
        self::STATUS_NEW => 'Novo',
        self::STATUS_TO_QUALIFY => 'A qualificar',
        self::STATUS_IN_CONTACT => 'Em contato',
        self::STATUS_PROPOSAL_SENT => 'Proposta enviada',
        self::STATUS_WON => 'Ganho',
        self::STATUS_LOST => 'Perdido',
    ];

    public const CLOSED_STATUSES = [self::STATUS_WON, self::STATUS_LOST];

    protected $fillable = [
        'company_id',
        'survey_id',
        'origin',
        'modality',
        'audience',
        'service_key',
        'need',
        'requested_by',
        'status',
        'assigned_to',
        'last_activity_at',
        'next_action',
        'next_action_at',
        'lost_reason',
        'open_key',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'next_action_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /**
     * Chave de unicidade das oportunidades ABERTAS (T07): campanha + origem + modalidade/serviço + público.
     */
    public static function openKey(int $surveyId, string $origin, ?string $modality, ?string $audience, ?string $serviceKey = null): string
    {
        return implode(':', [$surveyId, $origin, $modality ?? '-', $audience ?? '-', $serviceKey ?? '-']);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Nr1OpportunityEvent::class, 'opportunity_id')->orderBy('id');
    }
}
