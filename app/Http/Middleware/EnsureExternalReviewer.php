<?php

namespace App\Http\Middleware;

use App\Services\Psychosocial\DocumentShareService;
use App\Support\WorkspaceManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Área do revisor externo. Recurso desligado → 404 (a área não existe). Perfil errado → 403.
 * O revisor nunca herda nada de empresa nem de Talents: o ambiente dele é de tipo próprio.
 */
class EnsureExternalReviewer
{
    public function __construct(
        private WorkspaceManager $workspaceManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(DocumentShareService::enabled(), Response::HTTP_NOT_FOUND);

        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $workspace = $this->workspaceManager->resolveActiveWorkspace($user, $request);

        if (! $workspace || ! $workspace->isExternalReview() || ! $workspace->isExternalReviewer()
            || ! $workspace->approverOrganization?->is_active) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // MFA (D14): com `nr1.external_review.mfa_required`, o revisor só entra com MFA verificado nesta sessão.
        if (config('nr1.external_review.mfa_required')) {
            return app(EnsureMfa::class)->handle($request, $next, 'login');
        }

        return $next($request);
    }
}
