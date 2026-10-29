<?php

declare(strict_types=1);

namespace App\Http\Controllers\Nr1;

use App\Http\Controllers\Controller;
use App\Models\PsychosocialReviewRound;
use App\Models\Survey;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Psychosocial\ReviewRoundService;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\SurveyPublicEntry;
use App\Support\Nr1GroupPrivacy;
use App\Support\Nr1Shell\Nr1Shell;
use App\Support\Nr1Shell\Nr1ShellContext;
use App\Support\Nr1Shell\Nr1ShellLegacy;
use App\Support\Nr1Shell\Nr1ShellPageData;
use App\Support\Nr1Shell\Nr1ShellStageD;
use App\Support\SurveyPublicationGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shell NR-1 (/nr1): navegação e apresentação próprias. Não recalcula nada: lê os mesmos serviços das telas antigas
 * (PsychosocialDashboardBuilder, gate de publicação, matriz de permissões) e as telas antigas seguem valendo por URL.
 */
class ShellController extends Controller
{
    private const PROFILE_LABELS = [
        Nr1Shell::PROFILE_TALENTS => 'Talents · Consultoria',
        Nr1Shell::PROFILE_EMPRESA => 'Empresa · Gestor',
        Nr1Shell::PROFILE_PASSEG => 'PASSEG · Revisão',
        Nr1Shell::PROFILE_COMERCIAL => 'Talents · Comercial',
        Nr1Shell::PROFILE_COLABORADOR => 'Colaborador',
    ];

    /** Seções que só existem na V11 (o legado concentra o plano no parecer). */
    private const NOT_IN_LEGACY = ['plano-de-acao', 'solucoes'];

    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();
        $section = $user ? Nr1Shell::landingSection($user) : null;
        abort_if($section === null, 403);

