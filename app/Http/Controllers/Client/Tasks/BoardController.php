<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskBoard;
use App\Support\Tasks\BoardPresenter;
use App\Support\Tasks\PortalTaskAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    /** Cabeçalho da tela de Tarefas com os quadros a carregar (só os expandidos, sob demanda). */
    public const BOARDS_HEADER = 'X-Task-Boards';

    /** Cabeçalho com os quadros (entre os pedidos) que mostram itens arquivados. */
    public const ARCHIVED_HEADER = 'X-Task-Boards-Archived';

    /** Cabeçalho com o quadro que o usuário acabou de abrir (vira o «último usado»). */
    public const OPENED_HEADER = 'X-Task-Board-Opened';

    /**
     * Tela única de Tarefas: todos os quadros visíveis empilhados. O último usado (ou o primeiro favorito,
     * ou o primeiro da lista) já vem com o conteúdo; os demais carregam ao expandir.
     */
    public function index(Request $request): Response
    {
        $models = $this->visibleBoardModels($request);
        $light = BoardPresenter::forSwitcher($models, $request->user()->id);
        $this->rememberOpenedBoard($request, $light->pluck('id'));
        $boardId = BoardPresenter::resolveInitialBoardId($light, $request->session()->get($this->lastBoardSessionKey($request)));

        return $this->renderScreen($request, $models, $boardId ? $models->firstWhere('id', $boardId) : null, false);
    }

    /** Mesma tela do index, com este quadro expandido e rolado até ele (links e notificações continuam valendo). */
    public function show(Request $request, TaskBoard $board): Response
    {
        $this->authorize('view', $board);

        $models = $this->visibleBoardModels($request);
        if (! $request->header('X-Inertia-Partial-Data')) {
            $request->session()->put($this->lastBoardSessionKey($request), $board->id);
        }
        $this->rememberOpenedBoard($request, $models->pluck('id'));

        return $this->renderScreen($request, $models, $models->firstWhere('id', $board->id) ?? $board, true);
    }

    private function lastBoardSessionKey(Request $request): string
    {
        return 'tasks.client.last_board_id.'.(int) $request->user()->company_id;
    }

    /**
     * @param  Collection<int, int>  $allowedIds
     */
    private function rememberOpenedBoard(Request $request, Collection $allowedIds): void
    {
        $opened = BoardPresenter::requestedBoardIds($request->header(self::OPENED_HEADER), $allowedIds, 1);
        if ($opened !== []) {
            $request->session()->put($this->lastBoardSessionKey($request), $opened[0]);
        }
    }

    /**
     * Props em closures: as recargas parciais (só boardPayloads, ou boards) não calculam o resto.
     *
     * @param  Collection<int, TaskBoard>  $models
     */
    private function renderScreen(Request $request, Collection $models, ?TaskBoard $focus, bool $scrollToFocus): Response
    {
        $user = $request->user();
        $companyId = PortalTaskAccess::companyId($user);

        return Inertia::render('Client/Tasks/Index', [
            'boards' => fn () => BoardPresenter::forSwitcher(
                $models,
                $user->id,
                BoardPresenter::summaries($models, $companyId, $user),
            ),
            'boardPayload' => fn () => $focus ? BoardPresenter::forClient($focus, $companyId, $user) : null,
            'boardPayloads' => function () use ($request, $models, $companyId, $user) {
                $ids = BoardPresenter::requestedBoardIds($request->header(self::BOARDS_HEADER), $models->pluck('id'));

                return collect($ids)
                    ->map(fn (int $id) => BoardPresenter::forClient($models->firstWhere('id', $id), $companyId, $user))
                    ->values();
            },
            'focusBoardId' => $focus?->id,
            'scrollToFocus' => $scrollToFocus,
            'companyUsers' => fn () => BoardPresenter::companyUsersForMentions($companyId),
        ]);
    }

    /**
     * Quadros que o usuário do portal pode ver, na ordem da Talents.
     *
     * @return Collection<int, TaskBoard>
     */
    private function visibleBoardModels(Request $request): Collection
    {
        $user = $request->user();
        if (! PortalTaskAccess::moduleEnabled($user)) {
            return collect();
        }

        return PortalTaskAccess::boardsQuery($user)
            ->with(['company:id,name'])
            ->orderByRaw('company_id is null desc')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }
}
