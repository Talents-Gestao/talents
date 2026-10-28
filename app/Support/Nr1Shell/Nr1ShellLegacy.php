<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Admin\ActionPlanAdminController;
use App\Http\Controllers\Client\ActionPlanController;
use App\Http\Controllers\Client\SurveyResultsController;
use App\Models\ActionPlan;
use App\Models\Company;
use App\Models\Survey;
use App\Models\User;
use App\Services\SurveyResultsPresenter;
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
        $base = ['notice' => self::NOTICE, 'minPerSector' => (int) $survey->min_responses_for_breakdown, 'forTalents' => $forTalents];

        // A empresa precisa dos mesmos módulos das rotas antigas (Resultados: pesquisas; Plano/Parecer: planos_acao).
        if (! $forTalents) {
            $module = $section === 'documentos' || $section === 'plano-de-acao' ? PermissionModule::PlanosAcao : PermissionModule::Pesquisas;
            abort_unless($user->canAccess($module, PermissionAction::View), 403);
        }

        return $base + match ($section) {
            'visao-geral' => ['overview' => $this->overview($survey, $forTalents)],
            'campanha' => ['campaign' => $this->campaign($survey)],
            'resultados' => ['results' => $this->results($request, $survey, $company, $forTalents)],
            'documentos' => ['documents' => $this->documents($request, $survey, $company, $forTalents)],
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

        return [
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
    private function campaign(Survey $survey): array
    {
        $reason = $survey->publicParticipationClosureReason();

        return [
            'period' => ($survey->starts_at?->format('d/m/Y') ?? '—').' — '.($survey->ends_at?->format('d/m/Y') ?? '—'),
            'open' => $reason === null,
            'label' => match ($reason) {
                null => 'Campanha aberta',
                'not_started' => 'Campanha ainda não iniciada',
                'ended' => 'Campanha encerrada',
                default => 'Campanha inativa',
            },
            'completed' => $survey->completedResponses()->count(),
            'publicUrl' => $reason === null ? route('survey.public', ['token' => $survey->public_token]) : null,
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
            'nextUrl' => route('nr1.documentos'),
        ];
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
