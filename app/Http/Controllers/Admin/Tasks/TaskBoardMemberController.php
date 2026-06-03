<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Actions\Tasks\LogTaskActivity;
use App\Enums\TaskBoardMemberRole;
use App\Http\Controllers\Controller;
use App\Enums\WorkspaceType;
use App\Models\TaskBoard;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskBoardMemberController extends Controller
{
    public function store(Request $request, TaskBoard $board, LogTaskActivity $log): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['nullable', Rule::in(array_map(fn ($r) => $r->value, TaskBoardMemberRole::cases()))],
        ]);

        $role = $data['role'] ?? TaskBoardMemberRole::Viewer->value;

        $user = User::query()
            ->where('id', $data['user_id'])
            ->where('is_active', true)
            ->first();

        if (! $user || ! $this->isEligibleBoardMember($user, $board)) {
            throw ValidationException::withMessages([
                'user_id' => 'Usuário inválido ou não elegível para este quadro.',
            ]);
        }

        $board->members()->syncWithoutDetaching([
            $data['user_id'] => ['role' => $role],
        ]);

        $log->handle($board, null, 'board.member.added', $request->user(), [
            'user_id' => $data['user_id'],
            'role' => $role,
        ]);

        return back()->with('success', 'Membro adicionado ao quadro.');
    }

    public function destroy(Request $request, TaskBoard $board, User $user, LogTaskActivity $log): RedirectResponse
    {
        $board->members()->detach($user->id);

        $log->handle($board, null, 'board.member.removed', $request->user(), [
            'user_id' => $user->id,
        ]);

        return back()->with('success', 'Membro removido do quadro.');
    }

    private function isEligibleBoardMember(User $user, TaskBoard $board): bool
    {
        $hasActiveTalentsWorkspace = $user->workspaces()
            ->where('workspace_type', WorkspaceType::Talents)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveTalentsWorkspace) {
            return true;
        }

        if ($user->company_id === null || ! $user->is_active) {
            return false;
        }

        if ($board->company_id === null) {
            return true;
        }

        return (int) $user->company_id === (int) $board->company_id;
    }
}
