<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versão de um modelo de feedback: dona das próprias seções e perguntas. Depois que algum feedback usa a
 * versão, suas linhas não mudam mais (editar o modelo cria a versão seguinte). Título e descrição ficam
 * guardados na versão: tela, PDF e assinatura mostram os da versão usada pelo feedback.
 */
class FeedbackTemplateVersion extends Model
{
    protected $fillable = [
        'feedback_template_id',
        'number',
        'title',
        'description',
        'structure',
        'structure_hash',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'structure' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplate::class, 'feedback_template_id')->withTrashed();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FeedbackTemplateSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FeedbackSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Usada por algum feedback, inclusive excluído (as respostas ainda apontam para as perguntas dela). */
    public function isUsed(): bool
    {
        return FeedbackSession::withTrashed()->where('feedback_template_version_id', $this->id)->exists();
    }
}
