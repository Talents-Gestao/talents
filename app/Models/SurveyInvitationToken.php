<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Convite de uso único. Só o hash do código existe aqui; o uso é registrado só com o mês e nunca
 * aponta para a resposta, para preservar o anonimato.
 */
class SurveyInvitationToken extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['survey_id', 'audience', 'token_hash', 'used_month'];

    protected function casts(): array
    {
        return [
            'used_month' => 'date',
        ];
    }

    public static function hashFor(string $code): string
    {
        return hash('sha256', $code);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
