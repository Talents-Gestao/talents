<?php

declare(strict_types=1);

namespace App\Services\Nr1;

use App\Exceptions\Nr1CampaignException;
use App\Models\Nr1Training;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Treinamentos por público da campanha NR-1 (Caderno §6). A Talents marca o que foi contratado e preenche os dados;
 * a empresa só consulta e pede proposta do que não está contratado (ver Nr1OpportunityService).
 */
class Nr1TrainingService
{
    public const DISK = 'local';

    public function __construct(private readonly Nr1OpportunityService $opportunities) {}

    /**
     * Marca como contratado (ou atualiza os dados de um treinamento já contratado).
     *
     * @param  array<string, mixed>  $data  scope, price_cents, starts_on, ends_on, link_url, responsible, location, event_at,
     *                                      facilitator, capacity, attendance, released
     */
    public function contract(Survey $survey, User $actor, string $modality, string $audience, array $data, ?UploadedFile $material = null): Nr1Training
    {
        $this->assertTalents($actor);
        $this->assertSlot($survey, $modality, $audience);

        $scope = trim((string) ($data['scope'] ?? ''));
        if (mb_strlen($scope) < 3) {
            throw new Nr1CampaignException('Informe o escopo ou o preço contratado.');
        }

        $startsOn = $data['starts_on'] ?? null;
        $endsOn = $data['ends_on'] ?? null;
        if ($startsOn && $endsOn && $endsOn < $startsOn) {
            throw new Nr1CampaignException('O fim não pode ser anterior ao início.');
        }

        return DB::transaction(function () use ($survey, $actor, $modality, $audience, $data, $material, $scope, $startsOn, $endsOn): Nr1Training {
            $training = Nr1Training::query()
                ->where('survey_id', $survey->id)->where('modality', $modality)->where('audience', $audience)
                ->lockForUpdate()->first()
                ?? new Nr1Training(['survey_id' => $survey->id, 'modality' => $modality, 'audience' => $audience]);

            $hasMaterial = filled($data['link_url'] ?? null) || $material !== null || $training->material_path !== null;

            if ($modality === Nr1Training::MODALITY_ONLINE && ! $hasMaterial) {
                throw new Nr1CampaignException('O treinamento online precisa de link ou arquivo.');
            }

            if ($modality === Nr1Training::MODALITY_IN_PERSON) {
                if (blank($data['location'] ?? null) || blank($data['event_at'] ?? null)) {
                    throw new Nr1CampaignException('O treinamento presencial precisa de local, data e hora.');
                }
                if ((int) ($data['capacity'] ?? 0) < 1) {
                    throw new Nr1CampaignException('Informe a capacidade do treinamento presencial.');
                }
            }

            $training->fill([
                'scope' => $scope,
                'price_cents' => isset($data['price_cents']) && $data['price_cents'] !== '' ? max(0, (int) $data['price_cents']) : null,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'link_url' => filled($data['link_url'] ?? null) ? trim((string) $data['link_url']) : null,
                'responsible' => filled($data['responsible'] ?? null) ? trim((string) $data['responsible']) : null,
                'location' => $modality === Nr1Training::MODALITY_IN_PERSON ? trim((string) ($data['location'] ?? '')) : null,
                'event_at' => $modality === Nr1Training::MODALITY_IN_PERSON ? $data['event_at'] : null,
                'facilitator' => $modality === Nr1Training::MODALITY_IN_PERSON && filled($data['facilitator'] ?? null) ? trim((string) $data['facilitator']) : null,
                'capacity' => $modality === Nr1Training::MODALITY_IN_PERSON ? (int) $data['capacity'] : null,
                'attendance' => $modality === Nr1Training::MODALITY_IN_PERSON && isset($data['attendance']) && $data['attendance'] !== '' ? max(0, (int) $data['attendance']) : null,
            ]);

            if ($modality === Nr1Training::MODALITY_IN_PERSON && $training->attendance !== null && $training->attendance > (int) $training->capacity) {
                throw new Nr1CampaignException('A presença não pode passar da capacidade.');
            }

            if ($material !== null) {
                $bytes = (string) file_get_contents($material->getRealPath());
                $path = "nr1/trainings/{$survey->id}/".hash('sha256', $bytes).'.'.strtolower($material->getClientOriginalExtension() ?: 'bin');
                Storage::disk(self::DISK)->put($path, $bytes);
                $training->material_path = $path;
                $training->material_name = $material->getClientOriginalName();
            }

            if ($training->contracted_at === null) {
                $training->contracted_at = now();
                $training->contracted_by = $actor->id;
            }

            $wantsRelease = (bool) ($data['released'] ?? false);
            $training->released_at = $wantsRelease ? ($training->released_at ?? now()) : null;

            $training->save();

            $this->opportunities->markTrainingWon($survey, $modality, $audience, $actor);

            return $training;
        });
    }

