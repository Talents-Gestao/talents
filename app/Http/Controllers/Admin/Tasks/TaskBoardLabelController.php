<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskBoard;
use App\Models\TaskLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskBoardLabelController extends Controller
{
    public function store(Request $request, TaskBoard $board): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'color' => ['required', 'string', 'max:32'],
            'position' => ['nullable', 'numeric'],
        ]);

        $max = (float) $board->labels()->max('position');
        $data['position'] = $data['position'] ?? ($max + 1000);

        $board->labels()->create([
            'name' => $data['name'] ?? null,
            'color' => $data['color'],
            'position' => $data['position'],
        ]);

        return back()->with('success', 'Etiqueta criada.');
    }

    public function update(Request $request, TaskLabel $label): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'color' => ['sometimes', 'string', 'max:32'],
            'position' => ['sometimes', 'numeric'],
        ]);

        $label->update($data);

        return back()->with('success', 'Etiqueta atualizada.');
    }

    public function destroy(TaskLabel $label): RedirectResponse
    {
        $label->delete();

        return back()->with('success', 'Etiqueta removida.');
    }
}
