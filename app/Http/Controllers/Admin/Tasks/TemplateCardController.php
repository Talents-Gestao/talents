<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskTemplateCard;
use App\Models\TaskTemplateList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TemplateCardController extends Controller
{
    public function store(Request $request, TaskTemplateList $template_list): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_visibility' => ['required', 'in:internal,company,inherit'],
            'default_due_offset_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'position' => ['nullable', 'numeric'],
        ]);

        $max = (float) $template_list->cards()->max('position');
        $data['position'] = $data['position'] ?? ($max + 1000);

        $template_list->cards()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'default_visibility' => $data['default_visibility'],
            'default_due_offset_days' => $data['default_due_offset_days'] ?? null,
            'position' => $data['position'],
        ]);

        return back()->with('success', 'Cartão modelo criado.');
    }

    public function update(Request $request, TaskTemplateCard $template_card): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_visibility' => ['sometimes', 'in:internal,company,inherit'],
            'default_due_offset_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'position' => ['sometimes', 'numeric'],
        ]);

        $template_card->update($data);

        return back()->with('success', 'Cartão modelo atualizado.');
    }

    public function destroy(TaskTemplateCard $template_card): RedirectResponse
    {
        $template_card->delete();

        return back()->with('success', 'Cartão modelo removido.');
    }
}