    /** Remove a contratação (os dados ficam guardados; some para o colaborador). */
    public function uncontract(Survey $survey, User $actor, string $modality, string $audience): void
    {
        $this->assertTalents($actor);
        $this->assertSlot($survey, $modality, $audience);

        Nr1Training::query()
            ->where('survey_id', $survey->id)->where('modality', $modality)->where('audience', $audience)
            ->update(['contracted_at' => null, 'contracted_by' => null, 'released_at' => null]);
    }

    /**
     * Cartões da campanha: um por modalidade, com uma linha por público.
     *
     * @return list<array<string, mixed>>
     */
    public function cards(Survey $survey, bool $forTalents): array
    {
        $trainings = Nr1Training::query()->where('survey_id', $survey->id)->get()->keyBy(fn (Nr1Training $t) => $t->modality.':'.$t->audience);

        $cards = [];
        foreach (Nr1Training::MODALITIES as $modality) {
            $rows = [];
            foreach (SurveyAudienceLink::AUDIENCES as $audience) {
                /** @var Nr1Training|null $training */
                $training = $trainings->get($modality.':'.$audience);
                $contracted = $training?->isContracted() ?? false;
                $request = $contracted ? null : $this->opportunities->openTrainingRequest($survey, $modality, $audience);

                $rows[] = [
                    'audience' => $audience,
                    'audience_label' => SurveyAudienceLink::LABELS[$audience],
                    'state' => $contracted ? 'contracted' : ($request ? 'requested' : 'not_contracted'),
                    'requested_at' => $request?->created_at?->format('d/m/Y H:i'),
                    'training' => $contracted || $forTalents ? $this->present($training, $forTalents) : null,
                ];
            }

            $cards[] = ['modality' => $modality, 'label' => Nr1Training::MODALITY_LABELS[$modality], 'rows' => $rows];
        }

        return $cards;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function present(?Nr1Training $training, bool $forTalents): ?array
    {
        if ($training === null) {
            return null;
        }

        $data = [
            'scope' => $training->scope,
            'starts_on' => $training->starts_on?->format('d/m/Y'),
            'ends_on' => $training->ends_on?->format('d/m/Y'),
            'link_url' => $training->link_url,
            'material_name' => $training->material_name,
            'responsible' => $training->responsible,
            'location' => $training->location,
            'event_at' => $training->event_at?->format('d/m/Y H:i'),
            'facilitator' => $training->facilitator,
            'capacity' => $training->capacity,
            'attendance' => $training->attendance,
            'released' => $training->released_at !== null,
            'availability' => $training->released_at !== null ? 'Liberado ao público' : 'Ainda não liberado ao público',
        ];

        if ($forTalents) {
            $data += [
                'price_cents' => $training->price_cents,
                'starts_on_raw' => $training->starts_on?->format('Y-m-d'),
                'ends_on_raw' => $training->ends_on?->format('Y-m-d'),
                'event_at_raw' => $training->event_at?->format('Y-m-d\TH:i'),
                'contracted' => $training->isContracted(),
            ];
        }

        return $data;
    }

    private function assertTalents(User $actor): void
    {
        if (! $actor->isSuperAdmin() && ! $actor->isOwner()) {
            throw new Nr1CampaignException('Só a Talents marca treinamentos como contratados.');
        }
    }

    private function assertSlot(Survey $survey, string $modality, string $audience): void
    {
        if (! $survey->usesReviewFlow()) {
            throw new Nr1CampaignException('A contratação de treinamentos vale para campanhas NR-1 V11.');
        }

        if (! in_array($modality, Nr1Training::MODALITIES, true) || ! in_array($audience, SurveyAudienceLink::AUDIENCES, true)) {
            throw new Nr1CampaignException('Modalidade ou público inválido.');
        }
    }
}
