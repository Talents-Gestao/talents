<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Http\Controllers\Admin\Nr1OpportunityController;
use App\Http\Controllers\Admin\ReviewRoundAdminController;
use App\Http\Controllers\Client\DocumentsController;
use App\Http\Controllers\Client\SolutionsController;
use App\Models\Company;
use App\Models\Nr1Opportunity;
use App\Models\Nr1OpportunityEvent;
use App\Models\Survey;
use App\Models\User;
use App\Services\Nr1\Nr1OpportunityService;
use App\Services\Nr1\Nr1SolutionService;
use App\Support\Nr1ServiceCards;
use Illuminate\Http\Request;

/**
 * Etapa D (V11): Documentos, Soluções Talents e Oportunidades no shell. Só apresentação e navegação: as props vêm dos
 * MESMOS controllers das telas atuais (acesso, publicação e regras idênticos) e as ações postam para as rotas que já
 * existem (inclusive o step-up de MFA de «Publicar»). Nenhuma regra de negócio nova.
 */
final class Nr1ShellStageD
{
    public function __construct(private readonly Nr1SolutionService $solutions) {}

    /**
     * Central de documentos da V11. Talents: rodada atual, publicada, histórico, pendências e ações. Empresa: só a
     * rodada PUBLICADA, ciência e confirmação da integração ao PGR.
     *
     * @return array<string, mixed>
     */
    public function documents(Request $request, User $user, string $profile, Survey $survey, ?Company $company): array
    {
        if ($profile === Nr1Shell::PROFILE_TALENTS && $company !== null) {
            $props = Nr1ShellLegacy::capture(fn () => app(ReviewRoundAdminController::class)->index($company, $survey), $request);
            $base = [$company->id, $survey->id];
            $round = $props['round'];

            return [
                'mode' => 'talents',
                'requiresPassegReview' => $props['requiresPassegReview'],
                'externalReviewEnabled' => $props['externalReviewEnabled'],
                'canChangeReviewRequirement' => $props['canChangeReviewRequirement'],
                'requirementChanges' => $props['requirementChanges'],
                'canStart' => $props['canStart'],
                'reviewers' => $props['reviewers'],
                'approvalMeaning' => $props['approvalMeaning'],
                'round' => $round,
                'publishedRound' => $props['publishedRound'],
                'urls' => [
                    'start' => route('admin.companies.surveys.documents.start', $base),
                    'reviewRequirement' => route('admin.companies.surveys.documents.review-requirement', $base),
                    'regenerate' => $round ? route('admin.companies.surveys.documents.regenerate', [...$base, $round['id']]) : null,
                    'send' => $round ? route('admin.companies.surveys.documents.send', [...$base, $round['id']]) : null,
                    'publish' => $round ? route('admin.companies.surveys.documents.publish', [...$base, $round['id']]) : null,
                    'registration' => route('admin.companies.surveys.registration.edit', $base),
                    'full' => route('admin.companies.surveys.documents.index', $base),
                ],
            ];
        }

        $props = Nr1ShellLegacy::capture(fn () => app(DocumentsController::class)->index($request, $survey), $request);

        return [
            'mode' => 'company',
            'round' => $props['round'],
            'canRecord' => $props['canRecord'],
            'approvalMeaning' => $props['approvalMeaning'],
            'urls' => [
                'acknowledge' => route('client.surveys.documents.acknowledge', $survey->id),
                'pgrIntegration' => route('client.surveys.documents.pgr-integration', $survey->id),
            ],
        ];
    }

    /**
     * Soluções Talents de uma campanha: três cartões e recomendações das regras (a empresa só recebe regras ativas e
     * revisadas, e só depois de «Publicado»).
     *
     * @return array<string, mixed>
     */
    public function solutions(Request $request, string $profile, Survey $survey): array
    {
        if ($profile === Nr1Shell::PROFILE_EMPRESA) {
            $props = Nr1ShellLegacy::capture(fn () => app(SolutionsController::class)->show($request, $survey), $request);

            return [
                'mode' => 'company',
                'cards' => $props['cards'],
                'recommendations' => $props['recommendations'],
                'requests' => $props['requests'],
                'canRequest' => $props['canRequest'],
                'locked' => false,
                'requestUrlBase' => route('client.surveys.solutions.request', [$survey->id, '__MATCH__']),
            ];
        }

        return [
            'mode' => 'staff',
            'cards' => Nr1ServiceCards::all(),
            'recommendations' => $this->solutions->recommendations($survey, forCompany: false),
            'requests' => [],
            'canRequest' => false,
            // Antes da publicação não há recomendações: a mensagem do HTML.
            'locked' => $survey->requires_publication && $survey->published_at === null,
            'rulesUrl' => route('admin.service-matches.index'),
        ];
    }

    /**
     * Soluções sem campanha em foco (perfil Comercial): só os três cartões e o atalho das regras.
     *
     * @return array<string, mixed>
     */
    public function solutionsWithoutSurvey(): array
    {
        return [
            'mode' => 'staff',
            'cards' => Nr1ServiceCards::all(),
            'recommendations' => [],
            'requests' => [],
            'canRequest' => false,
            'locked' => false,
            'noSurvey' => true,
            'rulesUrl' => route('admin.service-matches.index'),
        ];
    }

    /**
     * Fila comercial: as props do controller atual (contadores, filtros, fila) mais as transições permitidas e os
     * últimos eventos de cada oportunidade. As ações usam as rotas de assumir, mudar de estado e próxima ação.
     *
     * @return array<string, mixed>
     */
    public function opportunities(Request $request): array
    {
        $props = app(Nr1OpportunityController::class)->queuePage($request);
        $queue = collect($props['queue']);
        $ids = $queue->pluck('id')->all();

        $events = Nr1OpportunityEvent::query()->whereIn('opportunity_id', $ids)->with('user:id,name')->orderByDesc('id')->get()->groupBy('opportunity_id');
        $labels = Nr1Opportunity::STATUS_LABELS;

        $rows = $queue->map(function (array $row) use ($events, $labels): array {
            return $row + [
                'transitions' => collect(Nr1OpportunityService::TRANSITIONS[$row['status']] ?? [])->map(fn (string $to) => ['value' => $to, 'label' => $labels[$to] ?? $to])->values()->all(),
                'history' => ($events->get($row['id']) ?? collect())->take(3)->map(fn (Nr1OpportunityEvent $event) => [
                    'body' => $event->body,
                    'author' => $event->user?->name ?? 'Sistema',
                    'at' => $event->created_at?->format('d/m/Y H:i'),
                ])->values()->all(),
                'urls' => [
                    'assume' => route('admin.nr1-opportunities.assume', $row['id']),
                    'status' => route('admin.nr1-opportunities.status', $row['id']),
                    'nextAction' => route('admin.nr1-opportunities.next-action', $row['id']),
                    'detail' => route('admin.nr1-opportunities.opportunity', $row['id']),
                ],
            ];
        })->all();

        return [
            'counters' => $props['counters'],
            'pagination' => $props['pagination'],
            'stageCounts' => $props['stageCounts'],
            'filters' => $props['filters'],
            'originLabels' => $props['originLabels'],
            'statusLabels' => $props['statusLabels'],
            'canWork' => $props['canWork'],
            'queue' => $rows,
            'stages' => array_map(fn (string $key) => ['key' => $key, 'label' => $labels[$key] ?? $key], array_keys($labels)),
            'full' => route('admin.nr1-opportunities.index'),
        ];
    }
}
