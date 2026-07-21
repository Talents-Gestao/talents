<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Http\Controllers\Controller;
use App\Models\TaskProcessTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProcessTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $q = TaskProcessTemplate::query()->orderByDesc('is_active')->orderBy('name');

        if ($request->boolean('only_active')) {
            $q->where('is_active', true);
        }

        $templates = $q->withCount(['lists', 'boards'])->paginate(20)->withQueryString();

        return Inertia::render('Admin/Tasks/Processes/Index', [
            'templates' => $templates,
            'filters' => $request->only(['only_active']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tasks/Processes/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cover_color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ]);

        $slug = Str::slug($data['name']);
        $base = $slug;
        $i = 1;
        while (TaskProcessTemplate::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        TaskProcessTemplate::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'cover_color' => $data['cover_color'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('admin.tarefas.processos.index')->with('success', 'Processo criado.');
    }

    public function edit(TaskProcessTemplate $template): Response
    {
        $template->load(['lists.cards']);

        return Inertia::render('Admin/Tasks/Processes/Edit', [
            'template' => $template,
            'visibilityListOptions' => [
                ['value' => 'internal', 'label' => 'Interno'],
                ['value' => 'company', 'label' => 'Empresa'],
            ],
            'visibilityCardOptions' => [
                ['value' => 'internal', 'label' => 'Interno'],
                ['value' => 'company', 'label' => 'Empresa'],
                ['value' => 'inherit', 'label' => 'Seguir a lista'],
            ],
        ]);
    }

    public function update(Request $request, TaskProcessTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('task_process_templates', 'slug')->ignore($template->id)],
            'description' => ['nullable', 'string'],
            'cover_color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ]);

        $template->update($data);

        return redirect()->route('admin.tarefas.processos.edit', $template)->with('success', 'Processo atualizado.');
    }

    public function destroy(TaskProcessTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('admin.tarefas.processos.index')->with('success', 'Processo removido.');
    }
}
