<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Admin\ActionPlanAdminController;
use App\Http\Controllers\Client\ActionPlanController;
use App\Http\Controllers\Client\SurveyResultsController;
use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\Company;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;
use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use App\Models\User;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Services\SurveyResultsPresenter;
use App\Support\Nr1GroupPrivacy;
use App\Support\Nr1RiskScenarioResolver;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use ReflectionProperty;

/**
 * Campanhas do modelo anterior (sem fluxo de revisão) dentro do shell NR-1. Reaproveita os controllers e as props das
 * telas antigas: as regras de acesso, publicação, MFA e o fluxo Diagnóstico/Parecer/Publicar continuam exatamente os mesmos.
 * Nada aqui grava dado; as ações do laudo seguem postando para as rotas antigas.
 */
final class Nr1ShellLegacy
{
    public const NOTICE = 'Pesquisa no modelo anterior';

    public static function isLegacy(Survey $survey): bool
    {
        return ! $survey->usesReviewFlow();
    }

    /**
     * Props que o controller antigo entregaria à página antiga (já resolvidas), sem as props compartilhadas do Inertia.
     *
     * @param  callable(): InertiaResponse  $call
     * @return array<string, mixed>
     */
    public static function capture(callable $call, Request $request): array
    {
        $response = $call();
        $props = (new ReflectionProperty($response, 'props'))->getValue($response);

        return $response->resolveProperties($request, $props);
    }

    /**
     * Dados da seção legada. Retorna null quando a seção não existe no modelo anterior (só aviso).
     *
     * @return array<string, mixed>
     */
    public function section(Request $request, User $user, string $profile, string $section, Survey $survey, ?Company $company): array
    {
        $forTalents = $profile === Nr1Shell::PROFILE_TALENTS;
        $base = [
            'notice' => self::NOTICE,
            'minPerSector' => (int) $survey->min_responses_for_breakdown,
            'forTalents' => $forTalents,
            // O caminho direto antigo («Parecer e plano» no Admin; resultados na empresa) segue acessível de dentro do painel.
            'fullUrl' => $forTalents && $company !== null
                ? route('admin.companies.surveys.action-plan.edit', [$company->id, $survey->id])
                : route('client.surveys.results', $survey->id),
            'fullLabel' => $forTalents ? 'Abrir Parecer e plano (tela completa)' : 'Abrir resultados (tela completa)',
        ];

        // A empresa precisa dos mesmos módulos das rotas antigas (Resultados: pesquisas; Plano/Parecer: planos_acao;
        // relatórios liberados: relatorios).
        if (! $forTalents) {
            $module = match ($section) {
                'parecer', 'plano-de-acao' => PermissionModule::PlanosAcao,
                'documentos' => PermissionModule::Relatorios,
                default => PermissionModule::Pesquisas,
            };
            abort_unless($user->canAccess($module, PermissionAction::View), 403);
        }

        return $base + match ($section) {
            'visao-geral' => ['overview' => $this->overview($survey, $forTalents)],
            'campanha' => ['campaign' => $this->campaign($survey, $forTalents)],
            'resultados' => ['results' => $this->results($request, $survey, $company, $forTalents)],
            'plano-de-acao' => ['plan' => $this->plan($survey, $company, $forTalents)],
            'documentos' => ['central' => $this->central($survey, $company, $forTalents)],
            'parecer' => ['documents' => $this->documents($request, $survey, $company, $forTalents)],
            default => [],
        };
    }

