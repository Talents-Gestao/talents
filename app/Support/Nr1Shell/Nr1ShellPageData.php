<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\PsychosocialInternalReview;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyCampaignRegistration;
use App\Models\User;
use App\Services\Nr1\Nr1CampaignOverview;
use App\Services\Nr1\Nr1JourneyBuilder;
use App\Services\Psychosocial\InternalReviewService;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\PsychosocialDashboardExtras;
use App\Support\Nr1GroupPrivacy;
use App\Support\Nr1InstrumentMatrix;
use App\Support\PsychosocialMeasurePresenter;

/**
 * Dados das telas do shell NR-1 que reproduzem o HTML aprovado (Etapa B: Visão geral, Campanha NR-1 e Minha jornada).
 * Só reaproveita os serviços das telas antigas (painel, campanha, jornada): nada é recalculado nem reaberto aqui.
 */
class Nr1ShellPageData
{
    public function __construct(
        private readonly PsychosocialDashboardBuilder $dashboard,
        private readonly PsychosocialDashboardExtras $extras,
        private readonly Nr1CampaignOverview $campaign,
        private readonly Nr1JourneyBuilder $journey,
        private readonly InternalReviewService $reviews,
    ) {}

    /**
     * Visão geral: KPIs, pontos fortes, sete dimensões, prioridades, próximas ações e participação por grupo.
     *
     * @return array<string, mixed>
     */
    public function overview(Survey $survey, bool $hideParticipation, string $insufficientMessage): array
    {
        $dashboard = $this->dashboard->build($survey);
        if (! ($dashboard['has_results'] ?? false)) {
            return ['hasResults' => false];
        }

        $items = collect(Nr1InstrumentMatrix::forTemplate((int) $survey->survey_template_id))->keyBy('question_id');
        $cards = $dashboard['cards'];

        $respondents = (int) ($cards['participation']['respondents'] ?? 0);
        $groups = $dashboard['groups']['items'] ?? [];
        $others = collect($groups)->firstWhere('kind', 'others');
        // Pontos fortes só com a base mínima da campanha (mesmo grupo mínimo da privacidade), para qualquer perfil.
        $minGroup = Nr1GroupPrivacy::minFor($survey);
        $strengthsAllowed = ! $hideParticipation && $respondents >= $minGroup;

        return [
            'hasResults' => true,
            'cards' => [
                'overall' => $cards['overall'],
                'participation' => $hideParticipation
                    ? ['hidden' => true, 'message' => $insufficientMessage]
                    : ['hidden' => false, 'percent_label' => $cards['participation']['percent_label'], 'respondents' => $respondents, 'eligible' => $cards['participation']['eligible']],
                'attention' => $cards['attention'],
                'plan' => $cards['plan'],
            ],
            // As 3 perguntas com maior % favorável (desempate: maior base, depois a ordem da pergunta).
            'strengths' => $strengthsAllowed ? collect($dashboard['strengths'] ?? [])->take(3)->map(fn (array $row) => [
                'item' => $items->get($row['id'])['item_number'] ?? null,
                'dimension' => $row['section_title'] ?? null,
                'body' => $row['body'],
                'pct_label' => $row['favorable_pct_label'],
                'favorable' => (int) $row['favorable'],
                'n' => (int) $row['n'],
            ])->values()->all() : [],
            'strengthsEmpty' => $strengthsAllowed ? null : "Pontos fortes aparecem quando a campanha tiver ao menos {$minGroup} respostas, para proteger o anonimato.",
            'strengthsBase' => $strengthsAllowed ? (int) (collect($dashboard['strengths'] ?? [])->take(3)->max('n') ?? $respondents) : null,
            'dimensions' => collect($dashboard['dimensions'] ?? [])->map(fn (array $row) => [
                'id' => $row['section_id'],
                'title' => $row['title'],
                'score_label' => $row['score_label'],
                'bar_percent' => $row['bar_percent'],
                'risk_level' => $row['risk_level'],
                'band_label' => $row['band_label'],
            ])->values()->all(),
            // Distribuição agregada por pergunta (a mesma do painel; nunca resposta individual nem setor isolado).
            'items' => collect($dashboard['items'] ?? [])->map(fn (array $row) => [
                'position' => $row['position'],
                'section_id' => $row['section_id'],
                'body' => $row['body'],
                'n' => $row['n'],
                'counts' => $row['counts'],
                'distribution' => $row['distribution'],
                'statement' => $row['statement'],
                'favorable_pct' => (float) $row['favorable_pct'],
                'unfavorable_pct' => (float) $row['unfavorable_pct'],
                'favorable_pct_label' => $row['favorable_pct_label'],
                'unfavorable_pct_label' => $row['unfavorable_pct_label'],
            ])->values()->all(),
            'attention' => collect($dashboard['attention'] ?? [])->map(fn (array $row) => [
                'title' => $row['title'],
                'score_label' => $row['score_label'],
                'band_label' => $row['band_label'],
            ])->values()->all(),
            'nextActions' => $this->extras->nextActions($survey, 6),
            'groups' => $hideParticipation ? [] : collect($groups)->sortByDesc('n')->map(fn (array $row) => [
                'name' => $row['name'],
                'n' => $row['n'],
                'percent_label' => $row['percent_label'],
                'percent' => (float) str_replace(',', '.', rtrim((string) $row['percent_label'], '%')),
            ])->values()->all(),
            'othersCount' => $hideParticipation ? null : ($others['n'] ?? null),
            'respondents' => $hideParticipation ? null : $respondents,
        ];
    }

