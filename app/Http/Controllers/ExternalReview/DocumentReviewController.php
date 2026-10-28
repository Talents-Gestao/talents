<?php

declare(strict_types=1);

namespace App\Http\Controllers\ExternalReview;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\DocumentShare;
use App\Models\DocumentShareReview;
use App\Models\PsychosocialDocumentVersion;
use App\Models\PsychosocialReviewRound;
use App\Policies\PsychosocialDocumentVersionPolicy;
use App\Services\Psychosocial\DocumentShareService;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Services\Psychosocial\ReviewRoundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Revisor externo: só as versões compartilhadas com ele, dentro da validade. O recurso é sempre
 * buscado pelo compartilhamento do revisor; versão alheia, vencida ou revogada responde 404.
 */
class DocumentReviewController extends Controller
{
    public function __construct(
        private readonly DocumentShareService $shares,
        private readonly PsychosocialDocumentService $documents,
        private readonly ReviewRoundService $rounds,
    ) {}

    public function index(Request $request): Response
    {
        $shares = DocumentShare::query()
            ->active()
            ->where('reviewer_id', $request->user()->id)
            ->with(['version.document', 'company:id,name'])
            ->orderByDesc('shared_at')
            ->get()
            ->map(fn (DocumentShare $share) => $this->present($share));

        return Inertia::render('ExternalReview/Index', ['shares' => $shares]);
    }

    public function show(Request $request, int $version): Response
    {
        [$model, $share] = $this->resolve($request, $version, 'view');
        $share->load(['version.document', 'company:id,name', 'reviews.user:id,name']);

        return Inertia::render('ExternalReview/Show', [
            'share' => array_merge($this->present($share), [
                'reviews' => $share->reviews->map(fn (DocumentShareReview $review) => [
                    'id' => $review->id,
                    'kind' => $review->kind,
                    'body' => $review->body,
                    'author' => $review->user?->name,
                    'created_at' => $review->created_at?->format('d/m/Y H:i'),
                ])->values(),
                'approved' => $share->reviews->contains('kind', DocumentShareReview::KIND_APPROVAL),
                'round' => $this->roundPayload($model),
            ]),
        ]);
    }

    public function download(Request $request, int $version): SymfonyResponse
    {
        [$model] = $this->resolve($request, $version, 'view');

        try {
            return $this->documents->download($model, $request->user(), $request->ip());
        } catch (PsychosocialDocumentException $exception) {
            return response($exception->getMessage(), SymfonyResponse::HTTP_CONFLICT, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
    }

    public function comment(Request $request, int $version): RedirectResponse
    {
        [, $share] = $this->resolve($request, $version, 'comment');

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->shares->comment($share, $request->user(), $data['body']);

        return back()->with('success', 'Comentário registrado.');
    }

    public function approve(Request $request, int $version): RedirectResponse
    {
        [$model, $share] = $this->resolve($request, $version, 'approve');
        $round = $this->roundOf($model);

        try {
            // Versão de uma rodada de revisão: a aprovação é da rodada (os dois documentos). Fora dela, vale o registro simples.
            $round !== null
                ? $this->rounds->approve($round, $request->user())
                : $this->shares->approve($share, $request->user());
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $round !== null ? 'Relatório aprovado.' : 'Versão aprovada.');
    }

    public function requestChanges(Request $request, int $version): RedirectResponse
    {
        [$model] = $this->resolve($request, $version, 'comment');
        $round = $this->roundOf($model);
        abort_if($round === null, SymfonyResponse::HTTP_NOT_FOUND);

        $data = $request->validate(
            ['body' => ['required', 'string', 'min:'.ReviewRoundService::MIN_CHANGE_REQUEST_LENGTH, 'max:5000']],
            ['body.required' => 'Descreva a alteração solicitada.', 'body.min' => 'Descreva a alteração solicitada (mínimo de '.ReviewRoundService::MIN_CHANGE_REQUEST_LENGTH.' caracteres).'],
        );

        try {
            $this->rounds->requestChanges($round, $request->user(), $data['body']);
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Alteração solicitada: a revisão voltou para a Talents.');
    }

    private function roundOf(PsychosocialDocumentVersion $version): ?PsychosocialReviewRound
    {
        return PsychosocialReviewRound::query()
            ->where(fn ($q) => $q->where('executive_version_id', $version->id)->orWhere('technical_version_id', $version->id))
            ->orderByDesc('round_number')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function roundPayload(PsychosocialDocumentVersion $version): ?array
    {
        $round = $this->roundOf($version);
        if ($round === null) {
            return null;
        }

        $round->loadMissing(['executiveVersion.document', 'technicalVersion.document']);

        return [
            'number' => $round->round_number,
            'status' => $round->status,
            'status_label' => $round->statusLabel(),
            'can_decide' => $round->status === PsychosocialReviewRound::STATUS_IN_REVIEW,
            'approval_meaning' => ReviewRoundService::APPROVAL_MEANING,
            'documents' => collect([$round->executiveVersion, $round->technicalVersion])
                ->filter()
                ->map(fn (PsychosocialDocumentVersion $item) => [
                    'version_id' => $item->id,
                    'label' => PsychosocialDocumentService::label($item->document->type),
                    'public_identifier' => $item->public_identifier,
                    'url' => DocumentShare::query()->active()->where('reviewer_id', request()->user()->id)->where('document_version_id', $item->id)->exists()
                        ? route('external-review.show', $item->id)
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{0: PsychosocialDocumentVersion, 1: DocumentShare}
     */
    private function resolve(Request $request, int $versionId, string $ability): array
    {
        $version = PsychosocialDocumentVersion::query()->find($versionId);
        abort_if($version === null, SymfonyResponse::HTTP_NOT_FOUND);

        Gate::authorize($ability, $version);

        $share = app(PsychosocialDocumentVersionPolicy::class)->activeShare($request->user(), $version);
        abort_if($share === null, SymfonyResponse::HTTP_NOT_FOUND);

        return [$version, $share];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DocumentShare $share): array
    {
        $version = $share->version;

        return [
            'version_id' => $version->id,
            'public_identifier' => $version->public_identifier,
            'version_number' => $version->version_number,
            'kind' => $version->kind,
            'seal' => $version->seal,
            'document_label' => PsychosocialDocumentService::label($version->document->type),
            'company_name' => $share->company?->name,
            'shared_at' => $share->shared_at->format('d/m/Y'),
            'expires_at' => $share->expires_at->format('d/m/Y'),
            'pdf_ready' => $version->pdf_status === PsychosocialDocumentVersion::PDF_READY,
        ];
    }
}