    /**
     * Cabeçalho do laudo (período, cenário de risco, mínimo por setor, última publicação) + indicador geral e dimensões.
     *
     * @return array<string, mixed>
     */
    private function overview(Survey $survey, bool $forTalents): array
    {
        $presented = SurveyResultsPresenter::forSurvey($survey);
        $overall = $presented['overall'];
        $plan = ActionPlan::query()->where('survey_id', $survey->id)->where('company_id', $survey->company_id)->first();
        $published = $plan?->admin_published_at !== null;

        $respondents = $overall !== null ? (int) $overall->respondent_count : 0;
        $minGroup = Nr1GroupPrivacy::minFor($survey);
        // Empresa: abaixo do grupo mínimo, sem número nem percentual de participação (mesma regra da V11).
        $hideParticipation = ! $forTalents && $respondents < $minGroup;
        $eligible = self::eligible($survey, $respondents);
        $sections = collect($presented['bySection']);
        $items = $plan?->items()->get() ?? collect();
        // A empresa só vê o plano depois da publicação do parecer (gate do legado).
        $planVisible = $forTalents || $published;

        $cards = $overall === null ? null : [
            'overall' => [
                'score_label' => number_format((float) $overall->average_score, 2, ',', ''),
                'risk_level' => (string) $overall->risk_level,
                'band_label' => Nr1ShellPageData::BAND_LABELS[$overall->risk_level] ?? (string) $overall->risk_level,
            ],
            'participation' => $hideParticipation
                ? ['hidden' => true, 'message' => self::insufficient($minGroup)]
                : [
                    'hidden' => false,
                    'respondents' => $respondents,
                    'eligible' => $eligible,
                    'percent_label' => $eligible ? number_format($respondents / $eligible * 100, 1, ',', '.').'%' : null,
                ],
            'attention' => [
                'count' => $sections->filter(fn ($row) => in_array($row->risk_level, ['yellow', 'red'], true))->count(),
                'total' => $sections->count(),
            ],
            // «Responsável confirmado» no modelo anterior = item do plano do parecer com responsável preenchido.
            'plan' => $planVisible && $items->isNotEmpty() ? [
                'confirmed' => $items->filter(fn ($item) => filled($item->responsible_name))->count(),
                'total' => $items->count(),
            ] : null,
            'planMessage' => match (true) {
                ! $planVisible => 'O plano aparece quando a Talents publicar o parecer.',
                $items->isEmpty() => 'Plano de ação ainda não elaborado no parecer.',
                default => null,
            },
        ];

        return [
            'cards' => $cards,
            // Pontos fortes dependem do sentido de cada enunciado do questionário de 39 perguntas (V11).
            'strengthsEmpty' => 'Os pontos fortes são calculados no questionário NR-1 de 39 perguntas. Esta pesquisa está no modelo anterior, sem essa leitura.',
            'title' => $survey->title,
            'period' => ($survey->starts_at?->format('d/m/Y') ?? '—').' — '.($survey->ends_at?->format('d/m/Y') ?? '—'),
            'surveyStatus' => $survey->status,
            'riskScenario' => $overall !== null
                ? (Nr1RiskScenarioResolver::scenarioConfig(Nr1RiskScenarioResolver::forSurvey($survey) ?? 'green')['short_label'] ?? null)
                : null,
            'minPerSector' => (int) $survey->min_responses_for_breakdown,
            'lastPublication' => $plan?->admin_published_at?->format('d/m/Y H:i'),
            'publicationLabel' => $published ? 'Publicado para a empresa' : ($forTalents ? 'Rascunho — ainda não enviado' : 'Aguardando publicação'),
            'published' => $published,
            'respondents' => $overall !== null ? (int) $overall->respondent_count : 0,
            'overall' => $overall !== null ? ['score_label' => number_format((float) $overall->average_score, 2, ',', ''), 'risk_level' => $overall->risk_level] : null,
            'dimensions' => collect($presented['bySection'])->map(fn ($row) => [
                'title' => $row->meta['section_title'] ?? $row->section?->title ?? '—',
                'score_label' => number_format((float) $row->average_score, 2, ',', ''),
                'risk_level' => $row->risk_level,
                'bar_percent' => round(((float) $row->average_score - 1) / 4 * 100, 1),
            ])->values()->all(),
        ];
    }

    /**
     * Campanha NR-1 no modelo anterior: só o que existe (período, coleta, participação e link público).
     *
     * @return array<string, mixed>
     */
    private function campaign(Survey $survey, bool $forTalents): array
    {
        $reason = $survey->publicParticipationClosureReason();
        $completed = $survey->completedResponses()->count();
        $minGroup = Nr1GroupPrivacy::minFor($survey);
        $hide = ! $forTalents && $completed < $minGroup;
        $eligible = self::eligible($survey, $completed);
        $survey->loadMissing('template.sections.questions');
        $publicUrl = $reason === null ? route('survey.public', ['token' => $survey->public_token]) : null;

        return [
            'facts' => Nr1ShellCampaignFacts::build($survey),
            'questionnaire' => [
                'questions' => (int) ($survey->template?->sections->sum(fn ($section) => $section->questions->count()) ?? 0),
                'dimensions' => (int) ($survey->template?->sections->count() ?? 0),
            ],
            'participation' => $hide ? null : [
                'completed' => $completed,
                'eligible' => $eligible,
                'percent' => $eligible ? round($completed / $eligible * 100, 1) : null,
                'percent_label' => $eligible ? number_format($completed / $eligible * 100, 1, ',', '.').'%' : null,
            ],
            'insufficientMessage' => $hide ? self::insufficient($minGroup) : null,
            'publicQr' => $publicUrl !== null ? Nr1ShellCampaignFacts::qr($publicUrl) : null,
            'registrationUrl' => $forTalents ? route('admin.companies.surveys.registration.edit', [$survey->company_id, $survey->id]) : null,
            'period' => ($survey->starts_at?->format('d/m/Y') ?? '—').' — '.($survey->ends_at?->format('d/m/Y') ?? '—'),
            'open' => $reason === null,
            'label' => match ($reason) {
                null => 'Campanha aberta',
                'not_started' => 'Campanha ainda não iniciada',
                'ended' => 'Campanha encerrada',
                default => 'Campanha inativa',
            },
            'completed' => $hide ? null : $completed,
            'publicUrl' => $publicUrl,
        ];
    }