    /** Rótulos de faixa adotados (Caderno): vêm do nível, nunca do texto antigo do painel. */
    public const BAND_LABELS = ['green' => 'Menor sinalização', 'yellow' => 'Atenção', 'red' => 'Maior sinalização'];

    /**
     * Resultados da pesquisa: dimensões, setores (com «Outros Setores» e a regra de privacidade), 39 perguntas e a
     * Revisão interna (só a consultoria). Os setores e as perguntas vêm do painel, já filtrados pelo grupo mínimo.
     *
     * @return array<string, mixed>
     */
    public function results(Survey $survey, bool $hideParticipation, string $insufficientMessage, ?Company $company, bool $forTalents): array
    {
        $base = $this->overview($survey, $hideParticipation, $insufficientMessage);
        if (! ($base['hasResults'] ?? false)) {
            return ['hasResults' => false];
        }

        $dashboard = $this->dashboard->build($survey);
        $band = fn (string $risk): string => self::BAND_LABELS[$risk] ?? $risk;
        $dimensions = fn (array $rows) => collect($rows)->map(fn (array $row) => [
            'title' => $row['title'],
            'score_label' => $row['score_label'],
            'risk_level' => $row['risk_level'],
            'band_label' => $band($row['risk_level']),
        ])->values()->all();

        $details = collect($dashboard['sectors'] ?? [])->map(fn (array $row) => ['name' => $row['department_name']] + $row)->all();
        if (($dashboard['others'] ?? null) !== null) {
            $details[] = ['name' => 'Outros Setores'] + $dashboard['others'];
        }
        $percent = collect($dashboard['groups']['items'] ?? [])->keyBy('name');

        $sectors = $hideParticipation ? [] : collect($details)->map(fn (array $row) => [
            'name' => $row['name'],
            'others' => $row['name'] === 'Outros Setores',
            'n' => $row['n'],
            'percent_label' => $percent->get($row['name'])['percent_label'] ?? null,
            'score_label' => $row['score_label'],
            'risk_level' => $row['risk_level'],
            'band_label' => $band($row['risk_level']),
            'dimensions' => $dimensions($row['dimensions'] ?? []),
        ])->sortByDesc('n')->values()->all();

        $review = null;
        if ($forTalents && $company !== null) {
            $items = $this->reviews->items($dashboard['attention']);
            $review = [
                'items' => $items,
                'decisions' => PsychosocialInternalReview::DECISION_LABELS,
                'save_url' => route('admin.companies.surveys.internal-review.store', [$company->id, $survey->id]),
            ] + $this->reviews->state($survey);
        }

        return [
            'hasResults' => true,
            'dimensions' => $base['dimensions'],
            'items' => $base['items'],
            'respondents' => $base['respondents'],
            'sectors' => $sectors,
            'groupsNote' => $dashboard['groups']['note'] ?? null,
            'minGroupSize' => $dashboard['min_group_size'] ?? 5,
            'insufficientMessage' => $hideParticipation ? $insufficientMessage : null,
            'bands' => [
                ['risk' => 'green', 'label' => self::BAND_LABELS['green'], 'range' => '1,00–2,33', 'text' => 'Menor sinalização de fatores desfavoráveis.'],
                ['risk' => 'yellow', 'label' => self::BAND_LABELS['yellow'], 'range' => '2,34–3,66', 'text' => 'Priorizar a investigação e o acompanhamento.'],
                ['risk' => 'red', 'label' => self::BAND_LABELS['red'], 'range' => '3,67–5,00', 'text' => 'Requer análise prioritária dos fatores sinalizados.'],
            ],
            'internalReview' => $review,
        ];
    }