        return redirect()->route('nr1.'.$section);
    }

    public function show(Request $request, string $section, Nr1ShellPageData $pages): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = Nr1Shell::profileFor($user);
        abort_unless($profile !== null && Nr1Shell::allows($user, $section, $profile), 403);

        // Revisor externo: o ambiente /revisao já tem o MFA e a regra de compartilhamento por versão.
        if ($profile === Nr1Shell::PROFILE_PASSEG) {
            return redirect()->route('external-review.index');
        }

        $context = Nr1ShellContext::resolve($request, $user, $profile);

        // Campanha do modelo anterior: Soluções e o plano por medidas são da V11 (o plano dela vive no parecer, em Documentos).
        // O que não se aplica ao legado não aparece no menu; a URL direta leva à seção equivalente.
        $legacy = $context->survey !== null && Nr1ShellLegacy::isLegacy($context->survey);
        if ($legacy && in_array($section, self::NOT_IN_LEGACY, true)) {
            $target = collect([$section === 'plano-de-acao' ? 'documentos' : 'visao-geral', 'visao-geral', 'campanha', 'resultados', 'documentos'])
                ->first(fn (string $key) => Nr1Shell::allows($user, $key, $profile));
            abort_if($target === null, 403);

            return redirect()->route('nr1.'.$target, $request->query());
        }

        return Inertia::render('Nr1/Section', [
            'shell' => [
                'profile' => $profile,
                'profileLabel' => self::PROFILE_LABELS[$profile],
                'menu' => array_values(array_map(
                    fn (array $item) => $item + ['href' => route('nr1.'.$item['key'])],
                    array_filter(Nr1Shell::sectionsFor($user), fn (array $item) => ! $legacy || ! in_array($item['key'], self::NOT_IN_LEGACY, true)),
                )),
                'current' => $section,
                'backUrl' => match (true) {
                    $profile === Nr1Shell::PROFILE_EMPRESA => route('client.dashboard'),
                    $profile === Nr1Shell::PROFILE_TALENTS && $context->company !== null => route('admin.companies.show', $context->company),
                    default => route('admin.dashboard'),
                },
                'backLabel' => match (true) {
                    $profile === Nr1Shell::PROFILE_EMPRESA, $profile === Nr1Shell::PROFILE_TALENTS && $context->company !== null => '← Voltar à empresa',
                    default => '← Voltar ao Talents',
                },
            ],
            'context' => $this->contextPayload($context, $profile),
            'section' => [
                'key' => $section,
                'label' => Nr1Shell::SECTIONS[$section][0],
                'kpis' => $context->survey && $this->readsResults($section) && ! Nr1ShellLegacy::isLegacy($context->survey) ? $this->kpis($context->survey, $profile) : [],
                'links' => $this->links($profile, $section, $context),
            ] + $this->pageProps($request, $pages, $user, $profile, $section, $context),
        ]);
    }

    /**
     * Dados das telas já fiéis ao HTML (Visão geral e Campanha NR-1). As demais seções seguem com o resumo da Etapa A.
     *
     * @return array<string, mixed>
     */
    private function pageProps(Request $request, Nr1ShellPageData $pages, User $user, string $profile, string $section, Nr1ShellContext $context): array
    {
        $survey = $context->survey;
        $stageD = app(Nr1ShellStageD::class);
        // Oportunidades é global (fila comercial): não depende da campanha em foco nem do modelo dela.
        if ($section === 'oportunidades') {
            return ['opportunities' => $stageD->opportunities($request)];
        }
        // Comercial (sem campanha em foco): só os cartões de serviço.
        if ($section === 'solucoes' && $survey === null && $profile === Nr1Shell::PROFILE_COMERCIAL) {
            return ['solutions' => $stageD->solutionsWithoutSurvey()];
        }
        // Campanha do modelo anterior: o legado integrado (mesmas props e regras das telas antigas) ou só o aviso.
        if ($survey !== null && Nr1ShellLegacy::isLegacy($survey)) {
            return ['legacy' => app(Nr1ShellLegacy::class)->section($request, $user, $profile, $section, $survey, $context->company)];
        }

        if ($survey === null || ! in_array($section, ['visao-geral', 'campanha', 'resultados', 'plano-de-acao', 'documentos', 'solucoes'], true)) {
            return [];
        }

        $respondents = SurveyResponse::query()->where('survey_id', $survey->id)->whereNotNull('completed_at')->count();
        $hide = $this->sampleHidden($survey, $respondents, $profile);

        return match ($section) {
            'visao-geral' => ['overview' => $pages->overview($survey, $hide, $this->insufficientMessage($survey))],
            'campanha' => ['campaign' => $pages->campaign($survey, $user, $profile === Nr1Shell::PROFILE_TALENTS, $hide)],
            'resultados' => ['results' => $pages->results($survey, $hide, $this->insufficientMessage($survey), $context->company, $profile === Nr1Shell::PROFILE_TALENTS)],
            'plano-de-acao' => ['plan' => $pages->plan($survey, $user, $profile === Nr1Shell::PROFILE_TALENTS)],
            'documentos' => ['documents' => $stageD->documents($request, $user, $profile, $survey, $context->company)],
            'solucoes' => ['solutions' => $stageD->solutions($request, $profile, $survey)],
        };
    }

    /** Colaborador: entra pelo link da campanha (sem login) e vê só Campanha NR-1 e Minha jornada. */
    public function jornada(Request $request, string $token, Nr1ShellPageData $pages, string $section = 'minha-jornada'): Response
    {
        // Mesma regra de abertura da rota antiga survey.public (SurveyPublicEntry): token inexistente = 404; campanha
        // inativa, não iniciada ou encerrada = a mesma tela «Closed», sem expor título nem empresa.
        $entry = SurveyPublicEntry::resolve($token);
        abort_if($entry === null, 404);

        $survey = $entry['survey'];
        abort_unless((int) $survey->method_version_id === (int) SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1), 404);

        $closedReason = SurveyPublicEntry::closureReason($survey, $entry['link']);
        if ($closedReason !== null) {
            return Inertia::render('Survey/Closed', [
                'message' => match ($closedReason) {
                    'inactive' => 'Esta pesquisa não está ativa no momento.',
                    'not_started' => 'Esta pesquisa ainda não iniciou.',
                    'ended' => 'Esta pesquisa já foi encerrada.',
                    default => 'Esta pesquisa não está ativa no momento.',
                },
            ]);
        }

        $menu = [];
        foreach (Nr1Shell::MATRIX[Nr1Shell::PROFILE_COLABORADOR] as $key) {
            $menu[] = ['key' => $key, 'label' => Nr1Shell::SECTIONS[$key][0], 'icon' => Nr1Shell::SECTIONS[$key][1], 'href' => route('nr1.jornada', [$token, $key])];
        }

        return Inertia::render('Nr1/Section', [
            'shell' => [
                'profile' => Nr1Shell::PROFILE_COLABORADOR,
                'profileLabel' => self::PROFILE_LABELS[Nr1Shell::PROFILE_COLABORADOR],
                'menu' => $menu,
                'current' => $section,
                'backUrl' => null,
            ],
            'context' => [
                'company' => null,
                'survey' => ['id' => $survey->id, 'title' => $survey->title, 'year' => ($survey->starts_at ?? $survey->created_at)?->year, 'respondents' => null, 'statusLabel' => null, 'published' => true],
                'companies' => [],
                'surveys' => [],
                'canPickCompany' => false,
                'awaitingPublication' => false,
                'collaborator' => true,
            ],
            'section' => [
                'key' => $section,
                'label' => Nr1Shell::SECTIONS[$section][0],
                'kpis' => [],
                'links' => [['label' => 'Responder o questionário', 'href' => route('survey.public', $token)]],
                'journey' => $pages->journey($survey, $entry['link'], $token),
            ],
        ]);
    }

    private function readsResults(string $section): bool
    {
        return in_array($section, ['visao-geral', 'resultados'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function contextPayload(Nr1ShellContext $context, string $profile): array
    {
        $survey = $context->survey;
        $respondents = $survey
            ? SurveyResponse::query()->where('survey_id', $survey->id)->whereNotNull('completed_at')->count()
            : null;
        $insufficient = $survey !== null && $this->sampleHidden($survey, (int) $respondents, $profile);

        return [
            'company' => $context->company ? ['id' => $context->company->id, 'name' => $context->company->name, 'logo_url' => $context->company->logo_url] : null,
            'survey' => $survey ? [
                'id' => $survey->id,
                'title' => $survey->title,
                'year' => ($survey->starts_at ?? $survey->created_at)?->year,
                'respondents' => $insufficient ? null : $respondents,
                'insufficientSample' => $insufficient,
                'insufficientMessage' => $insufficient ? $this->insufficientMessage($survey) : null,
                'published' => SurveyPublicationGate::allows($survey),
                'statusLabel' => $this->statusLabel($survey),
                'statusBadge' => $this->statusBadge($survey),
            ] : null,
            'companies' => $context->companies->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
            'surveys' => $context->surveys->map(fn (Survey $s) => ['id' => $s->id, 'title' => $s->title, 'year' => ($s->starts_at ?? $s->created_at)?->year, 'legacy' => Nr1ShellLegacy::isLegacy($s)])->values()->all(),
            'canPickCompany' => $context->canPickCompany,
            'awaitingPublication' => $context->awaitingPublication,
            'collaborator' => false,
        ];
    }

    /**
     * Empresa (e colaborador) não veem número nem percentual de participação abaixo do grupo mínimo da campanha;
     * Talents mantém o comportamento das telas antigas.
     */
    private function sampleHidden(Survey $survey, int $respondents, string $profile): bool
    {
        return $profile !== Nr1Shell::PROFILE_TALENTS && $respondents < Nr1GroupPrivacy::minFor($survey);
    }

    private function insufficientMessage(Survey $survey): string
    {
        return 'Amostra insuficiente: com menos de '.Nr1GroupPrivacy::minFor($survey).' respostas, o número e a participação não são exibidos.';
    }

    /**
     * Estado do cabeçalho com tom: Publicado (verde), Em revisão (âmbar), Em edição (cinza). Campanha sem publicação:
     * Ativa (verde) ou Encerrada (cinza). Só leitura: nada aqui muda o fluxo de revisão.
     *
     * @return array{label: string, tone: 'ok'|'warn'|'gray'}
     */
    private function statusBadge(Survey $survey): array
    {
        if (! $survey->requires_publication) {
            return $survey->status === 'closed' ? ['label' => 'Encerrada', 'tone' => 'gray'] : ['label' => 'Ativa', 'tone' => 'ok'];
        }
        if ($survey->published_at !== null) {
            return ['label' => 'Publicado', 'tone' => 'ok'];
        }

        $round = $survey->usesReviewFlow() ? app(ReviewRoundService::class)->currentRound($survey) : null;
        $inReview = $round !== null && in_array($round->status, [PsychosocialReviewRound::STATUS_IN_REVIEW, PsychosocialReviewRound::STATUS_CHANGES_REQUESTED, PsychosocialReviewRound::STATUS_APPROVED], true);

        return $inReview ? ['label' => 'Em revisão', 'tone' => 'warn'] : ['label' => 'Em edição', 'tone' => 'gray'];
    }

    private function statusLabel(Survey $survey): string
    {
        if (! $survey->requires_publication) {
            return $survey->status === 'closed' ? 'Campanha encerrada' : 'Campanha ativa';
        }

        return $survey->published_at !== null ? 'Publicado' : 'Aguardando publicação';
    }

    /**
     * Números reais do painel (os mesmos das telas antigas); a empresa só chega aqui com a campanha já publicada.
     *
     * @return list<array{label: string, value: string, hint: string|null}>
     */
    private function kpis(Survey $survey, string $profile): array
    {
        $cards = app(PsychosocialDashboardBuilder::class)->build($survey)['cards'] ?? [];
        if (($cards['overall'] ?? null) === null) {
            return [];
        }

        $hidden = $this->sampleHidden($survey, (int) ($cards['participation']['respondents'] ?? 0), $profile);
        $participation = $hidden
            ? ['label' => 'Participação', 'value' => '—', 'hint' => $this->insufficientMessage($survey)]
            : ['label' => 'Participação', 'value' => (string) $cards['participation']['percent_label'], 'hint' => $cards['participation']['respondents'].' respostas · '.($cards['participation']['eligible'] ?? '—').' elegíveis'];

        return [
            ['label' => 'Índice geral da pesquisa', 'value' => $cards['overall']['score_label'].' / 5', 'hint' => $cards['overall']['band_label']],
            $participation,
            ['label' => 'Dimensões em atenção', 'value' => $cards['attention']['count'].' / '.$cards['attention']['total'], 'hint' => null],
            ['label' => 'Plano de ação', 'value' => $cards['plan']['confirmed'].' / '.$cards['plan']['total'], 'hint' => 'Medidas com responsável confirmado'],
        ];
    }

    /**
     * Telas atuais de cada seção (as rotas antigas seguem valendo): o shell encaixa o contexto e leva até elas.
     *
     * @return list<array{label: string, href: string}>
     */
    private function links(string $profile, string $section, Nr1ShellContext $context): array
    {
        $company = $context->company;
        $survey = $context->survey;
        $links = [];

        if ($profile === Nr1Shell::PROFILE_TALENTS && $company && $survey) {
            $args = [$company->id, $survey->id];
            $links = match ($section) {
                'visao-geral', 'resultados' => [['Abrir resultados (com Revisão interna)', route('admin.companies.surveys.results.show', $args)]],
                'campanha' => [['Abrir a campanha NR-1', route('admin.companies.surveys.campaign.show', $args)]],
                'plano-de-acao' => [['Abrir o plano de ação', route('admin.companies.surveys.measures.edit', $args)]],
                'documentos' => [['Abrir a central de documentos', route('admin.companies.surveys.documents.index', $args)]],
                default => [],
            };
        }

        if ($profile === Nr1Shell::PROFILE_EMPRESA && $survey) {
            $links = match ($section) {
                'visao-geral', 'resultados' => [['Abrir o painel psicossocial', route('client.surveys.psychosocial-dashboard', $survey)]],
                'campanha' => [['Abrir a campanha', route('client.surveys.show', $survey)]],
                'plano-de-acao' => [['Abrir o plano de ação', route('client.surveys.measures.index', $survey)]],
                'documentos' => [['Abrir os documentos', route('client.surveys.documents.index', $survey)]],
                'solucoes' => [['Abrir as Soluções Talents', route('client.surveys.solutions', $survey)]],
                default => [],
            };
        }

        if (in_array($profile, [Nr1Shell::PROFILE_TALENTS, Nr1Shell::PROFILE_COMERCIAL], true)) {
            $links = array_merge($links, match ($section) {
                'solucoes' => [['Abrir as regras das Soluções', route('admin.service-matches.index')]],
                'oportunidades' => [['Abrir a fila de oportunidades', route('admin.nr1-opportunities.index')]],
                default => [],
            });
        }

        return array_map(fn (array $link) => ['label' => $link[0], 'href' => $link[1]], $links);
    }
}
