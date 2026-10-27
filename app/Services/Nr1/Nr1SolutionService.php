<?php

declare(strict_types=1);

namespace App\Services\Nr1;

use App\Actions\Notices\PublishNr1Notice;
use App\Exceptions\Nr1CampaignException;
use App\Models\Nr1Opportunity;
use App\Models\Nr1OpportunityEvent;
use App\Models\Nr1ServiceMatch;
use App\Models\Survey;
use App\Models\User;
use App\Services\PsychosocialDashboardBuilder;
use App\Support\SurveyPublicationGate;
use Illuminate\Support\Facades\DB;

/**
 * Soluções Talents (Caderno §14): recomendações por dimensão em atenção (regras configuráveis em Nr1ServiceMatch), só
 * depois de «Publicado». «Solicitar conversa» gera um lead; a sugestão automática, sem clique da empresa, gera apenas
 * uma oportunidade «A qualificar» e NUNCA «interesse declarado».
 */
class Nr1SolutionService
{
    public const MIN_NOTE_LENGTH = 0;

    public function __construct(
        private readonly PsychosocialDashboardBuilder $dashboard,
        private readonly Nr1OpportunityService $opportunities,
    ) {}

    /**
     * Recomendações para a campanha. A empresa só recebe regras ATIVAS e REVISADAS pela Talents (configurável em
     * `nr1.solutions.require_review`); a consultoria vê todas as ativas.
     *
     * @return list<array<string, mixed>>
     */
    public function recommendations(Survey $survey, bool $forCompany): array
    {
        if (! SurveyPublicationGate::allows($survey) || ($survey->requires_publication && $survey->published_at === null)) {
            return [];
        }

        $attention = collect($this->dashboard->build($survey)['attention'])->keyBy('title');
        if ($attention->isEmpty()) {
            return [];
        }

        $matches = Nr1ServiceMatch::query()
            ->whereIn('factor_label', $attention->keys()->all())
            ->where('active', true)
            ->when($forCompany && config('nr1.solutions.require_review', true), fn ($q) => $q->whereNotNull('reviewed_at'))
            ->orderBy('sort_order')
            ->get();

        $requested = Nr1Opportunity::query()
            ->where('survey_id', $survey->id)
            ->where('origin', Nr1Opportunity::ORIGIN_SOLUTION_REQUEST)
            ->whereNotIn('status', Nr1Opportunity::CLOSED_STATUSES)
            ->pluck('service_key')
            ->all();

        return $matches->map(function (Nr1ServiceMatch $match) use ($attention, $requested): array {
            $dimension = $attention->get($match->factor_label);

            return [
                'id' => $match->id,
                'factor' => $match->factor_label,
                'score_label' => $dimension['score_label'],
                'risk_level' => $dimension['risk_level'],
                'band_label' => $dimension['band_label'],
                'service_name' => $match->service_name,
                'eligible_services' => $match->eligible_services,
                'text' => $match->consultative_text,
                'confidence' => $match->confidence,
                'confidence_label' => Nr1ServiceMatch::CONFIDENCE_LABELS[$match->confidence] ?? $match->confidence,
                'requested' => in_array($match->service_name, $requested, true),
            ];
        })->values()->all();
    }

    /**
     * Estado dos pedidos da empresa visto por ela: Solicitado, Em contato, Proposta enviada, Encerrado. Só pedidos feitos
     * pela empresa (proposta de treinamento e conversa sobre solução); a sugestão automática nunca aparece, porque não é
     * interesse declarado. Nada interno do Comercial sai daqui: sem anotações, responsável, próxima ação, motivo de recusa
     * nem histórico, e «Ganho» e «Perdido» viram o mesmo «Encerrado».
     *
     * @return list<array{id: int, kind: string, subject: string, status: string, status_label: string, requested_at: string, updated_at: string}>
     */
    public function companyRequests(Survey $survey): array
    {
        return Nr1Opportunity::query()
            ->where('company_id', $survey->company_id)
            ->where('survey_id', $survey->id)
            ->whereIn('origin', [Nr1Opportunity::ORIGIN_TRAINING, Nr1Opportunity::ORIGIN_SOLUTION_REQUEST])
            ->orderByDesc('id')
            ->get(['id', 'origin', 'modality', 'audience', 'service_key', 'status', 'created_at', 'last_activity_at'])
            ->map(function (Nr1Opportunity $opportunity): array {
                $status = match ($opportunity->status) {
                    Nr1Opportunity::STATUS_IN_CONTACT => ['in_contact', 'Em contato'],
                    Nr1Opportunity::STATUS_PROPOSAL_SENT => ['proposal_sent', 'Proposta enviada'],
                    Nr1Opportunity::STATUS_WON, Nr1Opportunity::STATUS_LOST => ['closed', 'Encerrado'],
                    default => ['requested', 'Solicitado'],
                };

                $training = $opportunity->origin === Nr1Opportunity::ORIGIN_TRAINING;

                return [
                    'id' => $opportunity->id,
                    'kind' => $training ? 'Proposta de treinamento' : 'Conversa sobre solução',
                    'subject' => $training
                        ? ($opportunity->modality === 'presencial' ? 'Treinamento presencial' : 'Treinamento online').' · '.($opportunity->audience === 'lideres' ? 'Líderes' : 'Time')
                        : (string) $opportunity->service_key,
                    'status' => $status[0],
                    'status_label' => $status[1],
                    'requested_at' => $opportunity->created_at?->format('d/m/Y') ?? '',
                    'updated_at' => ($opportunity->last_activity_at ?? $opportunity->created_at)?->format('d/m/Y') ?? '',
                ];
            })
            ->all();
    }

