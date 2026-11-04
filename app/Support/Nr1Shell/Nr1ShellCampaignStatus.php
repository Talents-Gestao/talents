<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Models\PsychosocialReviewRound;
use App\Models\Survey;

/**
 * Chip de estado da campanha (cabeçalho do painel e escolha de empresa): Publicado (verde), Em revisão (âmbar), Em
 * edição (cinza); campanha sem publicação: Ativa (verde) ou Encerrada (cinza). Só leitura: recebe o estado da última
 * rodada já consultado (sem consulta aqui), para a lista de empresas não fazer uma consulta por campanha.
 */
final class Nr1ShellCampaignStatus
{
    private const IN_REVIEW = [
        PsychosocialReviewRound::STATUS_IN_REVIEW,
        PsychosocialReviewRound::STATUS_CHANGES_REQUESTED,
        PsychosocialReviewRound::STATUS_APPROVED,
    ];

    /**
     * @return array{label: string, tone: 'ok'|'warn'|'gray'}
     */
    public static function badge(Survey $survey, bool $usesReviewFlow, ?string $currentRoundStatus): array
    {
        if (! $survey->requires_publication) {
            return $survey->status === 'closed' ? ['label' => 'Encerrada', 'tone' => 'gray'] : ['label' => 'Ativa', 'tone' => 'ok'];
        }
        if ($survey->published_at !== null) {
            return ['label' => 'Publicado', 'tone' => 'ok'];
        }

        return $usesReviewFlow && in_array($currentRoundStatus, self::IN_REVIEW, true)
            ? ['label' => 'Em revisão', 'tone' => 'warn']
            : ['label' => 'Em edição', 'tone' => 'gray'];
    }
}
