<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Actions\Tasks\LogTaskActivity;
use App\Http\Controllers\Controller;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Portal: cria, edita e marca itens das checklists que já existem no cartão. Criar ou excluir a checklist, excluir
 * item e escolher responsável pela etapa ficam no Admin.
 */
class CardChecklistItemController extends Controller
{
    public function store(Request $request, TaskChecklist $checklist, LogTaskActivity $log): RedirectResponse
    {
        $checklist->loadMissing('card.list.board');
        $card = $checklist->card;
        abort_unless($card !== null, 404);
        $this->authorize('addChecklistItem', $card);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
        ]);

        $item = $checklist->items()->create([
            'text' => $data['text'],
            'description' => $data['description'] ?? null,
            'position' => (float) $checklist->items()->max('position') + 1000,
            'due_date' => $data['due_date'] ?? null,
        ]);

        $log->handle($card->list->board, $card, 'checklist_item.created', $request->user(), ['item_id' => $item->id]);

        return back()->with('success', 'Item adicionado.');
    }

    public function update(Request $request, TaskChecklistItem $item, LogTaskActivity $log): RedirectResponse
    {
        $item->loadMissing('checklist.card.list.board');
        $card = $item->checklist?->card;
        abort_unless($card !== null, 404);
        $this->authorize('update', $card);

        $data = $request->validate([
            'text' => ['sometimes', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_completed' => ['sometimes', 'boolean'],
            'due_date' => ['nullable', 'date'],
            'position' => ['sometimes', 'numeric'],
        ]);

        $item->update($data);

        if ($data !== []) {
            $log->handle($card->list->board, $card, 'checklist_item.updated', $request->user(), [
                'item_id' => $item->id,
                'fields' => array_keys($data),
            ]);
        }

        return back()->with('success', 'Item atualizado.');
    }
}
