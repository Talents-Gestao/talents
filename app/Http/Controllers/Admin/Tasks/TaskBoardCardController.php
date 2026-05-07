<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Actions\Tasks\LogTaskActivity;
use App\Actions\Tasks\MoveTaskCard;
use App\Http\Controllers\Controller;
use App\Models\TaskList;
use App\Models\TaskCard;
use App\Models\User;
use App\Notifications\TaskCardMemberAssignedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class TaskBoardCardController extends Controller
{
    public function store(Request $request, TaskList $list, LogTaskActivity $log): RedirectResponse
    {
        $board = $list->board;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['required', 'in:internal,company,inherit'],
            'position' => ['nullable', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        $max = (float) $list->cards()->max('position');
        $data['position'] = $data['position'] ?? ($max + 1000);

        $card = $list->cards()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'],
            'position' => $data['position'],
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'created_by_user_id' => $request->user()->id,
        ]);

        $log->handle($board, $card, 'card.created', $request->user(), []);

        return back()->with('success', 'Cartão criado.');
    }

    public function update(Request $request, TaskCard $card, LogTaskActivity $log): RedirectResponse
    {
        $board = $card->list->board;
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['sometimes', 'in:internal,company,inherit'],
            'position' => ['sometimes', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'cover_color' => ['nullable', 'string', 'max:32'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:task_labels,id'],
        ]);

        $memberIds = $data['member_ids'] ?? null;
        $labelIds = $data['label_ids'] ?? null;
        unset($data['member_ids'], $data['label_ids']);

        if ($data !== []) {
            $card->update($data);
        }

        if (is_array($memberIds)) {
            $validIds = User::query()
                ->whereIn('id', $memberIds)
                ->when($board->company_id, fn ($q) => $q->where('company_id', $board->company_id))
                ->when(! $board->company_id, fn ($q) => $q->whereNull('company_id'))
                ->pluck('id')
                ->all();
            $previous = $card->members()->pluck('users.id')->all();
            $card->members()->sync($validIds);
            $added = array_diff($validIds, $previous);
            if ($added !== []) {
                $users = User::query()->whereIn('id', $added)->get();
                Notification::send($users, new TaskCardMemberAssignedNotification($card, $request->user()));
            }
        }

        if (is_array($labelIds)) {
            $allowedLabels = $board->labels()->whereIn('id', $labelIds)->pluck('id')->all();
            $card->labels()->sync($allowedLabels);
        }

        $log->handle($board, $card, 'card.updated', $request->user(), []);

        return back()->with('success', 'Cartão atualizado.');
    }

    public function move(Request $request, TaskCard $card, MoveTaskCard $moveTaskCard): RedirectResponse
    {
        $data = $request->validate([
            'list_id' => ['required', 'exists:task_lists,id'],
            'position' => ['required', 'numeric'],
        ]);

        $targetList = TaskList::query()->findOrFail($data['list_id']);

        $moveTaskCard->handle(
            $card,
            $targetList,
            (float) $data['position'],
            $request->user(),
            true,
        );

        return back()->with('success', 'Cartão movido.');
    }

    public function destroy(TaskCard $card, LogTaskActivity $log): RedirectResponse
    {
        $board = $card->list->board;
        $card->delete();
        $log->handle($board, null, 'card.deleted', request()->user(), []);

        return back()->with('success', 'Cartão removido.');
    }
}
