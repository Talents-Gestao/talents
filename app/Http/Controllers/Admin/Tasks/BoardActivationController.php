<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Actions\Tasks\InstantiateProcessTemplateForCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TaskProcessTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardActivationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Tasks/Boards/Activate', [
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'templates' => TaskProcessTemplate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request, TaskProcessTemplate $template, InstantiateProcessTemplateForCompany $instantiate): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'board_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $template->is_active) {
            return back()->with('error', 'Este modelo de processo está inativo.');
        }

        $company = Company::query()->findOrFail($data['company_id']);

        $board = $instantiate->handle(
            $template,
            $company,
            $request->user(),
            $data['board_name'] ?? null,
        );

        return redirect()->route('admin.tarefas.quadros.show', $board)->with('success', 'Processo ativado para a empresa.');
    }
}
