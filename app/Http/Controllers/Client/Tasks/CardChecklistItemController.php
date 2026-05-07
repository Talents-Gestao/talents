<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskChecklistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CardChecklistItemController extends Controller
{
    public function update(Request $request, TaskChecklistItem $item): RedirectResponse
    {
        $item->loadMissing('checklist.card.list.board');
        $card = $item->checklist->card;
        $this->authorize('update', $card);

        $data = $request->validate([
            'text' => ['sometimes', 'string', 'max:2000'],
            'is_completed' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'numeric'],
        ]);

        $item->update($data);

        return back()->with('success', 'Item atualizado.');
    }
}