    /**
     * Laudo 01 Diagnóstico: as mesmas props do painel de resultados (Talents: do laudo; empresa: de Resultados).
     *
     * @return array<string, mixed>
     */
    private function results(Request $request, Survey $survey, ?Company $company, bool $forTalents): array
    {
        $props = $forTalents && $company !== null
            ? self::capture(fn () => app(ActionPlanAdminController::class)->edit($company, $survey), $request)
            : self::capture(fn () => app(SurveyResultsController::class)->show($request, $survey), $request);

        $panel = array_intersect_key($props, array_flip([
            'survey', 'overall', 'bySection', 'deptOveralls', 'deptSectionsByDepartment', 'insights',
            'questionDistributions', 'departmentParticipation', 'questionDistributionsByDepartment',
        ]));

        return [
            'panel' => $panel,
            // Mia e relatórios só existem na tela da empresa (props de Resultados).
            'client' => $forTalents ? null : array_intersect_key($props, array_flip(['aiEnabled', 'aiAnalysis', 'aiAnalysisPending', 'riskScenarioLabel', 'actionPlanPublished'])),
            'recalculateUrl' => $forTalents ? null : route('client.surveys.recalculate', $survey->id),
            'reports' => $forTalents ? [] : $this->clientReports($survey, (bool) ($props['actionPlanPublished'] ?? false)),
            'nextUrl' => route('nr1.parecer'),
        ];
    }

    /**
     * Plano de ação no modelo anterior: os itens do plano do Laudo (título, responsável, prazo, situação), só leitura.
     * A edição continua em «Parecer e publicação». A empresa só vê depois da publicação do parecer.
     *
     * @return array<string, mixed>
     */
    private function plan(Survey $survey, ?Company $company, bool $forTalents): array
    {
        $plan = ActionPlan::query()->where('survey_id', $survey->id)->where('company_id', $survey->company_id)->first();
        $published = $plan?->admin_published_at !== null;
        if (! $forTalents && ! $published) {
            return ['locked' => true, 'published' => false, 'items' => []];
        }

        $staff = $forTalents && $company !== null;

        return [
            'locked' => false,
            'published' => $published,
            'publishedAt' => $plan?->admin_published_at?->format('d/m/Y H:i'),
            'items' => ($plan?->items()->get() ?? collect())->map(fn (ActionPlanItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'responsible' => $item->responsible_name,
                'due' => $item->due_date?->format('d/m/Y'),
                'status' => (string) $item->status,
            ])->values()->all(),
            'editUrl' => $staff ? route('nr1.parecer') : null,
            'pdfUrl' => match (true) {
                $staff => route('admin.companies.surveys.action-plan.pdf', [$company->id, $survey->id]),
                $published => route('client.surveys.reports.action-plan', $survey->id),
                default => null,
            },
        ];
    }

