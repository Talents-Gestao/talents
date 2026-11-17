<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trilha das exclusões em Feedbacks: quem excluiu, quando, o quê e quantos feedbacks e respostas foram afetados.
 * A exclusão é lógica (deleted_at); o suporte recupera pelo id registrado aqui.
 */
class FeedbackAuditLog extends Model
{
    public const ACTION_SESSION_DELETED = 'session_deleted';

    public const ACTION_TEMPLATE_DELETED = 'template_deleted';

    public const SUBJECT_SESSION = 'feedback_session';

    public const SUBJECT_TEMPLATE = 'feedback_template';

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'sessions_count',
        'answers_count',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'sessions_count' => 'integer',
            'answers_count' => 'integer',
            'details' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
