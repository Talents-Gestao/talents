<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskCard;
use App\Models\User;
use App\Notifications\TaskCommentMentionNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class CardCommentController extends Controller
{
    public function store(Request $request, TaskCard $card): RedirectResponse
    {
        $this->authorize('comment', $card);

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

        $companyId = (int) $request->user()->company_id;
        $mentionIds = array_unique($data['mentioned_user_ids'] ?? []);
        $mentionIds = array_values(array_diff($mentionIds, [$request->user()->id]));

        if ($mentionIds !== []) {
            $users = User::query()
                ->whereIn('id', $mentionIds)
                ->where('company_id', $companyId)
                ->get();

            Notification::send($users, new TaskCommentMentionNotification($comment, $card, $request->user()));
        }

        return back()->with('success', 'Comentário publicado.');
    }
}
