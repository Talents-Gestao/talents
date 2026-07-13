<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskBoardChecklistItemController extends Controller
{
    public function store(Request $request, TaskChecklist $checklist): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position' => ['nullable', 'numeric'],
            'due_date' => ['nullable', 'date'],
            'assignee_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $max = (float) $checklist->items()->max('position');
        $data['position'] = $data['position'] ?? ($max + 1000);

        $checklist->items()->create([
            'text' => $data['text'],
            'description' => $data['description'] ?? null,
            'position' => $data['position'],
            'due_date' => $data['due_date'] ?? null,
            'assignee_user_id' => $data['assignee_user_id'] ?? null,
        ]);

        return back()->with('success', 'Item adicionado.');
    }

    public function update(Request $request, TaskChecklistItem $item): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['sometimes', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position' => ['sometimes', 'numeric'],
            'is_completed' => ['sometimes', 'boolean'],
            'due_date' => ['nullable', 'date'],
            'assignee_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $item->update($data);

        return back()->with('success', 'Item atualizado.');
    }

    public function destroy(TaskChecklistItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('success', 'Item removido.');
    }

    public function reorder(Request $request, TaskChecklist $checklist): RedirectResponse
    {
        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:task_checklist_items,id'],
        ]);

        $itemIds = collect($data['item_ids'])->map(fn ($id) => (int) $id)->values();
        $ownedCount = TaskChecklistItem::query()
            ->where('task_checklist_id', $checklist->id)
            ->whereIn('id', $itemIds)
            ->count();

        if ($ownedCount !== $itemIds->count()) {
            throw ValidationException::withMessages([
                'item_ids' => 'Um ou mais itens não pertencem a esta checklist.',
            ]);
        }

        $position = 1000.0;
        foreach ($itemIds as $itemId) {
            TaskChecklistItem::query()
                ->where('task_checklist_id', $checklist->id)
                ->where('id', $itemId)
                ->update(['position' => $position]);
            $position += 1000;
        }

        return back()->with('success', 'Ordem das etapas atualizada.');
    }
}
