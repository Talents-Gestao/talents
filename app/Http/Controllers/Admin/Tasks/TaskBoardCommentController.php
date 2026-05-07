<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskCard;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\TaskCommentMentionNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class TaskBoardCommentController extends Controller
{
    public function store(Request $request, TaskCard $card): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'mentioned_user_ids' => ['nullable', 'array'],
            'mentioned_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $comment = $card->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'mentioned_user_ids' => $data['mentioned_user_ids'] ?? [],
        ]);

        $board = $card->list->board;
        $mentionIds = array_unique($data['mentioned_user_ids'] ?? []);
        $mentionIds = array_values(array_diff($mentionIds, [$request->user()->id]));

        if ($mentionIds !== []) {
            $users = User::query()->whereIn('id', $mentionIds)
                ->when($board->company_id, fn ($q) => $q->where('company_id', $board->company_id))
                ->when(! $board->company_id, fn ($q) => $q->whereNull('company_id'))
                ->get();

            Notification::send($users, new TaskCommentMentionNotification($comment, $card, $request->user()));
        }

        return back()->with('success', 'Comentário publicado.');
    }

    public function destroy(TaskComment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return back()->with('success', 'Comentário removido.');
    }
}
