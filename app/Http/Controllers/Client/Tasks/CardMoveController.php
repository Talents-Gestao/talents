<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Actions\Tasks\MoveTaskCard;
use App\Http\Controllers\Controller;
use App\Models\TaskCard;
use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CardMoveController extends Controller
{
    public function store(Request $request, TaskCard $card, MoveTaskCard $moveTaskCard): RedirectResponse
    {
        $this->authorize('move', $card);

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
            false,
        );

        return back()->with('success', 'Cartão movido.');
    }
}
