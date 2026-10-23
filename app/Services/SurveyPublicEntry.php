<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAudienceLink;

/**
 * Resolve o token da URL pública: link geral da campanha ou link de um público (Time/Líderes).
 * Campanhas com links por público não aceitam mais o link geral, para que toda resposta tenha público.
 */
final class SurveyPublicEntry
{
    /**
     * @param  list<string>  $with
     * @return array{survey: Survey, link: SurveyAudienceLink|null}|null
     */
    public static function resolve(string $token, array $with = []): ?array
    {
        $survey = Survey::query()->where('public_token', $token)->with($with)->first();

        if ($survey !== null) {
            return $survey->audienceLinks()->exists() ? null : ['survey' => $survey, 'link' => null];
        }

        $link = SurveyAudienceLink::query()->where('token', $token)->first();
        $survey = $link === null ? null : Survey::query()->with($with)->find($link->survey_id);

        return $survey === null ? null : ['survey' => $survey, 'link' => $link];
    }

    /**
     * Regra única de abertura: campanha e, quando houver, o link do público.
     *
     * @return 'inactive'|'not_started'|'ended'|null null = aceita participação
     */
    public static function closureReason(Survey $survey, ?SurveyAudienceLink $link): ?string
    {
        return $survey->publicParticipationClosureReason() ?? $link?->closureReason();
    }
}
