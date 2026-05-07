<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskBoard;
use App\Support\Tasks\BoardPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $boards = TaskBoard::query()
            ->forCompany((int) $user->company_id)
            ->where('is_archived', false)
            ->orderByDesc('id')
            ->get(['id', 'name', 'cover_color', 'company_id', 'updated_at']);

        return Inertia::render('Client/Tarefas/Index', [
            'boards' => $boards,
        ]);
    }

    public function show(Request $request, TaskBoard $board): Response
    {
        $this->authorize('view', $board);

        $payload = BoardPresenter::forClient($board);
        $companyUsers = BoardPresenter::companyUsersForMentions((int) $request->user()->company_id);

        return Inertia::render('Client/Tarefas/Show', [
            'boardPayload' => $payload,
            'companyUsers' => $companyUsers,
        ]);
    }
}
