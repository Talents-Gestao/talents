<?php

namespace App\Http\Controllers\Admin\Interviews;

use App\Http\Controllers\Controller;
use App\Models\InterviewQuestionnaire;
use App\Models\InterviewQuestionnaireQuestion;
use App\Models\InterviewQuestionnaireSection;
use Database\Seeders\InterviewQuestionnaireSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InterviewQuestionnaireController extends Controller
{
    public function index(): Response
    {
        InterviewQuestionnaireSeeder::ensureDefault();

        $questionnaires = InterviewQuestionnaire::query()
            ->withCount(['sections', 'interviews'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (InterviewQuestionnaire $q) => [
                'id' => $q->id,
                'name' => $q->name,
                'description' => $q->description,
                'is_default' => $q->is_default,
                'sections_count' => $q->sections_count,
                'interviews_count' => $q->interviews_count,
                'updated_at' => $q->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Interviews/Scripts/Index', [
            'questionnaires' => $questionnaires,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Interviews/Scripts/Edit', [
            'questionnaire' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($request, $data, $isDefault) {
            if ($isDefault) {
                InterviewQuestionnaire::query()->update(['is_default' => false]);
            }

            $questionnaire = InterviewQuestionnaire::query()->create([
                'name' => $data['name'],
                'description' => $data['description'],
                'is_default' => $isDefault,
                'created_by' => $request->user()->id,
            ]);

            $this->syncSections($questionnaire, $data['sections']);
        });

        return redirect()
            ->route('admin.entrevistas.roteiros.index')
            ->with('success', 'Roteiro criado.');
    }

    public function edit(InterviewQuestionnaire $questionnaire): Response
    {
        $questionnaire->load(['sections.questions']);

        return Inertia::render('Admin/Interviews/Scripts/Edit', [
            'questionnaire' => [
                'id' => $questionnaire->id,
                'name' => $questionnaire->name,
                'description' => $questionnaire->description,
                'is_default' => $questionnaire->is_default,
                'sections' => $questionnaire->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'position' => $section->position,
                    'questions' => $section->questions->map(fn ($q) => [
                        'id' => $q->id,
                        'question_key' => $q->question_key,
                        'text' => $q->text,
                        'position' => $q->position,
                    ])->values(),
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, InterviewQuestionnaire $questionnaire): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($data, $questionnaire, $isDefault) {
            if ($isDefault) {
                InterviewQuestionnaire::query()
                    ->where('id', '!=', $questionnaire->id)
                    ->update(['is_default' => false]);
            }

            $questionnaire->update([
                'name' => $data['name'],
                'description' => $data['description'],
                'is_default' => $isDefault,
            ]);

            $questionnaire->sections()->each(function (InterviewQuestionnaireSection $section) {
                $section->questions()->delete();
            });
            $questionnaire->sections()->delete();

            $this->syncSections($questionnaire, $data['sections']);
        });

        return redirect()
            ->route('admin.entrevistas.roteiros.index')
            ->with('success', 'Roteiro atualizado.');
    }

    public function destroy(InterviewQuestionnaire $questionnaire): RedirectResponse
    {
        if ($questionnaire->is_default) {
            return back()->with('error', 'Não é possível excluir o roteiro padrão.');
        }

        if ($questionnaire->interviews()->exists()) {
            return back()->with('error', 'Este roteiro possui entrevistas vinculadas.');
        }

        $questionnaire->delete();

        return redirect()
            ->route('admin.entrevistas.roteiros.index')
            ->with('success', 'Roteiro excluído.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.questions' => ['required', 'array', 'min:1'],
            'sections.*.questions.*.text' => ['required', 'string', 'max:2000'],
            'sections.*.questions.*.question_key' => ['nullable', 'string', 'max:128'],
        ]);
    }

    /**
     * @param  list<array{title: string, questions: list<array{text: string, question_key?: string|null}>}>  $sections
     */
    private function syncSections(InterviewQuestionnaire $questionnaire, array $sections): void
    {
        foreach ($sections as $sectionIndex => $sectionData) {
            $section = InterviewQuestionnaireSection::query()->create([
                'questionnaire_id' => $questionnaire->id,
                'title' => $sectionData['title'],
                'position' => $sectionIndex + 1,
            ]);

            foreach ($sectionData['questions'] as $questionIndex => $questionData) {
                $key = $questionData['question_key'] ?? null;
                if (! is_string($key) || $key === '') {
                    $key = 'q_'.Str::slug(Str::limit($questionData['text'], 40, ''), '_').'_'.($sectionIndex + 1).'_'.($questionIndex + 1);
                }

                InterviewQuestionnaireQuestion::query()->create([
                    'section_id' => $section->id,
                    'question_key' => $key,
                    'text' => $questionData['text'],
                    'position' => $questionIndex + 1,
                ]);
            }
        }
    }
}
