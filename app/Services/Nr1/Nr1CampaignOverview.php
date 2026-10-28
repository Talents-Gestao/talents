<?php

declare(strict_types=1);

namespace App\Services\Nr1;

use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use App\Support\Nr1Orientation;

/**
 * Dados da tela «Campanha NR-1» (Caderno §6), iguais para a empresa e para a Talents: orientação, treinamentos por
 * público e participação. Só contagens e texto de contratação; nenhuma resposta individual.
 */
class Nr1CampaignOverview
{
    public function __construct(private readonly Nr1TrainingService $trainings) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Survey $survey, bool $forTalents): array
    {
        $reason = $survey->publicParticipationClosureReason();

        return [
            'orientation' => Nr1Orientation::content(),
            'trainings' => $this->trainings->cards($survey, $forTalents),
            'participation' => $this->participation($survey),
            'collection' => [
                'open' => $reason === null,
                'label' => match ($reason) {
                    null => 'Campanha aberta',
                    'not_started' => 'Campanha ainda não iniciada',
                    'ended' => 'Campanha encerrada',
                    default => 'Campanha inativa',
                },
            ],
        ];
    }

    /**
     * @return array{completed: int, eligible: ?int, eligible_source: ?string, percent_label: ?string}
     */
    private function participation(Survey $survey): array
    {
        $completed = $survey->completedResponses()->count();
        $registration = SurveyCampaignRegistration::query()->where('survey_id', $survey->id)->first();
        $eligible = $registration?->eligible_count !== null ? (int) $registration->eligible_count : null;

        return [
            'completed' => $completed,
            'eligible' => $eligible,
            'eligible_source' => $registration?->eligible_source,
            'percent_label' => $eligible !== null && $eligible > 0
                ? number_format($completed / $eligible * 100, 1, ',', '').'%'
                : null,
        ];
    }
}
