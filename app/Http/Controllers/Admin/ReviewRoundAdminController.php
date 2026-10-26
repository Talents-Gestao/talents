<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PsychosocialDocumentVersion;
use App\Models\PsychosocialPassegOptionChange;
use App\Models\PsychosocialReviewRound;
use App\Models\Survey;
use App\Models\User;
use App\Services\Psychosocial\DocumentShareService;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Services\Psychosocial\ReviewRoundService;
use App\Support\ReviewRoundPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Central de documentos da Talents (campanha V11): gera os dois documentos, envia à PASSEG, publica.
 * O revisor externo age em /revisao; a empresa recebe em /client depois de "Publicado".
 */
class ReviewRoundAdminController extends Controller
{
    public function __construct(
        private readonly ReviewRoundService $rounds,
        private readonly PsychosocialDocumentService $documents,
    ) {}

    public function index(Company $company, Survey $survey): Response
    {
        $this->authorizeSurvey($company, $survey);

        $current = $this->rounds->currentRound($survey);
        $published = $this->rounds->publishedRound($survey);

        return Inertia::render('Admin/Documents/Central', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'published_at' => $survey->published_at?->format('d/m/Y H:i'),
            ],
            'campaignUrl' => route('admin.companies.surveys.campaign.show', [$company->id, $survey->id]),
            'resultsUrl' => route('admin.companies.surveys.results.show', [$company->id, $survey->id]),
            'externalReviewEnabled' => DocumentShareService::enabled(),
            'requiresPassegReview' => (bool) $survey->requires_passeg_review,
            'requirementChanges' => PsychosocialPassegOptionChange::query()->where('survey_id', $survey->id)->with('user:id,name')->orderByDesc('id')->get()
                ->map(fn (PsychosocialPassegOptionChange $change) => [
                    'id' => $change->id,
                    'by' => $change->user?->name ?? 'Sistema',
                    'at' => $change->changed_at->format('d/m/Y H:i'),
                    'previous' => $change->previous_value,
                    'new' => $change->new_value,
                ])->all(),
            'canChangeReviewRequirement' => $current === null || in_array($current->status, [PsychosocialReviewRound::STATUS_DRAFT, PsychosocialReviewRound::STATUS_PUBLISHED], true),
            'round' => $current ? $this->present($company, $survey, $current, withActions: true) : null,
            // A empresa continua vendo a última rodada publicada enquanto uma nova revisão está em andamento.
            'publishedRound' => $published !== null && $published->id !== $current?->id
                ? $this->present($company, $survey, $published, withActions: false)
                : null,
            'canStart' => $current === null || $current->isPublished(),
            'reviewers' => $this->reviewers(),
            'approvalMeaning' => ReviewRoundService::APPROVAL_MEANING,
            'statusLabels' => PsychosocialReviewRound::STATUS_LABELS,
        ]);
    }

    public function start(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->authorizeSurvey($company, $survey);

        return $this->act($company, $survey, 'Revisão iniciada. Gere os documentos para começar.', fn () => $this->rounds->start($survey, $request->user()));
    }

    public function updateReviewRequirement(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->authorizeSurvey($company, $survey);

        $data = $request->validate(['requires_passeg_review' => ['required', 'boolean']]);
        $required = (bool) $data['requires_passeg_review'];

        return $this->act(
            $company,
            $survey,
            $required ? 'Revisão PASSEG exigida nesta campanha.' : 'Revisão PASSEG não exigida: a Talents publica diretamente.',
            fn () => $this->rounds->setPassegReviewRequired($survey, $request->user(), $required),
        );
    }

    public function regenerate(Request $request, Company $company, Survey $survey, PsychosocialReviewRound $round): RedirectResponse
    {
        $this->authorizeRound($company, $survey, $round);

        return $this->act($company, $survey, 'Documentos gerados: nova versão congelada, com hash.', fn () => $this->rounds->regenerate($round, $request->user()));
    }

    public function send(Request $request, Company $company, Survey $survey, PsychosocialReviewRound $round): RedirectResponse
    {
        $this->authorizeRound($company, $survey, $round);

        $data = $request->validate([
            'reviewer_id' => ['required', 'integer', 'exists:users,id'],
            'share_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        return $this->act($company, $survey, 'Enviado à revisão da PASSEG.', fn () => $this->rounds->send(
            $round,
            $request->user(),
            User::query()->findOrFail($data['reviewer_id']),
            (int) ($data['share_days'] ?? 30),
        ));
    }

    public function publish(Request $request, Company $company, Survey $survey, PsychosocialReviewRound $round): RedirectResponse
    {
        $this->authorizeRound($company, $survey, $round);

        return $this->act($company, $survey, 'Documentos publicados para a empresa.', fn () => $this->rounds->publish($round, $request->user()));
    }

    public function download(Request $request, Company $company, Survey $survey, PsychosocialDocumentVersion $version): SymfonyResponse
    {
        $this->authorizeSurvey($company, $survey);
        abort_unless((int) $version->document->survey_id === (int) $survey->id, 404);

        try {
            return $this->documents->download($version, $request->user(), $request->ip());
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    private function act(Company $company, Survey $survey, string $success, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (PsychosocialDocumentException $exception) {
            return $this->back($company, $survey)->with('error', $exception->getMessage());
        }

        return $this->back($company, $survey)->with('success', $success);
    }

    private function back(Company $company, Survey $survey): RedirectResponse
    {
        return redirect()->route('admin.companies.surveys.documents.index', [$company->id, $survey->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Company $company, Survey $survey, PsychosocialReviewRound $round, bool $withActions): array
    {
        $round->loadMissing(['executiveVersion.document', 'technicalVersion.document']);
        $editable = in_array($round->status, [PsychosocialReviewRound::STATUS_DRAFT, PsychosocialReviewRound::STATUS_CHANGES_REQUESTED], true);

        return array_merge(ReviewRoundPresenter::base($round), [
            'versions' => ReviewRoundPresenter::versions(
                $round,
                fn (PsychosocialDocumentVersion $version) => route('admin.companies.surveys.documents.download', [$company->id, $survey->id, $version->id]),
            ),
            'pending' => $withActions && ! $round->isPublished() ? $this->rounds->pending($round) : [],
            'history' => $this->rounds->history($round),
            'actions' => $withActions ? [
                'regenerate' => $editable,
                'send' => $editable && $survey->requires_passeg_review,
                'publish' => $round->status === PsychosocialReviewRound::STATUS_APPROVED
                    || ($round->status === PsychosocialReviewRound::STATUS_DRAFT && ! $survey->requires_passeg_review),
            ] : ['regenerate' => false, 'send' => false, 'publish' => false],
        ]);
    }

    /**
     * @return list<array{id: int, name: string, organization: ?string}>
     */
    private function reviewers(): array
    {
        return User::query()
            ->whereHas('workspaces', fn ($q) => $q->where('role', UserRole::ExternalReviewer)->where('is_active', true))
            ->with(['workspaces' => fn ($q) => $q->where('role', UserRole::ExternalReviewer)->with('approverOrganization:id,name')])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'organization' => $user->workspaces->first()?->approverOrganization?->name,
            ])
            ->all();
    }

    private function authorizeSurvey(Company $company, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
        abort_unless($survey->usesReviewFlow(), 404);
    }

    private function authorizeRound(Company $company, Survey $survey, PsychosocialReviewRound $round): void
    {
        $this->authorizeSurvey($company, $survey);
        abort_unless((int) $round->survey_id === (int) $survey->id, 404);
    }
}
