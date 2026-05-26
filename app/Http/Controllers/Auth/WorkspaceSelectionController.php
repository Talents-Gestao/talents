<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserWorkspace;
use App\Support\WorkspaceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceSelectionController extends Controller
{
    public function __construct(
        private WorkspaceManager $workspaceManager,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $workspaces = $this->workspaceManager->activeWorkspacesFor($user);

        if ($workspaces->isEmpty()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Esta conta não possui nenhum ambiente de acesso ativo.',
            ]);
        }

        if ($workspaces->count() === 1) {
            $workspace = $workspaces->first();
            $this->workspaceManager->selectWorkspace($user, $workspace, $request);

            return $this->workspaceManager->redirectForWorkspace($user, $workspace);
        }

        return Inertia::render('Auth/SelectWorkspace', [
            'workspaces' => $workspaces->map(fn (UserWorkspace $w) => $w->toFrontendArray())->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);

        $workspace = $user->workspaces()
            ->where('id', $validated['workspace_id'])
            ->where('is_active', true)
            ->first();

        if (! $workspace) {
            throw ValidationException::withMessages([
                'workspace_id' => 'Ambiente de acesso inválido.',
            ]);
        }

        $this->workspaceManager->selectWorkspace($user, $workspace, $request);

        return $this->workspaceManager->redirectForWorkspace($user, $workspace);
    }
}
