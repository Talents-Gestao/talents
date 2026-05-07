<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskProcessTemplate;
use App\Models\TaskTemplateList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TemplateListController extends Controller
{
    public function store(Request $request, TaskProcessTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'default_visibility' => ['required', 'in:internal,company'],
            'allow_company_drop_in' => ['boolean'],
            'position' => ['nullable', 'numeric'],
        ]);

        $max = (float) $template->lists()->max('position');
        $data['position'] = $data['position'] ?? ($max + 1000);
        $data['allow_company_drop_in'] = $data['allow_company_drop_in'] ?? true;

        $template->lists()->create([
            'name' => $data['name'],
            'default_visibility' => $data['default_visibility'],
            'allow_company_drop_in' => $data['allow_company_drop_in'],
            'position' => $data['position'],
        ]);

        return back()->with('success', 'Lista adicionada ao modelo.');
    }

    public function update(Request $request, TaskTemplateList $template_list): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'default_visibility' => ['sometimes', 'in:internal,company'],
            'allow_company_drop_in' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'numeric'],
        ]);

        $template_list->update($data);

        return back()->with('success', 'Lista atualizada.');
    }

    public function destroy(TaskTemplateList $template_list): RedirectResponse
    {
        $template_list->delete();

        return back()->with('success', 'Lista removida.');
    }
}
