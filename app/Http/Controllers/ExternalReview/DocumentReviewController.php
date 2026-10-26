<?php

declare(strict_types=1);

namespace App\Http\Controllers\ExternalReview;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\DocumentShare;
use App\Models\DocumentShareReview;
use App\Models\PsychosocialDocumentVersion;
use App\Policies\PsychosocialDocumentVersionPolicy;
use App\Services\Psychosocial\DocumentShareService;
use App\Services\Psychosocial\PsychosocialDocumentService;
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
        [, $share] = $this->resolve($request, $version, 'approve');

        try {
            $this->shares->approve($share, $request->user());
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Versão aprovada.');
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
