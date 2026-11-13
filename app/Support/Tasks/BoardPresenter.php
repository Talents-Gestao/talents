<?php

namespace App\Support\Tasks;

use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class BoardPresenter
{
    /**
     * Eager load de listas + cartões num único nível (evita conflito lists vs lists.cards no Laravel).
     */
    private static function loadAdminBoardListsWithCards(
        TaskBoard $board,
        bool $fullCardRelations = false,
        bool $includeArchived = false,
    ): void {
        $cardWith = $fullCardRelations
            ? [
                'company:id,name',
                'labels:id,name,color',
                'members:id,name,email,company_id',
                'checklists.items',
                'attachments',
                'comments.user:id,name',
            ]
            : [
                'company:id,name',
                'labels:id,name,color',
                'members:id,name',
                'checklists.items:id,task_checklist_id,is_completed,due_date',
            ];

        $board->load([
            'company:id,name',
            'lists' => function ($q) use ($includeArchived, $cardWith) {
                if (! $includeArchived) {
                    $q->where('is_archived', false);
                }
                $q->orderBy('is_archived')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->with([
                        'cards' => function ($cq) use ($includeArchived, $cardWith) {
                            if (! $includeArchived) {
                                $cq->where('is_archived', false);
                            }
                            $cq->orderBy('is_archived')
                                ->orderBy('position')
                                ->orderBy('id')
                                ->with($cardWith)
                                ->withCount(['comments', 'attachments']);
                        },
                    ]);
            },
        ]);
    }

    /**
     * @return array{done: int, total: int}
     */
    private static function checklistCounts(TaskCard $card): array
    {
        if (! $card->relationLoaded('checklists')) {
            $card->load(['checklists.items']);
        } else {
            foreach ($card->checklists as $checklist) {
                if (! $checklist->relationLoaded('items')) {
                    $checklist->load('items');
                }
            }
        }

        $total = 0;
        $done = 0;

        foreach ($card->checklists as $checklist) {
            foreach ($checklist->items as $item) {
                $total++;
                if ($item->is_completed) {
                    $done++;
                }
            }
        }

        return ['done' => $done, 'total' => $total];
    }

    /**
     * Resumo leve para listagem de quadros (expandir com cartões visíveis).
     *
     * @return array<string, mixed>
     */
    public static function forAdminIndex(TaskBoard $board): array
    {
        self::loadAdminBoardListsWithCards($board, false);

        $cardsCount = 0;
        $lists = $board->lists->map(function ($list) use (&$cardsCount) {
            $cards = $list->cards->map(fn (TaskCard $card) => self::serializeCardPreview($card))->values();
            $cardsCount += $cards->count();

            return [
                'id' => $list->id,
                'name' => $list->name,
                'color' => $list->color,
                'cards' => $cards,
            ];
        })->values();

        return [
            'id' => $board->id,
            'name' => $board->name,
            'description' => $board->description,
            'cover_color' => $board->cover_color,
            'company_id' => $board->company_id,
            'company' => $board->company ? ['id' => $board->company->id, 'name' => $board->company->name] : null,
            'is_internal' => $board->company_id === null,
            'lists_count' => $lists->count(),
            'cards_count' => $cardsCount,
            'updated_at' => $board->updated_at?->toIso8601String(),
            'lists' => $lists,
        ];
    }

    /**
     * Lista leve para o seletor de quadros da tela de Tarefas (sem listas nem cartões).
     *
     * @param  iterable<int, TaskBoard>  $boards  já filtrados e ordenados
     * @param  array<int, array<string, mixed>>  $extra  campos extras por id de quadro
     * @return Collection<int, array<string, mixed>>
     */
    public static function forSwitcher(iterable $boards, ?int $userId, array $extra = []): Collection
    {
        $starred = array_flip(self::starredBoardIds($userId));

        return collect($boards)->map(fn (TaskBoard $board) => array_merge([
            'id' => $board->id,
            'name' => $board->name,
            'description' => $board->description,
            'cover_color' => $board->cover_color,
            'company_id' => $board->company_id,
            'company' => $board->company ? ['id' => $board->company->id, 'name' => $board->company->name] : null,
            'is_internal' => $board->company_id === null,
            'is_starred' => isset($starred[$board->id]),
        ], $extra[$board->id] ?? []))->values();
    }

    /**
     * Resumo de cada quadro para o estado recolhido da tela de Tarefas, em duas consultas para todos:
     * listas ativas com contagem de cartões, concluídos, atrasados e próximo prazo.
     * No portal ($companyId informado) conta só os cartões que a empresa vê (mesma regra de forClient)
     * e só as listas com cartões visíveis.
     *
     * @param  Collection<int, TaskBoard>  $boards
     * @return array<int, array<string, mixed>> por id de quadro: lists_count, cards_count e summary
     */
    public static function summaries(Collection $boards, ?int $companyId = null, ?User $user = null): array
    {
        if ($boards->isEmpty()) {
            return [];
        }

        $lists = TaskList::query()
            ->whereIn('board_id', $boards->pluck('id'))
            ->where('is_archived', false)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'board_id', 'name', 'color']);

        $cardsQuery = TaskCard::query()
            ->whereIn('list_id', $lists->pluck('id'))
            ->where('is_archived', false);

        if ($user !== null && ! $user->isSuperAdmin()) {
            PortalTaskAccess::scopeCards($cardsQuery, $user);
        } elseif ($companyId !== null) {
            $cardsQuery->visibleToCompany($companyId);
        }

        $cardsByList = $cardsQuery->get(['id', 'list_id', 'due_date', 'completed_at'])->groupBy('list_id');
        $today = now()->toDateString();
        $soonLimit = now()->addDays(7)->toDateString();

        $result = [];
        foreach ($boards as $board) {
            $boardLists = [];
            $total = 0;
            $completed = 0;
            $overdue = 0;
            $dueSoon = 0;
            $nextDue = null;

            foreach ($lists->where('board_id', $board->id) as $list) {
                $cards = $cardsByList->get($list->id, collect());
                if ($companyId !== null && $cards->isEmpty()) {
                    continue;
                }

                $listCompleted = 0;
                foreach ($cards as $card) {
                    if ($card->completed_at !== null) {
                        $listCompleted++;

                        continue;
                    }
                    $due = $card->due_date?->toDateString();
                    if ($due === null) {
                        continue;
                    }
                    if ($due < $today) {
                        $overdue++;
                    } else {
                        $nextDue = $nextDue === null || $due < $nextDue ? $due : $nextDue;
                        if ($due <= $soonLimit) {
                            $dueSoon++;
                        }
                    }
                }

                $total += $cards->count();
                $completed += $listCompleted;
                $boardLists[] = [
                    'id' => $list->id,
                    'name' => $list->name,
                    'color' => $list->color,
                    'cards_count' => $cards->count(),
                    'completed_count' => $listCompleted,
                ];
            }

            $result[$board->id] = [
                'lists_count' => count($boardLists),
                'cards_count' => $total,
                'summary' => [
                    'lists' => $boardLists,
                    'completed_count' => $completed,
                    'overdue_count' => $overdue,
                    'due_soon_count' => $dueSoon,
                    'next_due_date' => $nextDue,
                ],
            ];
        }

        return $result;
    }

    /**
     * Ids pedidos pela tela de Tarefas num cabeçalho (ex.: «3,5,8»), só os que estão em $allowedIds.
     *
     * @param  iterable<int, int>  $allowedIds
     * @return array<int, int>
     */
    public static function requestedBoardIds(?string $header, iterable $allowedIds, int $limit = 12): array
    {
        $allowed = array_flip(array_map('intval', collect($allowedIds)->all()));

        return collect(explode(',', (string) $header))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => isset($allowed[$id]))
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Quadro inicial da tela de Tarefas: o último usado (se ainda estiver na lista),
     * senão o primeiro favorito, senão o primeiro da lista.
     *
     * @param  iterable<int, array{id:int,is_starred?:bool}>  $boards
     */
    public static function resolveInitialBoardId(iterable $boards, mixed $lastBoardId): ?int
    {
        $boards = collect($boards);
        $last = is_numeric($lastBoardId) ? (int) $lastBoardId : null;

        if ($last !== null && $boards->contains(fn ($b) => (int) $b['id'] === $last)) {
            return $last;
        }

        $first = $boards->first(fn ($b) => ! empty($b['is_starred'])) ?? $boards->first();

        return $first ? (int) $first['id'] : null;
    }

    /**
     * @return array<int, int>
     */
    private static function starredBoardIds(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

        try {
            if (! Schema::hasTable('task_board_user_favorites')) {
                return [];
            }

            return DB::table('task_board_user_favorites')
                ->where('user_id', $userId)
                ->pluck('board_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function forAdmin(TaskBoard $board, bool $includeArchived = false): array
    {
        self::loadAdminBoardListsWithCards($board, true, $includeArchived);

        $board->loadMissing([
            'labels',
            'members:id,name,email,company_id',
        ]);

        return self::serializeBoard($board, false, $includeArchived);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Quadro no portal. Com o usuário, vale PortalTaskAccess (cartões, membros mostrados e o que ele pode fazer);
     * sem usuário, a visão da empresa como um todo (cartões com company_id da empresa).
     */
    public static function forClient(TaskBoard $board, int $companyId, ?User $user = null): array
    {
        $visible = function ($q) use ($user, $companyId) {
            $cards = $q instanceof Relation ? $q->getQuery() : $q;
            $user !== null ? PortalTaskAccess::scopeCards($cards, $user) : $cards->visibleToCompany($companyId);

            return $q;
        };

        $board->loadMissing([
            'lists.cards' => fn ($q) => $visible($q)->orderBy('position'),
            'lists' => fn ($q) => $q->where('is_archived', false)
                ->whereHas('cards', fn (Builder $cq) => $visible($cq))
                ->orderBy('position'),
            'labels',
        ]);

        $payload = self::serializeBoard($board, true);

        // Membros mostrados: só quem está atribuído a um cartão visível (nunca a equipe inteira do quadro).
        $payload['members'] = $board->lists
            ->flatMap(fn ($list) => $list->cards->flatMap(fn (TaskCard $card) => $card->members))
            ->unique('id')
            ->sortBy('name')
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'role' => null])
            ->values();

        $payload['portal_can'] = $user !== null
            ? PortalTaskAccess::abilities($user, (int) $board->id)
            : ['comment' => false, 'edit' => false, 'move' => false, 'attach' => false, 'remove_attachment' => false];

        // Cada cartão leva o que o usuário pode fazer nele (o modal recebe só o cartão) e os membros sem e-mail.
        $payload['lists'] = collect($payload['lists'])->map(fn (array $list) => [
            ...$list,
            'cards' => collect($list['cards'])->map(fn (array $card) => [
                ...$card,
                'portal_can' => $payload['portal_can'],
                'members' => collect($card['members'])
                    ->map(fn (array $m) => ['id' => $m['id'], 'name' => $m['name'], 'is_team' => $m['is_team']])
                    ->values(),
            ])->values(),
        ])->values();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeBoard(TaskBoard $board, bool $clientMode, bool $includeArchived = false): array
    {
        $lists = $board->lists->map(fn ($list) => self::serializeList($list, $clientMode));

        $labels = $board->labels->map(fn ($l) => [
            'id' => $l->id,
            'name' => $l->name,
            'color' => $l->color,
            'position' => $l->position,
        ]);

        $members = $board->relationLoaded('members')
            ? $board->members->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role ?? null,
            ])->values()
            : collect();

        $isStarred = self::isBoardStarredBy($board->id, Auth::id());

        return [
            'id' => $board->id,
            'name' => $board->name,
            'description' => $board->description,
            'cover_color' => $board->cover_color,
            'company_id' => $board->company_id,
            'company' => $board->company ? ['id' => $board->company->id, 'name' => $board->company->name] : null,
            'is_archived' => $board->is_archived,
            'is_internal' => $board->company_id === null,
            'is_starred' => $isStarred,
            'client_mode' => $clientMode,
            'show_archived' => $includeArchived,
            'lists' => $lists,
            'labels' => $labels,
            'members' => $members,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeList($list, bool $clientMode): array
    {
        $cards = $list->cards->map(
            fn (TaskCard $card) => $clientMode ? self::serializeCard($card) : self::serializeCardForBoard($card),
        );

        return [
            'id' => $list->id,
            'name' => $list->name,
            'color' => $list->color,
            'position' => $list->position,
            'visibility' => $list->visibility,
            'allow_company_drop_in' => $list->allow_company_drop_in,
            'is_archived' => (bool) $list->is_archived,
            'cards' => $cards,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Cartão resumido para listagem / preview de quadros (ícones estilo Trello).
     *
     * @return array<string, mixed>
     */
    public static function serializeCardPreview(TaskCard $card): array
    {
        $checklist = self::checklistCounts($card);

        return [
            'id' => $card->id,
            'title' => $card->title,
            'description' => $card->description,
            'cover_color' => $card->cover_color,
            'due_date' => $card->due_date?->toDateString(),
            'completed_at' => $card->completed_at?->toIso8601String(),
            'is_archived' => (bool) $card->is_archived,
            'recurrence' => $card->recurrence?->value,
            'recurrence_label' => $card->recurrence?->label(),
            'recurrence_ends_on' => $card->recurrence_ends_on?->toDateString(),
            'company' => $card->company ? ['id' => $card->company->id, 'name' => $card->company->name] : null,
            'labels' => $card->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->values(),
            'members' => $card->members->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'checklists' => $card->checklists->map(fn ($cl) => [
                'id' => $cl->id,
                'items' => $cl->items->map(fn ($it) => [
                    'id' => $it->id,
                    'is_completed' => (bool) $it->is_completed,
                    'due_date' => $it->due_date?->toDateString(),
                ])->values(),
            ])->values(),
            'checklist_done' => $checklist['done'],
            'checklist_total' => $checklist['total'],
            'comments_count' => (int) ($card->comments_count ?? 0),
            'attachments_count' => (int) ($card->attachments_count ?? 0),
        ];
    }

    /**
     * Cartão completo para o quadro admin (kanban + modal), com meta explícita para ícones.
     *
     * @return array<string, mixed>
     */
    public static function serializeCardForBoard(TaskCard $card): array
    {
        $preview = self::serializeCardPreview($card);
        $full = self::serializeCard($card);

        return array_merge($full, [
            'checklist_done' => $preview['checklist_done'],
            'checklist_total' => $preview['checklist_total'],
            'comments_count' => $preview['comments_count'],
            'attachments_count' => $preview['attachments_count'],
            'checklists' => $full['checklists'],
        ]);
    }

    public static function serializeCard(TaskCard $card): array
    {
        $card->loadMissing([
            'company:id,name',
            'labels:id,name,color',
            'members:id,name,email,company_id',
            'checklists.items',
            'attachments',
            'comments.user:id,name',
        ]);

        $checklist = self::checklistCounts($card);

        return [
            'id' => $card->id,
            'list_id' => $card->list_id,
            'title' => $card->title,
            'description' => $card->description,
            'company_id' => $card->company_id,
            'company' => $card->company ? ['id' => $card->company->id, 'name' => $card->company->name] : null,
            'position' => $card->position,
            'visibility' => $card->visibility,
            'cover_color' => $card->cover_color,
            'cover_attachment_id' => $card->cover_attachment_id,
            'start_date' => $card->start_date?->toDateString(),
            'due_date' => $card->due_date?->toDateString(),
            'completed_at' => $card->completed_at?->toIso8601String(),
            'is_archived' => (bool) $card->is_archived,
            'recurrence' => $card->recurrence?->value,
            'recurrence_label' => $card->recurrence?->label(),
            'recurrence_ends_on' => $card->recurrence_ends_on?->toDateString(),
            'labels' => $card->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->values(),
            'members' => $card->members->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'company_id' => $u->company_id,
                'is_team' => $u->company_id === null,
            ])->values(),
            'checklists' => $card->checklists->map(fn ($cl) => [
                'id' => $cl->id,
                'name' => $cl->name,
                'position' => $cl->position,
                'is_completed' => (bool) $cl->is_completed,
                'items' => $cl->items->map(fn ($it) => [
                    'id' => $it->id,
                    'text' => $it->text,
                    'description' => $it->description,
                    'position' => $it->position,
                    'is_completed' => $it->is_completed,
                    'due_date' => $it->due_date?->toDateString(),
                    'created_at' => $it->created_at?->toDateString(),
                    'assignee_user_id' => $it->assignee_user_id,
                ])->values(),
            ])->values(),
            'attachments' => $card->attachments->map(fn ($a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime' => $a->mime,
                'size' => $a->size,
                'url' => \Storage::disk($a->disk)->url($a->path),
            ])->values(),
            'comments' => $card->comments->map(fn ($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'created_at' => $c->created_at?->toIso8601String(),
                'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
            ])->values(),
            'comments_count' => (int) ($card->comments_count ?? $card->comments->count()),
            'attachments_count' => (int) ($card->attachments_count ?? $card->attachments->count()),
            'checklist_done' => $checklist['done'],
            'checklist_total' => $checklist['total'],
        ];
    }

    /**
     * Usuários da empresa para menções no cliente.
     *
     * @return Collection<int, array{id:int,name:string,email:string}>
     */
    public static function companyUsersForMentions(int $companyId): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
    }

    /**
     * Usuários internos Talents (admin) atribuíveis em tarefas.
     *
     * @return Collection<int, array{id:int,name:string,email:string}>
     */
    public static function allActiveTalentsTeamUsers(): Collection
    {
        return User::query()
            ->withActiveTalentsWorkspace()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
    }

    /**
     * @return Collection<int, array{id:int,name:string,email:string,company_id:int|null,company_name:string|null}>
     */
    public static function allActiveCompanyUsers(): Collection
    {
        return User::query()
            ->with('company:id,name')
            ->whereNotNull('company_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'company_id'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'company_id' => $u->company_id,
                'company_name' => $u->company?->name,
            ]);
    }

    /**
     * Verifica se o usuário marcou o quadro como favorito.
     *
     * Tolerante à ausência da tabela (caso a migration ainda não tenha
     * sido aplicada no ambiente), evitando 500 ao acessar o quadro.
     */
    private static function isBoardStarredBy(int $boardId, ?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        try {
            if (! Schema::hasTable('task_board_user_favorites')) {
                return false;
            }

            return DB::table('task_board_user_favorites')
                ->where('board_id', $boardId)
                ->where('user_id', $userId)
                ->exists();
        } catch (Throwable $e) {
            return false;
        }
    }
}
