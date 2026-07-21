<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Support\Tasks\BoardPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        $boardsQuery = TaskBoard::query()
            ->where('is_archived', false)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->with(['company:id,name'])
            ->withCount([
                'lists' => fn ($q) => $q->where('is_archived', false),
            ])
            ->orderByRaw('company_id is null desc')
            ->orderBy('name');

        if ($user->isCompanyUser()) {
            $boardsQuery->accessibleByCompanyUser($user->id, $companyId);
        }

        $boards = $boardsQuery
            ->get()
            ->filter(fn (TaskBoard $board) => $user->can('view', $board))
            ->values()
            ->map(function (TaskBoard $board) use ($companyId, $user) {
                $cardsQuery = TaskCard::query()
                    ->whereHas('list', fn ($q) => $q->where('board_id', $board->id)->where('is_archived', false))
                    ->where('is_archived', false)
                    ->visibleToCompany($companyId);

                if ($user->isCompanyUser() && ! $board->hasMember($user->id)) {
                    $cardsQuery->whereHas('members', fn ($q) => $q->where('users.id', $user->id));
                }

                $cardsCount = $cardsQuery->count();

                return [
                    'id' => $board->id,
                    'name' => $board->name,
                    'description' => $board->description,
                    'cover_color' => $board->cover_color,
                    'is_internal' => $board->company_id === null,
                    'company' => $board->company ? ['id' => $board->company->id, 'name' => $board->company->name] : null,
                    'lists_count' => $board->lists_count,
                    'cards_count' => $cardsCount,
                ];
            });

        return Inertia::render('Client/Tasks/Index', [
            'boards' => $boards,
        ]);
    }

    public function show(Request $request, TaskBoard $board): Response
    {
        $this->authorize('view', $board);

        $user = $request->user();
        $payload = BoardPresenter::forClient($board, (int) $user->company_id, $user);
        $companyUsers = BoardPresenter::companyUsersForMentions((int) $user->company_id);

        return Inertia::render('Client/Tasks/Show', [
            'boardPayload' => $payload,
            'companyUsers' => $companyUsers,
        ]);
    }
}
