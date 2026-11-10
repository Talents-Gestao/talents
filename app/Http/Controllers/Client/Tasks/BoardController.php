<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Support\Tasks\BoardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    /** Tela única de Tarefas: abre direto no último quadro usado, no primeiro favorito ou no primeiro da lista. */
    public function index(Request $request): Response
    {
        $boards = $this->visibleBoards($request);
        $boardId = BoardPresenter::resolveInitialBoardId($boards, $request->session()->get($this->lastBoardSessionKey($request)));
        $board = $boardId ? TaskBoard::query()->find($boardId) : null;

        return $this->renderBoard($request, $board, $boards);
    }

    /** Mesma tela única do index, já no quadro pedido (links e notificações continuam valendo). */
    public function show(Request $request, TaskBoard $board): Response
    {
        $this->authorize('view', $board);

        return $this->renderBoard($request, $board, $this->visibleBoards($request));
    }

    private function lastBoardSessionKey(Request $request): string
    {
        return 'tasks.client.last_board_id.'.(int) $request->user()->company_id;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $boards
     */
    private function renderBoard(Request $request, ?TaskBoard $board, Collection $boards): Response
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        if ($board) {
            $request->session()->put($this->lastBoardSessionKey($request), $board->id);
        }

        return Inertia::render('Client/Tasks/Index', [
            'boards' => $boards,
            'boardPayload' => $board ? BoardPresenter::forClient($board, $companyId, $user) : null,
            'companyUsers' => BoardPresenter::companyUsersForMentions($companyId),
        ]);
    }

    /**
     * Quadros que o usuário do portal pode ver, na ordem da Talents, com contagem de cartões visíveis.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function visibleBoards(Request $request): Collection
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
            ->orderBy('position')
            ->orderBy('id');

        if ($user->isCompanyUser()) {
            $boardsQuery->accessibleByCompanyUser($user->id, $companyId);
        }

        $boards = $boardsQuery
            ->get()
            ->filter(fn (TaskBoard $board) => $user->can('view', $board))
            ->values();

        $extra = [];
        foreach ($boards as $board) {
            $cardsQuery = TaskCard::query()
                ->whereHas('list', fn ($q) => $q->where('board_id', $board->id)->where('is_archived', false))
                ->where('is_archived', false)
                ->visibleToCompany($companyId);

            if ($user->isCompanyUser() && ! $board->hasMember($user->id)) {
                $cardsQuery->whereHas('members', fn ($q) => $q->where('users.id', $user->id));
            }

            $extra[$board->id] = [
                'lists_count' => $board->lists_count,
                'cards_count' => $cardsQuery->count(),
            ];
        }

        return BoardPresenter::forSwitcher($boards, $user->id, $extra);
    }
}
