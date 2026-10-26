<?php

declare(strict_types=1);

namespace App\Services\Nr1;

use App\Exceptions\Nr1CampaignException;
use App\Models\Nr1Opportunity;
use App\Models\Nr1OpportunityEvent;
use App\Models\Nr1Training;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Oportunidades comerciais do NR-1. Aqui: a origem «treinamento» (Caderno §6, T07). A oportunidade só nasce de um pedido
 * explícito da empresa (nunca por visualização) e nunca duplica um pedido aberto da mesma modalidade e público.
 */
class Nr1OpportunityService
{
    /**
     * «Solicitar proposta» de um treinamento não contratado.
     */
    public function requestTraining(Survey $survey, User $requester, string $modality, string $audience): Nr1Opportunity
    {
        if (! $survey->usesReviewFlow()) {
            throw new Nr1CampaignException('A contratação de treinamentos vale para campanhas NR-1 V11.');
        }

        if (! in_array($modality, Nr1Training::MODALITIES, true) || ! in_array($audience, SurveyAudienceLink::AUDIENCES, true)) {
            throw new Nr1CampaignException('Modalidade ou público inválido.');
        }

        if ((int) $requester->company_id !== (int) $survey->company_id || $requester->isSuperAdmin() || $requester->isOwner()) {
            throw new Nr1CampaignException('Só a empresa da campanha solicita proposta.');
        }

        $contracted = Nr1Training::query()
            ->where('survey_id', $survey->id)->where('modality', $modality)->where('audience', $audience)
            ->whereNotNull('contracted_at')->exists();
        if ($contracted) {
            throw new Nr1CampaignException('Este treinamento já está contratado.');
        }

        $key = Nr1Opportunity::openKey($survey->id, Nr1Opportunity::ORIGIN_TRAINING, $modality, $audience);

        try {
            return DB::transaction(function () use ($survey, $requester, $modality, $audience, $key): Nr1Opportunity {
                if (Nr1Opportunity::query()->where('open_key', $key)->lockForUpdate()->exists()) {
                    throw new Nr1CampaignException('Já existe um pedido de proposta em aberto para este treinamento e público.');
                }

                $opportunity = Nr1Opportunity::query()->create([
                    'company_id' => $survey->company_id,
                    'survey_id' => $survey->id,
                    'origin' => Nr1Opportunity::ORIGIN_TRAINING,
                    'modality' => $modality,
                    'audience' => $audience,
                    'requested_by' => $requester->id,
                    'status' => Nr1Opportunity::STATUS_NEW,
                    'last_activity_at' => now(),
                    'open_key' => $key,
                ]);

                $this->event($opportunity, $requester, 'created', null, $opportunity->status, 'Proposta solicitada pela empresa.');

                return $opportunity;
            });
        } catch (QueryException) {
            // Duas requisições simultâneas: o índice único de open_key segura a segunda.
            throw new Nr1CampaignException('Já existe um pedido de proposta em aberto para este treinamento e público.');
        }
    }

    /**
     * Fecha como «Ganho» o pedido aberto de treinamento quando a Talents marca o treinamento como contratado.
     */
    public function markTrainingWon(Survey $survey, string $modality, string $audience, User $actor): void
    {
        $open = Nr1Opportunity::query()
            ->where('open_key', Nr1Opportunity::openKey($survey->id, Nr1Opportunity::ORIGIN_TRAINING, $modality, $audience))
            ->first();

        if ($open === null) {
            return;
        }

        $from = $open->status;
        $open->forceFill(['status' => Nr1Opportunity::STATUS_WON, 'open_key' => null, 'last_activity_at' => now()])->save();
        $this->event($open, $actor, 'status_changed', $from, Nr1Opportunity::STATUS_WON, 'Treinamento marcado como contratado.');
    }

    /** Pedido aberto (se houver) para o cartão da empresa mostrar «Proposta solicitada». */
    public function openTrainingRequest(Survey $survey, string $modality, string $audience): ?Nr1Opportunity
    {
        return Nr1Opportunity::query()
            ->where('open_key', Nr1Opportunity::openKey($survey->id, Nr1Opportunity::ORIGIN_TRAINING, $modality, $audience))
            ->first();
    }

    private function event(Nr1Opportunity $opportunity, ?User $actor, string $action, ?string $from, ?string $to, ?string $body): void
    {
        Nr1OpportunityEvent::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'body' => $body,
        ]);
    }
}
