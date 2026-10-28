<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Survey;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gate mínimo de publicação (T02). Uma campanha que exige publicação só mostra resultados, impressão,
 * exportações e documentos à empresa depois de "Publicado". Campanhas anteriores ao gate não exigem (D9).
 *
 * Único ponto de decisão: as rotas da empresa passam pelo middleware `survey.published` e as telas que
 * agregam várias campanhas (painel inicial) usam allows()/scopeVisible(). A Etapa 7 alimenta o estado.
 */
final class SurveyPublicationGate
{
    public static function allows(Survey $survey): bool
    {
        return ! $survey->requires_publication || $survey->published_at !== null;
    }

    /**
     * @param  Builder<Survey>  $query
     * @return Builder<Survey>
     */
    public static function scopeVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('surveys.requires_publication', false)->orWhereNotNull('surveys.published_at');
        });
    }
}
