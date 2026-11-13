<?php

namespace App\Http\Controllers\Client\Tasks;

use App\Actions\Tasks\LogTaskActivity;
use App\Actions\Tasks\ToggleTaskCardCompletion;
use App\Http\Controllers\Controller;
use App\Models\TaskCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Portal: só título, descrição, datas e conclusão. Visibilidade, empresa, membros, etiquetas e repetição ficam no Admin.
 */
class CardController extends Controller
{
    public function update(
        Request $request,
        TaskCard $card,
        LogTaskActivity $log,
        ToggleTaskCardCompletion $toggleCompletion,
    ): RedirectResponse {
        $this->authorize('update', $card);

        if ($request->has('complete')) {
            $data = $request->validate([
                'complete' => ['required', 'boolean'],
            ]);

            $toggleCompletion->handle($card, (bool) $data['complete'], $request->user());

            return back()->with('success', $data['complete'] ? 'Tarefa concluída.' : 'Tarefa reaberta.');
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        if ($data !== []) {
            $card->update($data);
            $log->handle($card->list->board, $card, 'card.updated', $request->user(), ['fields' => array_keys($data)]);
        }

        return back()->with('success', 'Cartão atualizado.');
    }
}
