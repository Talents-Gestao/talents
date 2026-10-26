<?php

declare(strict_types=1);

namespace App\Services\Nr1;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
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
    public const MIN_LOST_REASON_LENGTH = 5;

    /**
     * Transições da fila (Caderno §14). Ganho só depois de «Proposta enviada»; Perdido (recusa) de qualquer estado aberto,
     * sempre com o motivo (T22). Ganho e Perdido encerram.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        Nr1Opportunity::STATUS_NEW => [Nr1Opportunity::STATUS_TO_QUALIFY, Nr1Opportunity::STATUS_IN_CONTACT, Nr1Opportunity::STATUS_LOST],
        Nr1Opportunity::STATUS_TO_QUALIFY => [Nr1Opportunity::STATUS_IN_CONTACT, Nr1Opportunity::STATUS_LOST],
        Nr1Opportunity::STATUS_IN_CONTACT => [Nr1Opportunity::STATUS_TO_QUALIFY, Nr1Opportunity::STATUS_PROPOSAL_SENT, Nr1Opportunity::STATUS_LOST],
        Nr1Opportunity::STATUS_PROPOSAL_SENT => [Nr1Opportunity::STATUS_IN_CONTACT, Nr1Opportunity::STATUS_WON, Nr1Opportunity::STATUS_LOST],
        Nr1Opportunity::STATUS_WON => [],
        Nr1Opportunity::STATUS_LOST => [],
    ];

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

    /** «Assumir oportunidade»: o usuário da Talents ou do Comercial passa a ser o responsável. Quem já tem dono não é tomado. */
    public function assume(Nr1Opportunity $opportunity, User $actor): Nr1Opportunity
    {
        $this->assertCommercialActor($actor);

        return DB::transaction(function () use ($opportunity, $actor): Nr1Opportunity {
            $row = Nr1Opportunity::query()->lockForUpdate()->findOrFail($opportunity->id);

            if (! $row->isOpen()) {
                throw new Nr1CampaignException('Esta oportunidade já foi encerrada.');
            }
            if ($row->assigned_to !== null && (int) $row->assigned_to !== (int) $actor->id) {
                throw new Nr1CampaignException('Esta oportunidade já tem responsável.');
            }

            $from = $row->status;
            $row->forceFill([
                'assigned_to' => $actor->id,
                'status' => $row->status === Nr1Opportunity::STATUS_NEW ? Nr1Opportunity::STATUS_TO_QUALIFY : $row->status,
                'last_activity_at' => now(),
            ])->save();
            $this->event($row, $actor, 'assumed', $from, $row->status, 'Oportunidade assumida por '.$actor->name.'.');

            return $row;
        });
    }

    /** Muda o estado da fila. Perdido (recusa) exige o motivo (T22); o histórico guarda autor, data e nota. */
    public function changeStatus(Nr1Opportunity $opportunity, User $actor, string $to, ?string $note = null): Nr1Opportunity
    {
        $this->assertCommercialActor($actor);

        $note = filled($note) ? trim((string) $note) : null;

        return DB::transaction(function () use ($opportunity, $actor, $to, $note): Nr1Opportunity {
            $row = Nr1Opportunity::query()->lockForUpdate()->findOrFail($opportunity->id);

            if (! in_array($to, self::TRANSITIONS[$row->status] ?? [], true)) {
                throw new Nr1CampaignException(sprintf(
                    'Não é possível passar de «%s» para «%s».',
                    Nr1Opportunity::STATUS_LABELS[$row->status] ?? $row->status,
                    Nr1Opportunity::STATUS_LABELS[$to] ?? $to,
                ));
            }

            if ($to === Nr1Opportunity::STATUS_LOST && ($note === null || mb_strlen($note) < self::MIN_LOST_REASON_LENGTH)) {
                throw new Nr1CampaignException('Registre o motivo da recusa (mínimo de '.self::MIN_LOST_REASON_LENGTH.' caracteres).');
            }

            $from = $row->status;
            $closed = in_array($to, Nr1Opportunity::CLOSED_STATUSES, true);
            $row->forceFill([
                'status' => $to,
                'open_key' => $closed ? null : $row->open_key,
                'lost_reason' => $to === Nr1Opportunity::STATUS_LOST ? $note : $row->lost_reason,
                'last_activity_at' => now(),
            ])->save();
            $this->event($row, $actor, $to === Nr1Opportunity::STATUS_LOST ? 'lost' : 'status_changed', $from, $to, $note);

            return $row;
        });
    }

    /** Próxima ação (texto e data opcional) da oportunidade aberta. */
    public function setNextAction(Nr1Opportunity $opportunity, User $actor, string $text, ?string $dueAt): Nr1Opportunity
    {
        $this->assertCommercialActor($actor);

        $text = trim($text);
        if ($text === '') {
            throw new Nr1CampaignException('Descreva a próxima ação.');
        }

        return DB::transaction(function () use ($opportunity, $actor, $text, $dueAt): Nr1Opportunity {
            $row = Nr1Opportunity::query()->lockForUpdate()->findOrFail($opportunity->id);

            if (! $row->isOpen()) {
                throw new Nr1CampaignException('Esta oportunidade já foi encerrada.');
            }

            $row->forceFill(['next_action' => $text, 'next_action_at' => filled($dueAt) ? $dueAt : null, 'last_activity_at' => now()])->save();
            $this->event($row, $actor, 'next_action', $row->status, $row->status, $text);

            return $row;
        });
    }

    /**
     * Oportunidade nascida de solução (sugestão automática ou conversa pedida), sem duplicar uma aberta do mesmo serviço.
     * Sugestão automática entra como «A qualificar», nunca como interesse declarado; conversa pedida entra como «Novo».
     */
    public function openSolution(Survey $survey, string $origin, string $serviceKey, ?string $need, ?User $requester, ?string $dimension = null): ?Nr1Opportunity
    {
        $key = Nr1Opportunity::openKey($survey->id, $origin, null, null, $serviceKey);

        if (Nr1Opportunity::query()->where('open_key', $key)->exists()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($survey, $origin, $serviceKey, $need, $requester, $key): Nr1Opportunity {
                $opportunity = Nr1Opportunity::query()->create([
                    'company_id' => $survey->company_id,
                    'survey_id' => $survey->id,
                    'origin' => $origin,
                    'service_key' => $serviceKey,
                    'need' => $need,
                    'requested_by' => $requester?->id,
                    'status' => $origin === Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION ? Nr1Opportunity::STATUS_TO_QUALIFY : Nr1Opportunity::STATUS_NEW,
                    'last_activity_at' => now(),
                    'open_key' => $key,
                ]);

                $this->event(
                    $opportunity,
                    $requester,
                    'created',
                    null,
                    $opportunity->status,
                    $origin === Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION ? 'Sugestão automática da campanha publicada (não é interesse declarado).' : 'Conversa solicitada pela empresa.',
                );

                return $opportunity;
            });
        } catch (QueryException) {
            return null;
        }
    }

    private function assertCommercialActor(User $actor): void
    {
        if (! $actor->canAccessAdmin(AdminPermissionModule::Nr1Oportunidades, PermissionAction::Edit)) {
            throw new Nr1CampaignException('Sem permissão para trabalhar a fila de oportunidades.');
        }
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
