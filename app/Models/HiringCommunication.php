<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HiringCommunicationChannel;
use App\Enums\HiringCommunicationKind;
use App\Enums\HiringCommunicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comunicação da vaga a um contato da empresa (atualização de etapa ou dossiê).
 * O texto de etapa nunca leva nome nem dado de candidato. idempotency_key impede o mesmo conteúdo duas vezes.
 */
class HiringCommunication extends Model
{
    protected $fillable = [
        'hiring_vacancy_id',
        'hiring_application_id',
        'company_contact_id',
        'kind',
        'channel',
        'stage_name',
        'recipient',
        'subject',
        'body',
        'idempotency_key',
        'status',
        'sent_at',
        'failure_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => HiringCommunicationKind::class,
            'channel' => HiringCommunicationChannel::class,
            'status' => HiringCommunicationStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(HiringVacancy::class, 'hiring_vacancy_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(HiringApplication::class, 'hiring_application_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'company_contact_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
