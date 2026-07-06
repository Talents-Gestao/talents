<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Support\WorkspaceManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAdmin
{
    public function __construct(
        private WorkspaceManager $workspaceManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $workspace = $this->workspaceManager->resolveActiveWorkspace($user, $request);

        if (! $workspace) {
            if ($this->workspaceManager->activeWorkspacesFor($user)->isEmpty()) {
                abort(Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('workspaces.select');
        }

        if (
            $workspace->workspace_type !== WorkspaceType::Company
            || $workspace->role !== UserRole::CompanyAdmin
        ) {
            abort(Response::HTTP_FORBIDDEN, 'Apenas administradores da empresa podem acessar esta area.');
        }

        return $next($request);
    }
}
