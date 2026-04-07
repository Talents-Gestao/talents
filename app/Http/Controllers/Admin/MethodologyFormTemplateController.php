<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MethodologyFormQuestion;
use App\Models\MethodologyFormSection;
use App\Models\MethodologyFormTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MethodologyFormTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = MethodologyFormTemplate::query()
            ->withCount(['sections', 'companies'])
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('Admin/Methodology/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Methodology/Templates/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->validationRules());

        $this->persistTemplate(new MethodologyFormTemplate, $data);

        return redirect()->route('admin.methodology-templates.index')->with('success', 'Template criado.');
    }

    public function show(MethodologyFormTemplate $template): Response
    {
        $template->load(['sections.questions']);

        return Inertia::render('Admin/Methodology/Templates/Show', [
            'template' => $template,
        ]);
    }

    public function edit(MethodologyFormTemplate $template): Response
    {
        $template->load(['sections.questions']);

        return Inertia::render('Admin/Methodology/Templates/Edit', [
            'template' => $template,
        ]);
    }

    public function update(Request $request, MethodologyFormTemplate $template): RedirectResponse
    {
        $data = $request->validate($this->validationRules(true));

        DB::transaction(function () use ($template, $data) {
            $template->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'step_number' => (int) ($data['step_number'] ?? 2),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $template->sections()->delete();

            $this->createSectionsAndQuestions($template, $data['sections']);
        });

        return redirect()->route('admin.methodology-templates.index')->with('success', 'Template atualizado.');
    }

    public function destroy(MethodologyFormTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('admin.methodology-templates.index')->with('success', 'Template removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(bool $isUpdate = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'step_number' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.questions' => ['required', 'array', 'min:1'],
            'sections.*.questions.*.body' => ['required', 'string'],
            'sections.*.questions.*.type' => ['required', 'string', 'in:scale,text'],
            'sections.*.questions.*.is_required' => ['boolean'],
            'sections.*.questions.*.scale_min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'sections.*.questions.*.scale_max' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];

        if ($isUpdate) {
            $rules['is_active'] = ['boolean'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistTemplate(MethodologyFormTemplate $template, array $data): void
    {
        DB::transaction(function () use ($template, $data) {
            $template->fill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'step_number' => (int) ($data['step_number'] ?? 2),
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);
            $template->save();

            $this->createSectionsAndQuestions($template, $data['sections']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function createSectionsAndQuestions(MethodologyFormTemplate $template, array $sections): void
    {
        foreach ($sections as $sIndex => $section) {
            $sec = MethodologyFormSection::create([
                'methodology_form_template_id' => $template->id,
                'title' => $section['title'],
                'description' => $section['description'] ?? null,
                'sort_order' => $sIndex,
            ]);

            foreach ($section['questions'] as $qIndex => $question) {
                $type = $question['type'] ?? 'scale';
                MethodologyFormQuestion::create([
                    'methodology_form_section_id' => $sec->id,
                    'body' => $question['body'],
                    'type' => $type,
                    'is_required' => $question['is_required'] ?? true,
                    'scale_min' => $type === 'scale' ? (int) ($question['scale_min'] ?? 0) : 0,
                    'scale_max' => $type === 'scale' ? (int) ($question['scale_max'] ?? 5) : 5,
                    'sort_order' => $qIndex,
                ]);
            }
        }
    }
}