    /**
     * Central de documentos no modelo anterior: os dois documentos oficiais (Relatório executivo; Relatório técnico +
     * plano de ação) com o arquivo que existe hoje, a situação da publicação do Laudo e o histórico REAL (versões
     * liberadas e validadas dos documentos e a publicação do parecer). Não há revisão PASSEG neste modelo.
     *
     * @return array<string, mixed>
     */
    private function central(Survey $survey, ?Company $company, bool $forTalents): array
    {
        $plan = ActionPlan::query()->where('survey_id', $survey->id)->where('company_id', $survey->company_id)->first();
        $published = $plan?->admin_published_at !== null;
        $staff = $forTalents && $company !== null;

        $executive = PsychosocialDocument::query()
            ->where('survey_id', $survey->id)
            ->where('type', PsychosocialDocument::TYPE_EXECUTIVE_BRIEF)
            ->with('currentVersion')
            ->first()?->currentVersion;
        $executiveReady = $executive !== null && $executive->pdf_status === PsychosocialDocumentVersion::PDF_READY;

        $documents = [
            [
                'key' => 'executivo',
                'title' => 'Relatório executivo',
                'text' => 'Visão resumida para a liderança: indicadores, prioridades e plano proposto.',
                'url' => match (true) {
                    $staff && $executiveReady => route('admin.companies.surveys.psychosocial-documents.download', [$company->id, $survey->id, PsychosocialDocument::TYPE_EXECUTIVE_BRIEF]),
                    ! $forTalents && $published => route('client.surveys.reports.executive', $survey->id),
                    default => null,
                },
                'pending' => $staff ? 'Ainda não liberado: libere o resumo executivo em «Parecer e publicação».' : 'Disponível quando a Talents publicar o parecer.',
            ],
            [
                'key' => 'tecnico',
                'title' => 'Relatório técnico + plano de ação',
                'text' => 'Parecer técnico com metodologia, resultados, prioridades e o plano de ação com responsáveis e prazos.',
                'url' => match (true) {
                    $staff => route('admin.companies.surveys.action-plan.document-pdf', [$company->id, $survey->id]),
                    $published => route('client.surveys.reports.action-plan', $survey->id),
                    default => null,
                },
                'pending' => 'Disponível quando a Talents publicar o parecer.',
            ],
        ];

        $history = collect();
        if ($plan?->admin_published_at !== null) {
            $history->push(['at' => $plan->admin_published_at, 'label' => 'Parecer e plano publicados para a empresa']);
        }
        // Versões já liberadas (ou validadas) dos documentos do Laudo: nunca há rascunho nesta tabela.
        PsychosocialDocumentVersion::query()
            ->whereHas('document', fn ($query) => $query->where('survey_id', $survey->id))
            ->with('document')
            ->get()
            ->each(function (PsychosocialDocumentVersion $version) use ($history): void {
                $label = PsychosocialDocumentService::label($version->document->type);
                $history->push([
                    'at' => $version->created_at,
                    'label' => $version->kind === PsychosocialDocumentVersion::KIND_VALIDATED
                        ? "{$label}: validação informada registrada (versão {$version->version_number})"
                        : "{$label}: versão {$version->version_number} liberada",
                ]);
            });

        return [
            'documents' => $documents,
            'status' => $published ? 'Publicado' : ($staff ? 'Rascunho' : 'Em preparação'),
            'published' => $published,
            'version' => $executive?->version_number,
            'help' => match (true) {
                $published => 'Parecer e plano publicados. Os arquivos estão disponíveis para a empresa.',
                $staff => 'O parecer ainda não foi publicado para a empresa. A publicação é feita em «Parecer e publicação».',
                default => 'Documento em revisão pela Talents.',
            },
            'parecerUrl' => $staff ? route('nr1.parecer') : null,
            'history' => $history->sortByDesc('at')->map(fn (array $row) => [
                'at' => $row['at']?->format('d/m/Y H:i'),
                'label' => $row['label'],
            ])->values()->all(),
        ];
    }

    /** Elegíveis do cadastro técnico (nunca estimados); ignorados se forem menores que as respostas. */
    private static function eligible(Survey $survey, int $respondents): ?int
    {
        $eligible = SurveyCampaignRegistration::query()->where('survey_id', $survey->id)->value('eligible_count');

        return $eligible !== null && (int) $eligible > 0 && (int) $eligible >= $respondents ? (int) $eligible : null;
    }

    private static function insufficient(int $minGroup): string
    {
        return 'Amostra insuficiente: com menos de '.$minGroup.' respostas, o número e a participação não são exibidos.';
    }

    /**
     * Laudo 02 Parecer + 03 Publicar + Documentos liberados (Talents) ou o parecer e plano publicados (empresa).
     *
     * @return array<string, mixed>
     */
    private function documents(Request $request, Survey $survey, ?Company $company, bool $forTalents): array
    {
        if ($forTalents && $company !== null) {
            return ['laudo' => self::capture(fn () => app(ActionPlanAdminController::class)->edit($company, $survey), $request)];
        }

        $plan = self::capture(fn () => app(ActionPlanController::class)->show($request, $survey), $request);
        $published = ! ($plan['actionPlanLocked'] ?? true);

        return ['actionPlan' => $plan, 'reports' => $this->clientReports($survey, $published)];
    }

    /**
     * Relatórios automáticos e exportações que a tela antiga de Resultados oferece (mesmas rotas e mesma regra).
     *
     * @return list<array{label: string, href: string}>
     */
    private function clientReports(Survey $survey, bool $planPublished): array
    {
        $reports = [
            ['label' => 'Relatório executivo', 'href' => route('client.surveys.reports.executive', $survey->id)],
        ];
        if ($planPublished) {
            $reports[] = ['label' => 'Plano de ação (PDF)', 'href' => route('client.surveys.reports.action-plan', $survey->id)];
        }

        return array_merge($reports, [
            ['label' => 'Encaminhamento técnico', 'href' => route('client.surveys.reports.referral', $survey->id)],
            ['label' => 'Dados técnicos (RH)', 'href' => route('client.surveys.reports.technical', $survey->id)],
            ['label' => 'Exportar JSON (agregado)', 'href' => route('client.surveys.export.json', $survey->id)],
            ['label' => 'Exportar CSV (agregado)', 'href' => route('client.surveys.export.csv', $survey->id)],
        ]);
    }
}
