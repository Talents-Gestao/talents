<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Survey;
use App\Models\SurveyMethodVersion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gate mínimo de publicação (T02). Uma campanha que exige publicação só mostra resultados, impressão,
 * exportações e documentos à empresa depois de "Publicado". Campanhas anteriores ao gate não exigem (D9).
 *
 * Único ponto de decisão: as rotas da empresa passam pelo middleware `survey.published` e as telas que
 * agregam várias campanhas (painel inicial) usam allows()/scopeVisible(). A Etapa 7 alimenta o estado.
 *
 * «Publicado» para a empresa:
 * - `surveys.published_at` (V11 pela rodada; legado pelo botão Publicar ou ao disponibilizar o parecer);
 * - no modelo anterior, também o parecer com `action_plans.admin_published_at` (mesmo sem `published_at` antigo).
 * Parecer salvo na V11 NÃO libera a campanha — só a publicação da rodada.
 */
final class SurveyPublicationGate
{
    /** «Publicado» para a empresa: publicação da campanha ou, no modelo anterior, parecer disponibilizado. */
    public static function isPublished(Survey $survey): bool
    {
        if ($survey->published_at !== null) {
            return true;
        }

        if ($survey->usesReviewFlow()) {
            return false;
        }

        return $survey->actionPlans()->whereNotNull('admin_published_at')->exists();
    }

    public static function allows(Survey $survey): bool
    {
        return ! $survey->requires_publication || self::isPublished($survey);
    }

    /**
     * @param  Builder<Survey>  $query
     * @return Builder<Survey>
     */
    public static function scopeVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('surveys.requires_publication', false)
                ->orWhereNotNull('surveys.published_at')
                ->orWhere(function (Builder $legacy): void {
                    // Legado: parecer publicado libera a campanha no seletor da empresa.
                    $legacy
                        ->whereExists(function ($sub): void {
                            $sub->selectRaw('1')
                                ->from('action_plans')
                                ->whereColumn('action_plans.survey_id', 'surveys.id')
                                ->whereNotNull('action_plans.admin_published_at');
                        })
                        ->whereNotExists(function ($sub): void {
                            $sub->selectRaw('1')
                                ->from('survey_method_versions')
                                ->whereColumn('survey_method_versions.id', 'surveys.method_version_id')
                                ->where('survey_method_versions.code', SurveyMethodVersion::CODE_TALENTS39_V1);
                        });
                });
        });
    }
}
