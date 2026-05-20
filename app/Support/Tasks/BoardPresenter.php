<?php

namespace App\Support\Tasks;

use App\Enums\UserRole;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class BoardPresenter
{
    /**
     * @return array<string, mixed>
     */
    /**
     * Resumo leve para listagem de quadros (expandir com cartões visíveis).
     *
     * @return array<string, mixed>
     */
    public static function forAdminIndex(TaskBoard $board): array
    {
        $board->loadMissing([
            'company:id,name',
            'lists' => fn ($q) => $q->where('is_archived', false)->orderBy('position')->orderBy('id'),
            'lists.cards' => fn ($q) => $q->where('is_archived', false)
                ->orderBy('position')
                ->orderBy('id')
                ->with([
                    'company:id,name',
                    'labels:id,name,color',
                    'members:id,name',
                    'checklists.items:id,task_checklist_id,is_completed',
                ])
                ->withCount(['comments', 'attachments']),
        ]);

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

    public static function forAdmin(TaskBoard $board): array
    {
        $board->loadMissing([
            'company:id,name',
            'lists.cards' => fn ($q) => $q->where('is_archived', false)->orderBy('position'),
            'lists' => fn ($q) => $q->where('is_archived', false)->orderBy('position'),
            'labels',
            'members:id,name,email,company_id',
        ]);

        return self::serializeBoard($board, false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forClient(TaskBoard $board, int $companyId): array
    {
        $board->loadMissing([
            'lists.cards' => function ($q) use ($companyId) {
                $q->visibleToCompany($companyId)->orderBy('position');
            },
            'lists' => function ($q) use ($companyId) {
                $q->where('is_archived', false)
                    ->whereHas('cards', fn (Builder $cq) => $cq->visibleToCompany($companyId))
                    ->orderBy('position');
            },
            'labels',
            'members:id,name,email,company_id',
        ]);

        return self::serializeBoard($board, true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeBoard(TaskBoard $board, bool $clientMode): array
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
        $cards = $list->cards->map(fn (TaskCard $card) => self::serializeCard($card));

        return [
            'id' => $list->id,
            'name' => $list->name,
            'color' => $list->color,
            'position' => $list->position,
            'visibility' => $list->visibility,
            'allow_company_drop_in' => $list->allow_company_drop_in,
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
        return [
            'id' => $card->id,
            'title' => $card->title,
            'description' => $card->description,
            'cover_color' => $card->cover_color,
            'due_date' => $card->due_date?->toDateString(),
            'completed_at' => $card->completed_at?->toIso8601String(),
            'company' => $card->company ? ['id' => $card->company->id, 'name' => $card->company->name] : null,
            'labels' => $card->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->values(),
            'members' => $card->members->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'checklists' => $card->checklists->map(fn ($cl) => [
                'id' => $cl->id,
                'items' => $cl->items->map(fn ($it) => [
                    'id' => $it->id,
                    'is_completed' => $it->is_completed,
                    'due_date' => $it->due_date?->toDateString(),
                ])->values(),
            ])->values(),
            'comments_count' => (int) ($card->comments_count ?? 0),
            'attachments_count' => (int) ($card->attachments_count ?? 0),
        ];
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
                    'position' => $it->position,
                    'is_completed' => $it->is_completed,
                    'due_date' => $it->due_date?->toDateString(),
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
        ];
    }

    /**
     * Utilizadores da empresa para menções no cliente.
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
     * Utilizadores internos Talents (admin) atribuíveis em tarefas.
     *
     * @return Collection<int, array{id:int,name:string,email:string}>
     */
    public static function allActiveTalentsTeamUsers(): Collection
    {
        return User::query()
            ->where('role', UserRole::SuperAdmin)
            ->whereNull('company_id')
            ->where('is_active', true)
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
