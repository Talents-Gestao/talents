<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskCard;
use App\Models\TaskChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskBoardChecklistController extends Controller
{
    public function store(Request $request, TaskCard $card): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'numeric'],
            'items' => ['nullable', 'array'],
            'items.*' => ['required', 'string', 'max:2000'],
        ]);

        $max = (float) $card->checklists()->max('position');
        $data['position'] = $data['position'] ?? ($max + 1000);

        $checklist = $card->checklists()->create([
            'name' => $data['name'],
            'position' => $data['position'],
        ]);

        $items = collect($data['items'] ?? [])
            ->map(fn ($text) => trim((string) $text))
            ->filter()
            ->values();

        if ($items->isNotEmpty()) {
            $itemPosition = (float) $checklist->items()->max('position');
            foreach ($items as $text) {
                $itemPosition += 1000;
                $checklist->items()->create([
                    'text' => $text,
                    'position' => $itemPosition,
                ]);
            }
        }

        return back()->with('success', 'Checklist criada.');
    }

    public function update(Request $request, TaskChecklist $checklist): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $checklist->update(['name' => $data['name']]);
        }

        if (array_key_exists('is_completed', $data)) {
            $checklist->update([
                'is_completed' => (bool) $data['is_completed'],
            ]);

            if ($checklist->items()->exists()) {
                $checklist->items()->update([
                    'is_completed' => (bool) $data['is_completed'],
                ]);
            }
        }

        return back()->with('success', 'Checklist atualizada.');
    }

    public function destroy(TaskChecklist $checklist): RedirectResponse
    {
        $checklist->delete();

        return back()->with('success', 'Checklist removida.');
    }
}
