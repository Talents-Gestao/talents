<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Nr1CampaignException;
use App\Models\Nr1Opportunity;
use App\Models\Survey;
use App\Models\SurveyServiceSuggestion;
use App\Models\User;
use App\Services\Nr1\Nr1OpportunityService;
use Illuminate\Support\Facades\Cache;

/**
 * Lista, edição e liberação das sugestões de serviço. A geração fica em ServiceSuggestionsGenerator.
 */
final class SurveyServiceSuggestionService
{
    public function __construct(private readonly Nr1OpportunityService $opportunities) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Survey $survey, bool $forTalents): array
    {
        $statuses = $forTalents
            ? [SurveyServiceSuggestion::STATUS_PENDING, SurveyServiceSuggestion::STATUS_APPROVED]
            : [SurveyServiceSuggestion::STATUS_APPROVED];

        $requested = Nr1Opportunity::query()
            ->where('survey_id', $survey->id)
            ->where('origin', Nr1Opportunity::ORIGIN_SOLUTION_REQUEST)
            ->whereNotIn('status', Nr1Opportunity::CLOSED_STATUSES)
            ->pluck('service_key')
            ->all();

        $items = SurveyServiceSuggestion::query()
            ->where('survey_id', $survey->id)
            ->whereIn('status', $statuses)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (SurveyServiceSuggestion $suggestion) use ($survey, $forTalents, $requested): array {
                return [
                    'id' => $suggestion->id,
                    'product_name' => $suggestion->product_name,
                    'rationale' => $suggestion->rationale,
                    'status' => $suggestion->status,
                    'status_label' => SurveyServiceSuggestion::STATUS_LABELS[$suggestion->status] ?? $suggestion->status,
                    'requested' => in_array($suggestion->product_name, $requested, true),
                    'update_url' => $forTalents ? route('admin.companies.surveys.service-suggestions.update', [$survey->company_id, $survey->id, $suggestion->id]) : null,
                    'dismiss_url' => $forTalents && $suggestion->status === SurveyServiceSuggestion::STATUS_PENDING
                        ? route('admin.companies.surveys.service-suggestions.dismiss', [$survey->company_id, $survey->id, $suggestion->id])
                        : null,
                    'request_url' => ! $forTalents && $suggestion->status === SurveyServiceSuggestion::STATUS_APPROVED
                        ? route('client.surveys.service-suggestions.request', [$survey->id, $suggestion->id])
                        : null,
                ];
            })
            ->all();

        return [
            'generated_at' => $survey->service_suggestions_generated_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'pending' => Cache::has(ServiceSuggestionsGenerator::pendingCacheKey($survey->id)),
            'can_moderate' => $forTalents,
            'release_url' => $forTalents ? route('admin.companies.surveys.service-suggestions.release', [$survey->company_id, $survey->id]) : null,
            'items' => $items,
        ];
    }

    public function update(SurveyServiceSuggestion $suggestion, string $rationale): void
    {
        $suggestion->forceFill(['rationale' => $rationale])->save();
    }

    public function dismiss(SurveyServiceSuggestion $suggestion): void
    {
        if ($suggestion->status !== SurveyServiceSuggestion::STATUS_PENDING) {
            throw new Nr1CampaignException('Só é possível dispensar uma sugestão que ainda aguarda aprovação.');
        }

        $suggestion->forceFill(['status' => SurveyServiceSuggestion::STATUS_DISMISSED])->save();
    }

    public function release(Survey $survey, User $actor): int
    {
        $pending = SurveyServiceSuggestion::query()
            ->where('survey_id', $survey->id)
            ->where('status', SurveyServiceSuggestion::STATUS_PENDING)
            ->get();

        foreach ($pending as $suggestion) {
            $suggestion->forceFill([
                'status' => SurveyServiceSuggestion::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ])->save();
        }

        return $pending->count();
    }

    public function requestConversation(Survey $survey, SurveyServiceSuggestion $suggestion, User $requester): void
    {
        if ((int) $suggestion->survey_id !== (int) $survey->id || $suggestion->status !== SurveyServiceSuggestion::STATUS_APPROVED) {
            throw new Nr1CampaignException('Este serviço ainda não está disponível para a empresa.');
        }
        if ((int) $requester->contextCompanyId() !== (int) $survey->company_id || $requester->isSuperAdmin() || $requester->isOwner()) {
            throw new Nr1CampaignException('Só a empresa da campanha solicita conversa.');
        }

        $opportunity = $this->opportunities->openSolution(
            $survey,
            Nr1Opportunity::ORIGIN_SOLUTION_REQUEST,
            $suggestion->product_name,
            $suggestion->rationale,
            $requester,
        );
        if ($opportunity === null) {
            throw new Nr1CampaignException('Já existe um pedido de conversa em aberto para este serviço.');
        }
    }
}