    /**
     * Plano de ação: medidas com responsável sugerido × confirmado, prazo sugerido (editável pela Talents até a
     * publicação), status e as ações do ciclo (confirmar, iniciar, concluir) pelas rotas que já existem.
     *
     * @return array<string, mixed>
     */
    public function plan(Survey $survey, User $user, bool $forTalents): array
    {
        $scores = collect($this->dashboard->build($survey)['dimensions'] ?? [])->keyBy('title');
        $rows = PsychosocialMeasure::query()->where('survey_id', $survey->id)->orderBy('sort_order')->orderBy('id')->get();

        $published = $survey->requires_publication && $survey->published_at !== null;
        $canEditSuggestion = $forTalents && ! $published && $user->canAccessAdmin(AdminPermissionModule::Companies, PermissionAction::Edit);
        $canAct = ! $forTalents && $user->canAccess(PermissionModule::PlanosAcao, PermissionAction::Edit);

        $measures = $rows->map(function (PsychosocialMeasure $measure) use ($survey, $scores, $canEditSuggestion, $canAct): array {
            $present = PsychosocialMeasurePresenter::present($measure);
            $score = $scores->get($measure->factor_label);

            return [
                'id' => $present['id'],
                'status' => $present['status'],
                'status_label' => $present['status_label'],
                'factor_label' => $present['factor_label'],
                'measure_description' => $present['measure_description'],
                'objective' => $present['objective'],
                'expected_result' => $present['expected_result'],
                'indicator' => $present['indicator'],
                'suggested' => $present['suggested'],
                'confirmed' => $present['confirmed'],
                'effectiveness' => ['result_label' => $present['effectiveness']['result_label'], 'note' => $present['effectiveness']['note']],
                'score_label' => $score['score_label'] ?? null,
                'risk_level' => $score['risk_level'] ?? null,
                'band_label' => isset($score['risk_level']) ? (self::BAND_LABELS[$score['risk_level']] ?? null) : null,
                'timeline' => $this->timeline($measure),
                'urls' => [
                    'suggestion' => $canEditSuggestion ? route('admin.companies.surveys.measures.suggestion', [$survey->company_id, $survey->id, $measure->id]) : null,
                    'confirm' => $canAct && $measure->status === PsychosocialMeasure::STATUS_TO_APPROVE ? route('client.surveys.measures.confirm', [$survey->id, $measure->id]) : null,
                    'start' => $canAct && $measure->status === PsychosocialMeasure::STATUS_PENDING ? route('client.surveys.measures.start', [$survey->id, $measure->id]) : null,
                    'complete' => $canAct && $measure->status === PsychosocialMeasure::STATUS_IN_PROGRESS ? route('client.surveys.measures.complete', [$survey->id, $measure->id]) : null,
                ],
            ];
        })->all();

        $confirmed = $rows->filter(fn (PsychosocialMeasure $m) => $m->hasConfirmedResponsible())->count();

        return [
            'measures' => $measures,
            'counters' => ['confirmed' => $confirmed, 'pending' => $rows->count() - $confirmed, 'total' => $rows->count()],
            'canEditSuggestion' => $canEditSuggestion,
            'canAct' => $canAct,
            'users' => $canAct ? User::query()
                ->whereHas('workspaces', fn ($q) => $q->where('company_id', $survey->company_id)->where('is_active', true))
                ->orderBy('name')->get(['id', 'name'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all() : [],
            'fullUrl' => $forTalents
                ? route('admin.companies.surveys.measures.edit', [$survey->company_id, $survey->id])
                : route('client.surveys.measures.index', $survey->id),
        ];
    }

    /**
     * Linha do tempo simples do ciclo da medida: Proposta → Confirmada → Em andamento → Concluída.
     *
     * @return list<array{label: string, state: string}>
     */
    private function timeline(PsychosocialMeasure $measure): array
    {
        $order = [PsychosocialMeasure::STATUS_TO_APPROVE => 0, PsychosocialMeasure::STATUS_PENDING => 1, PsychosocialMeasure::STATUS_IN_PROGRESS => 2, PsychosocialMeasure::STATUS_IMPLEMENTED => 3, PsychosocialMeasure::STATUS_REPLANNED => 1];
        $current = $order[$measure->status] ?? 0;

        return collect(['Proposta', 'Confirmada', 'Em andamento', 'Concluída'])->map(fn (string $label, int $index) => [
            'label' => $label,
            'state' => $index < $current ? 'done' : ($index === $current ? 'current' : 'todo'),
        ])->all();
    }

    /**
     * Campanha NR-1 para quem gerencia (Talents e Empresa): orientação, treinamentos por público, questionário, link e
     * participação. Talents vê as ações de contratação; a Empresa vê «Solicitar proposta» (com permissão de edição).
     *
     * @return array<string, mixed>
     */
    public function campaign(Survey $survey, User $user, bool $forTalents, bool $hideParticipation): array
    {
        $overview = $this->campaign->build($survey, $forTalents);
        $registration = SurveyCampaignRegistration::query()->where('survey_id', $survey->id)->first();
        $open = $survey->publicParticipationClosureReason() === null;
        $canRequest = ! $forTalents && $user->canAccess(PermissionModule::Pesquisas, PermissionAction::Edit);

        $cards = collect($overview['trainings'])->map(function (array $card) use ($survey, $canRequest): array {
            $card['rows'] = collect($card['rows'])->map(fn (array $row) => $row + [
                'request_url' => $canRequest && $row['state'] === 'not_contracted'
                    ? route('client.surveys.trainings.request', [$survey->id, $card['modality'], $row['audience']])
                    : null,
            ])->all();

            return $card;
        })->all();

        $matrix = Nr1InstrumentMatrix::forTemplate((int) $survey->survey_template_id);

        return [
            'orientation' => $overview['orientation'],
            'trainings' => $cards,
            'collection' => $overview['collection'],
            'questionnaire' => [
                'questions' => count($matrix),
                'dimensions' => collect($matrix)->pluck('section_id')->unique()->count(),
            ],
            'links' => $survey->audienceLinks()->orderBy('audience')->get()->map(function (SurveyAudienceLink $link) use ($open): array {
                $url = $open && $link->closureReason() === null ? route('survey.public', ['token' => $link->token]) : null;

                return ['label' => $link->label(), 'url' => $url, 'qr' => $url !== null ? Nr1ShellCampaignFacts::qr($url) : null];
            })->values()->all(),
            'publicUrl' => $open ? route('survey.public', ['token' => $survey->public_token]) : null,
            'publicQr' => $open ? Nr1ShellCampaignFacts::qr(route('survey.public', ['token' => $survey->public_token])) : null,
            'facts' => Nr1ShellCampaignFacts::build($survey),
            'participation' => $hideParticipation ? null : [
                'completed' => $overview['participation']['completed'],
                'eligible' => $overview['participation']['eligible'],
                'percent_label' => $overview['participation']['percent_label'],
                'percent' => $overview['participation']['percent_label'] !== null
                    ? (float) str_replace(',', '.', rtrim($overview['participation']['percent_label'], '%'))
                    : 0,
            ],
            'eligibleEditor' => $forTalents ? [
                'count' => $registration?->eligible_count,
                'source' => $registration?->eligible_source ?? 'Informado pelo RH',
                'url' => route('admin.companies.surveys.campaign.eligible', [$survey->company_id, $survey->id]),
            ] : null,
            'contract' => $forTalents ? [
                'contract' => route('admin.companies.surveys.campaign.contract', [$survey->company_id, $survey->id]),
                'uncontract' => route('admin.companies.surveys.campaign.uncontract', [$survey->company_id, $survey->id]),
            ] : null,
            'canRequest' => $canRequest,
        ];
    }

    /**
     * Minha jornada (colaborador): orientação e só o treinamento contratado e liberado ao público do link.
     *
     * @return array<string, mixed>|null
     */
    public function journey(Survey $survey, ?SurveyAudienceLink $link, string $token): ?array
    {
        $data = $this->journey->build($survey, $link, fn ($training) => route('survey.public.training-material', [$token, $training->id]));
        if ($data === null) {
            return null;
        }

        $matrix = Nr1InstrumentMatrix::forTemplate((int) $survey->survey_template_id);

        return $data + [
            'questionnaire' => ['questions' => count($matrix), 'dimensions' => collect($matrix)->pluck('section_id')->unique()->count()],
            'surveyUrl' => route('survey.public', ['token' => $token]),
        ];
    }
}
