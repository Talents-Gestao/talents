<?php

namespace App\Http\Controllers\Admin\Interviews;

use App\Enums\InterviewStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessInterviewAudioJob;
use App\Models\Company;
use App\Models\Interview;
use App\Models\InterviewQuestionnaire;
use Database\Seeders\InterviewQuestionnaireSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InterviewController extends Controller
{
    public function index(Request $request): Response
    {
        $interviews = Interview::query()
            ->with(['questionnaire:id,name', 'createdBy:id,name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('candidate_name', 'ilike', '%'.$search.'%')
                        ->orWhere('position_title', 'ilike', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Interview $i) => [
                'id' => $i->id,
                'candidate_name' => $i->candidate_name,
                'position_title' => $i->position_title,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'is_processing' => $i->status->isProcessing(),
                'questionnaire' => $i->questionnaire?->only(['id', 'name']),
                'created_by' => $i->createdBy?->only(['id', 'name']),
                'created_at' => $i->created_at?->toIso8601String(),
                'finished_at' => $i->finished_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Interviews/Index', [
            'interviews' => $interviews,
            'filters' => [
                'status' => $request->query('status'),
                'q' => $request->query('q'),
            ],
        ]);
    }

    public function create(): Response
    {
        InterviewQuestionnaireSeeder::ensureDefault();

        $questionnaires = InterviewQuestionnaire::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Interviews/Create', [
            'questionnaires' => $questionnaires,
            'companies' => $companies,
            'maxUploadMb' => (int) config('interview.max_upload_mb', 500),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxKb = (int) config('interview.max_upload_mb', 500) * 1024;

        $data = $request->validate([
            'candidate_name' => ['required', 'string', 'max:255'],
            'position_title' => ['nullable', 'string', 'max:255'],
            'questionnaire_id' => ['required', Rule::exists('interview_questionnaires', 'id')],
            'company_id' => ['nullable', Rule::exists('companies', 'id')],
            'audio' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,audio/ogg,audio/webm,video/mp4,video/webm,application/octet-stream',
            ],
        ]);

        $file = $request->file('audio');
        $interview = Interview::query()->create([
            'questionnaire_id' => $data['questionnaire_id'],
            'candidate_name' => $data['candidate_name'],
            'position_title' => $data['position_title'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'status' => InterviewStatus::Queued,
            'audio_mime' => $file->getMimeType(),
            'audio_size' => $file->getSize(),
            'created_by' => $request->user()->id,
        ]);

        $extension = $file->getClientOriginalExtension() ?: 'mp3';
        $path = $file->storeAs(
            'private/interviews/'.$interview->id,
            'audio.'.$extension,
            'local'
        );

        $interview->update(['audio_path' => $path]);

        ProcessInterviewAudioJob::dispatch($interview->id);

        return redirect()
            ->route('admin.entrevistas.show', $interview)
            ->with('success', 'Entrevista enviada. O processamento pode levar alguns minutos.');
    }

    public function show(Interview $interview): Response
    {
        $interview->load([
            'questionnaire.sections.questions',
            'answers.question.section',
            'company:id,name',
            'createdBy:id,name',
        ]);

        $answersByQuestionId = $interview->answers->keyBy('question_id');

        $sections = $interview->questionnaire->sections->map(function ($section) use ($answersByQuestionId) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'questions' => $section->questions->map(function ($question) use ($answersByQuestionId) {
                    $answer = $answersByQuestionId->get($question->id);

                    return [
                        'id' => $question->id,
                        'text' => $question->text,
                        'answer' => $answer?->answer,
                        'raw_quote' => $answer?->raw_quote,
                    ];
                })->values(),
            ];
        })->values();

        return Inertia::render('Admin/Interviews/Show', [
            'interview' => [
                'id' => $interview->id,
                'candidate_name' => $interview->candidate_name,
                'position_title' => $interview->position_title,
                'status' => $interview->status->value,
                'status_label' => $interview->status->label(),
                'is_processing' => $interview->status->isProcessing(),
                'failure_reason' => $interview->failure_reason,
                'transcript_text' => $interview->transcript_text,
                'audio_size' => $interview->audio_size,
                'started_at' => $interview->started_at?->toIso8601String(),
                'finished_at' => $interview->finished_at?->toIso8601String(),
                'created_at' => $interview->created_at?->toIso8601String(),
                'questionnaire' => $interview->questionnaire?->only(['id', 'name']),
                'company' => $interview->company?->only(['id', 'name']),
                'created_by' => $interview->createdBy?->only(['id', 'name']),
            ],
            'sections' => $sections,
        ]);
    }

    public function destroy(Interview $interview): RedirectResponse
    {
        if ($interview->audio_path) {
            Storage::disk('local')->delete($interview->audio_path);
        }

        $interview->delete();

        return redirect()
            ->route('admin.entrevistas.index')
            ->with('success', 'Entrevista removida.');
    }

    public function reprocess(Interview $interview): RedirectResponse
    {
        if (! $interview->audio_path || ! Storage::disk('local')->exists($interview->audio_path)) {
            return back()->with('error', 'Arquivo de áudio não disponível para reprocessamento.');
        }

        $interview->update([
            'status' => InterviewStatus::Queued,
            'failure_reason' => null,
            'transcript_text' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        $interview->answers()->delete();

        ProcessInterviewAudioJob::dispatch($interview->id);

        return back()->with('success', 'Reprocessamento iniciado.');
    }
}
