<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewQuestionnaire extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_default',
        'version',
        'parent_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(InterviewQuestionnaireSection::class, 'questionnaire_id')->orderBy('position');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'questionnaire_id');
    }

    /**
     * Roteiro com entrevistas (inclusive excluídas, que podem ser restauradas) não tem seções
     * nem perguntas alteradas no lugar: vira nova versão.
     */
    public function isLocked(): bool
    {
        return Interview::withTrashed()->where('questionnaire_id', $this->id)->exists();
    }

    /** Raiz da família de versões. */
    public function rootId(): int
    {
        return (int) ($this->parent_id ?? $this->id);
    }

    public static function defaultQuestionnaire(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('id')->first();
    }
}
