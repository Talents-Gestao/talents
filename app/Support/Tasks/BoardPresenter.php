<?php

namespace App\Support\Tasks;

use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class BoardPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forAdmin(TaskBoard $board): array
    {
        $board->loadMissing([
            'company:id,name',
            'lists.cards' => fn ($q) => $q->where('is_archived', false)->orderBy('position'),
            'lists' => fn ($q) => $q->where('is_archived', false)->orderBy('position'),
            'labels',
            'members:id,name,email',
        ]);

        return self::serializeBoard($board, false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forClient(TaskBoard $board): array
    {
        $board->loadMissing([
            'lists.cards' => function ($q) {
                $q->visibleToCompany()->orderBy('position');
            },
            'lists' => function ($q) {
                $q->visibleToCompany()->orderBy('position');
            },
            'labels',
            'members:id,name,email',
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

        $userId = Auth::id();
        $isStarred = false;
        if ($userId) {
            $isStarred = DB::table('task_board_user_favorites')
                ->where('board_id', $board->id)
                ->where('user_id', $userId)
                ->exists();
        }

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
            'position' => $list->position,
            'visibility' => $list->visibility,
            'allow_company_drop_in' => $list->allow_company_drop_in,
            'cards' => $cards,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeCard(TaskCard $card): array
    {
        $card->loadMissing([
            'labels:id,name,color',
            'members:id,name,email',
            'checklists.items',
            'attachments',
            'comments.user:id,name',
        ]);

        return [
            'id' => $card->id,
            'list_id' => $card->list_id,
            'title' => $card->title,
            'description' => $card->description,
            'position' => $card->position,
            'visibility' => $card->visibility,
            'cover_color' => $card->cover_color,
            'cover_attachment_id' => $card->cover_attachment_id,
            'start_date' => $card->start_date?->toDateString(),
            'due_date' => $card->due_date?->toDateString(),
            'completed_at' => $card->completed_at?->toIso8601String(),
            'labels' => $card->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->values(),
            'members' => $card->members->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
            'checklists' => $card->checklists->map(fn ($cl) => [
                'id' => $cl->id,
                'name' => $cl->name,
                'position' => $cl->position,
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
}
