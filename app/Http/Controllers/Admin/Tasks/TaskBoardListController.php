<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Actions\Tasks\LogTaskActivity;
use App\Http\Controllers\Controller;
use App\Models\TaskBoard;
use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskBoardListController extends Controller
{
    public function store(Request $request, TaskBoard $board, LogTaskActivity $log): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', 'in:internal,company'],
            'allow_company_drop_in' => ['boolean'],
            'position' => ['nullable', 'numeric'],
        ]);

        $max = (float) $board->lists()->max('position');
        $data['position'] = $data['position'] ?? ($max + 1000);
        $data['allow_company_drop_in'] = $data['allow_company_drop_in'] ?? true;

        $list = $board->lists()->create([
            'name' => $data['name'],
            'visibility' => $data['visibility'],
            'allow_company_drop_in' => $data['allow_company_drop_in'],
            'position' => $data['position'],
            'is_archived' => false,
        ]);

        $log->handle($board, null, 'list.created', $request->user(), ['list_id' => $list->id]);

        return back()->with('success', 'Lista criada.');
    }

    public function update(Request $request, TaskList $list, LogTaskActivity $log): RedirectResponse
    {
        $board = $list->board;
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'visibility' => ['sometimes', 'in:internal,company'],
            'allow_company_drop_in' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'numeric'],
        ]);

        $list->update($data);

        $log->handle($board, null, 'list.updated', $request->user(), ['list_id' => $list->id]);

        return back()->with('success', 'Lista atualizada.');
    }

    public function destroy(TaskList $list, LogTaskActivity $log): RedirectResponse
    {
        $board = $list->board;
        $list->delete();
        $log->handle($board, null, 'list.deleted', request()->user(), []);

        return back()->with('success', 'Lista removida.');
    }
}
