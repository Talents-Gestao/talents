<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function update(Request $request, TaskCard $card): RedirectResponse
    {
        $this->authorize('update', $card);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'position' => ['sometimes', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ]);

        if ($data !== []) {
            $card->update($data);
        }

        return back()->with('success', 'Cartão atualizado.');
    }
}
