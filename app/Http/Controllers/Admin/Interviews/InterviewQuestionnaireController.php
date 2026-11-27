<?php

namespace App\Http\Controllers\Admin\Interviews;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\InterviewQuestionnaire;
use App\Models\InterviewQuestionnaireQuestion;
use App\Models\InterviewQuestionnaireSection;
use Database\Seeders\InterviewQuestionnaireSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InterviewQuestionnaireController extends Controller
{
    public function index(Request $request): Response
    {
        InterviewQuestionnaireSeeder::ensureDefault();

        $questionnaires = InterviewQuestionnaire::query()
            ->withCount(['sections', 'interviews'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderByDesc('version')
            ->get()
            ->map(fn (InterviewQuestionnaire $q) => [
                'id' => $q->id,
                'name' => $q->name,
                'description' => $q->description,
                'is_default' => $q->is_default,
                'version' => $q->version,
                'locked' => $q->isLocked(),
                'sections_count' => $q->sections_count,
                'interviews_count' => $q->interviews_count,
                'updated_at' => $q->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Interviews/Scripts/Index', [
            'questionnaires' => $questionnaires,
            'can' => $this->abilities($request),
        ]);
    }

    /**
     * «Duplicar como nova versão» cria um roteiro: só aparece (e só passa na rota) com entrevistas_roteiros:create.
     *
     * @return array{duplicate: bool}
     */
    private function abilities(Request $request): array
    {
        return [
            'duplicate' => (bool) $request->user()?->canAccessAdmin(
                AdminPermissionModule::EntrevistasRoteiros,
                PermissionAction::Create,
            ),
        ];
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

    public function edit(Request $request, InterviewQuestionnaire $questionnaire): Response
    {
        $questionnaire->load(['sections.questions']);

        return Inertia::render('Admin/Interviews/Scripts/Edit', [
            'can' => $this->abilities($request),
            'questionnaire' => [
                'id' => $questionnaire->id,
                'name' => $questionnaire->name,
                'description' => $questionnaire->description,
                'is_default' => $questionnaire->is_default,
                'version' => $questionnaire->version,
                'locked' => $questionnaire->isLocked(),
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

        $locked = $questionnaire->isLocked();
        if ($locked && ! $this->sameStructure($questionnaire, $data['sections'])) {
            // Apagar pergunta apagaria as respostas em cascata: roteiro com entrevistas vira nova versão.
            throw ValidationException::withMessages([
                'sections' => 'Este roteiro já tem entrevistas: seções e perguntas não podem ser alteradas nem removidas. Use «Duplicar como nova versão».',
            ]);
        }

        DB::transaction(function () use ($data, $questionnaire, $isDefault, $locked) {
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

            if ($locked) {
                return;
            }

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

        if ($questionnaire->isLocked()) {
            return back()->with('error', 'Este roteiro possui entrevistas vinculadas.');
        }

        $questionnaire->delete();

        return redirect()
            ->route('admin.entrevistas.roteiros.index')
            ->with('success', 'Roteiro excluído.');
    }

    /**
     * «Duplicar como nova versão»: copia seções e perguntas (mesmas chaves) para um roteiro novo,
     * na mesma família de versões. A cópia não é padrão e pode ser editada livremente até ter entrevistas.
     */
    public function duplicate(Request $request, InterviewQuestionnaire $questionnaire): RedirectResponse
    {
        $questionnaire->load('sections.questions');

        $copy = DB::transaction(function () use ($request, $questionnaire): InterviewQuestionnaire {
            $rootId = $questionnaire->rootId();
            $nextVersion = 1 + (int) InterviewQuestionnaire::query()
                ->where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_id', $rootId))
                ->max('version');

            $baseName = preg_replace('/\s+\(v\d+\)$/u', '', $questionnaire->name) ?? $questionnaire->name;

            $copy = InterviewQuestionnaire::query()->create([
                'name' => mb_substr($baseName, 0, 245).' (v'.$nextVersion.')',
                'description' => $questionnaire->description,
                'is_default' => false,
                'version' => $nextVersion,
                'parent_id' => $rootId,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($questionnaire->sections as $section) {
                $newSection = InterviewQuestionnaireSection::query()->create([
                    'questionnaire_id' => $copy->id,
                    'title' => $section->title,
                    'position' => $section->position,
                ]);
                foreach ($section->questions as $question) {
                    InterviewQuestionnaireQuestion::query()->create([
                        'section_id' => $newSection->id,
                        'question_key' => $question->question_key,
                        'text' => $question->text,
                        'position' => $question->position,
                    ]);
                }
            }

            return $copy;
        });

        return redirect()
            ->route('admin.entrevistas.roteiros.edit', $copy)
            ->with('success', 'Nova versão criada. As entrevistas existentes continuam no roteiro anterior.');
    }

    /**
     * Seções e perguntas enviadas iguais às atuais (mesma ordem, títulos e textos).
     *
     * @param  list<array{title: string, questions: list<array{text: string}>}>  $sections
     */
    private function sameStructure(InterviewQuestionnaire $questionnaire, array $sections): bool
    {
        $questionnaire->loadMissing('sections.questions');
        $norm = static fn (?string $v): string => trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');

        $current = $questionnaire->sections->map(fn ($section) => [
            'title' => $norm($section->title),
            'questions' => $section->questions->map(fn ($q) => $norm($q->text))->values()->all(),
        ])->values()->all();

        $given = array_map(static fn (array $section) => [
            'title' => $norm($section['title'] ?? ''),
            'questions' => array_values(array_map(static fn (array $q) => $norm($q['text'] ?? ''), $section['questions'] ?? [])),
        ], array_values($sections));

        return $current === $given;
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
