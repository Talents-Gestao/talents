<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoardFavoriteController extends Controller
{
    public function store(Request $request, TaskBoard $board): RedirectResponse
    {
        DB::table('task_board_user_favorites')->updateOrInsert(
            ['board_id' => $board->id, 'user_id' => $request->user()->id],
            ['updated_at' => now(), 'created_at' => now()],
        );

        return back();
    }

    public function destroy(Request $request, TaskBoard $board): RedirectResponse
    {
        DB::table('task_board_user_favorites')
            ->where('board_id', $board->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back();
    }
}