    /**
     * Sugestão automática ao publicar: uma oportunidade «A qualificar» por regra ativa que casa com uma dimensão em
     * atenção. Não depende de clique da empresa e não registra interesse declarado.
     */
    public function suggest(Survey $survey): int
    {
        $created = 0;

        foreach ($this->recommendations($survey, forCompany: false) as $recommendation) {
            $opportunity = $this->opportunities->openSolution(
                $survey,
                Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION,
                $recommendation['service_name'],
                $recommendation['factor'].' em atenção ('.$recommendation['score_label'].').',
                null,
            );

            if ($opportunity !== null) {
                $this->applyDefaultOwner($opportunity, (int) $recommendation['id']);
                $created++;
            }
        }

        return $created;
    }

    /**
     * «Solicitar conversa»: gera o lead com empresa, campanha, serviço, necessidade, data e (se a regra tiver) o
     * responsável comercial. Se já existe sugestão aberta do mesmo serviço, ela passa a ser pedido da empresa.
     */
    public function requestConversation(Survey $survey, User $requester, int $matchId, ?string $note): Nr1Opportunity
    {
        if (! $survey->usesReviewFlow()) {
            throw new Nr1CampaignException('As soluções valem para campanhas NR-1 V11.');
        }
        if ((int) $requester->company_id !== (int) $survey->company_id || $requester->isSuperAdmin() || $requester->isOwner()) {
            throw new Nr1CampaignException('Só a empresa da campanha solicita conversa.');
        }

        $recommendation = collect($this->recommendations($survey, forCompany: true))->firstWhere('id', $matchId);
        if ($recommendation === null) {
            throw new Nr1CampaignException('Esta solução não está disponível para a campanha.');
        }

        $service = (string) $recommendation['service_name'];
        $need = trim($recommendation['factor'].' em atenção ('.$recommendation['score_label'].').'.(filled($note) ? ' '.trim((string) $note) : ''));

        $requestKey = Nr1Opportunity::openKey($survey->id, Nr1Opportunity::ORIGIN_SOLUTION_REQUEST, null, null, $service);
        if (Nr1Opportunity::query()->where('open_key', $requestKey)->exists()) {
            throw new Nr1CampaignException('Já existe um pedido de conversa em aberto para esta solução.');
        }

        $suggestion = Nr1Opportunity::query()
            ->where('open_key', Nr1Opportunity::openKey($survey->id, Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION, null, null, $service))
            ->first();

        if ($suggestion !== null) {
            $converted = DB::transaction(function () use ($suggestion, $requester, $need, $requestKey): Nr1Opportunity {
                $from = $suggestion->status;
                $suggestion->forceFill([
                    'origin' => Nr1Opportunity::ORIGIN_SOLUTION_REQUEST,
                    'open_key' => $requestKey,
                    'status' => Nr1Opportunity::STATUS_NEW,
                    'requested_by' => $requester->id,
                    'need' => $need,
                    'last_activity_at' => now(),
                ])->save();

                Nr1OpportunityEvent::query()->create([
                    'opportunity_id' => $suggestion->id,
                    'user_id' => $requester->id,
                    'action' => 'requested',
                    'from_status' => $from,
                    'to_status' => $suggestion->status,
                    'body' => 'Conversa solicitada pela empresa (sugestão automática convertida em pedido).',
                ]);

                return $suggestion;
            });

            // A empresa pediu conversa sobre uma sugestão que já estava na fila: aviso à Talents.
            app(PublishNr1Notice::class)->proposalRequested($converted);

            return $converted;
        }

        $opportunity = $this->opportunities->openSolution($survey, Nr1Opportunity::ORIGIN_SOLUTION_REQUEST, $service, $need, $requester);
        if ($opportunity === null) {
            throw new Nr1CampaignException('Já existe um pedido de conversa em aberto para esta solução.');
        }
        $this->applyDefaultOwner($opportunity, $matchId);

        return $opportunity;
    }

    private function applyDefaultOwner(Nr1Opportunity $opportunity, int $matchId): void
    {
        $ownerId = Nr1ServiceMatch::query()->whereKey($matchId)->value('commercial_owner_id');
        if ($ownerId === null) {
            return;
        }

        $opportunity->forceFill(['assigned_to' => $ownerId])->save();
        Nr1OpportunityEvent::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => null,
            'action' => 'assigned_default',
            'from_status' => $opportunity->status,
            'to_status' => $opportunity->status,
            'body' => 'Responsável comercial padrão da regra do serviço.',
        ]);
    }
}
