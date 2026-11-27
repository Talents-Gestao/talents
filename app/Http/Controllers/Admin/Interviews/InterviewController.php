<?php

namespace App\Http\Controllers\Admin\Interviews;

use App\Enums\AdminPermissionModule;
use App\Enums\InterviewAnswerOrigin;
use App\Enums\InterviewStatus;
use App\Enums\InterviewTranscriptSource;
use App\Enums\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\HiringApplication;
use App\Models\HiringVacancy;
use App\Models\Interview;
use App\Models\InterviewQuestionnaire;
use App\Models\TalentCandidate;
use App\Models\User;
use App\Services\Interview\InterviewService;
use App\Services\Interview\InterviewTranscriptReader;
use Database\Seeders\InterviewQuestionnaireSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InterviewController extends Controller
{
    public function __construct(
        private readonly InterviewService $service,
    ) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));
        $applicationId = $request->integer('hiring_application_id') ?: null;
        $candidateId = $request->integer('talent_candidate_id') ?: null;

        $query = Interview::query()
            ->with(['questionnaire:id,name,version', 'createdBy:id,name', 'application.vacancy:id,title'])
            ->latest();

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status !== '' && InterviewStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $inner) use ($search, $operator): void {
                $inner->where('candidate_name', $operator, '%'.$search.'%')
                    ->orWhere('position_title', $operator, '%'.$search.'%');
            });
        }
        if ($applicationId !== null) {
            $query->where('hiring_application_id', $applicationId);
        }
        if ($candidateId !== null) {
            $query->where('talent_candidate_id', $candidateId);
        }

        $interviews = $query->paginate(15)->withQueryString()->through(fn (Interview $i) => [
            'id' => $i->id,
            'candidate_name' => $i->candidate_name,
            'position_title' => $i->position_title,
            'status' => $i->status->value,
            'status_label' => $i->status->label(),
            'is_processing' => $i->status->isProcessing(),
            'is_linked' => $i->isLinked(),
            'is_reviewed' => $i->isReviewed(),
            'deleted' => $i->trashed(),
            'source_label' => $i->source()->label(),
            'questionnaire' => $i->questionnaire?->only(['id', 'name', 'version']),
            'created_by' => $i->createdBy?->only(['id', 'name']),
            'created_at' => $i->created_at?->toIso8601String(),
            'finished_at' => $i->finished_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin/Interviews/Index', [
            'interviews' => $interviews,
            'filters' => [
                'status' => $status !== '' ? $status : null,
                'q' => $search !== '' ? $search : null,
                'hiring_application_id' => $applicationId,
                'talent_candidate_id' => $candidateId,
            ],
            'can' => $this->abilities($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        InterviewQuestionnaireSeeder::ensureDefault();

        $questionnaires = InterviewQuestionnaire::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderByDesc('version')
            ->get(['id', 'name', 'is_default', 'version']);

        // Atalho do quadro da vaga: candidato e candidatura já escolhidos (o usuário ainda confirma).
        $preselected = null;
        $application = HiringApplication::query()
            ->with(['candidate', 'vacancy.company:id,name'])
            ->find($request->integer('hiring_application_id') ?: 0);
        if ($application !== null && $application->candidate !== null && ! $application->candidate->isArchived()) {
            $preselected = $this->candidateOption($application->candidate);
            $preselected['application_id'] = $application->id;
        } elseif ($candidate = TalentCandidate::query()->active()->find($request->integer('talent_candidate_id') ?: 0)) {
            $preselected = $this->candidateOption($candidate);
        }

        return Inertia::render('Admin/Interviews/Create', [
            'questionnaires' => $questionnaires,
            'sources' => InterviewTranscriptSource::options(),
            'preselected' => $preselected,
            'ai_enabled' => InterviewService::aiEnabled(),
            'maxUploadMb' => (int) config('interview.max_upload_mb', 500),
            'maxTranscriptKb' => InterviewTranscriptReader::MAX_FILE_KB,
        ]);
    }

    /** Busca de candidatos ativos para vincular a entrevista (até 10), com as candidaturas de cada um. */
    public function candidates(Request $request): JsonResponse
    {
        $search = trim($request->string('q')->toString());
        $query = TalentCandidate::query()->active()->orderBy('name')->limit(10);

        if ($search !== '') {
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $digits = preg_replace('/\D+/', '', $search) ?? '';
            $query->where(function (Builder $q) use ($operator, $search, $digits): void {
                $q->where('name', $operator, '%'.$search.'%')
                    ->orWhere('email_normalized', 'like', '%'.mb_strtolower($search).'%');
                if (strlen($digits) >= 3) {
                    $q->orWhere('phone_digits', 'like', '%'.$digits.'%');
                }
            });
        }

        return response()->json([
            'data' => $query->get()->map(fn (TalentCandidate $c) => $this->candidateOption($c))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxKb = (int) config('interview.max_upload_mb', 500) * 1024;
        $source = InterviewTranscriptSource::tryFrom((string) $request->input('transcript_source'));

        $data = $request->validate([
            'questionnaire_id' => ['required', Rule::exists('interview_questionnaires', 'id')],
            'talent_candidate_id' => ['required', 'integer', Rule::exists('talent_candidates', 'id')],
            'hiring_application_id' => ['nullable', 'integer', Rule::exists('hiring_applications', 'id')],
            'transcript_source' => ['required', Rule::enum(InterviewTranscriptSource::class)],
            'audio' => [
                Rule::requiredIf($source === InterviewTranscriptSource::AudioWhisper),
                'nullable',
                'file',
                'max:'.$maxKb,
                'mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,audio/ogg,audio/webm,video/mp4,video/webm,application/octet-stream',
            ],
            'transcript_file' => [
                Rule::requiredIf($source === InterviewTranscriptSource::FileUpload),
                'nullable',
                'file',
                'max:'.InterviewTranscriptReader::MAX_FILE_KB,
                'extensions:txt,vtt',
            ],
            'transcript_text' => [
                Rule::requiredIf(in_array($source, [InterviewTranscriptSource::ManualText, InterviewTranscriptSource::Plaud], true)
                    && ! $request->hasFile('transcript_file')),
                'nullable',
                'string',
                'max:'.InterviewTranscriptReader::MAX_TEXT_CHARS,
            ],
            'confirm_candidate_id' => ['nullable'],
            'confirm_application_id' => ['nullable'],
        ], [
            'audio.required' => 'Envie o arquivo de áudio da entrevista.',
            'transcript_file.required' => 'Envie o arquivo TXT ou VTT.',
            'transcript_file.max' => 'O arquivo de transcrição pode ter até 2 MB.',
            'transcript_file.extensions' => 'Envie um arquivo TXT ou VTT.',
            'transcript_text.required' => 'Cole o texto da entrevista.',
        ], [
            'questionnaire_id' => 'roteiro',
            'talent_candidate_id' => 'candidato',
            'hiring_application_id' => 'vaga',
            'transcript_source' => 'fonte da transcrição',
        ]);

        $transcriptFile = $source?->isText() ? $request->file('transcript_file') : null;
        $audio = $source === InterviewTranscriptSource::AudioWhisper ? $request->file('audio') : null;

        $interview = $this->service->create($data, $audio, $transcriptFile, $request->user()?->id);

        $message = match (true) {
            $interview->status === InterviewStatus::Completed => 'Entrevista criada e pronta para preenchimento manual das respostas.',
            $interview->source()->isText() => 'Entrevista criada. As sugestões da IA serão geradas a partir do texto.',
            default => 'Entrevista enviada. O processamento pode levar alguns minutos.',
        };

        return redirect()->route('admin.entrevistas.show', $interview)->with('success', $message);
    }

    public function show(Request $request, Interview $interview): Response
    {
        $interview->load([
            'questionnaire.sections.questions',
            'answers',
            'company:id,name',
            'createdBy:id,name',
            'reviewedBy:id,name',
            'candidate:id,name,archived_at',
            'application.vacancy:id,title,company_id',
            'application.vacancy.company:id,name',
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
                        'reviewer_note' => $answer?->reviewer_note,
                        'origin' => $answer?->origin?->value,
                        'origin_label' => $answer?->origin?->label(),
                        'evidence_missing' => (bool) ($answer?->evidence_missing ?? false),
                    ];
                })->values(),
            ];
        })->values();

        $user = $request->user();
        $application = $interview->application;

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
                'transcript_source' => $interview->source()->value,
                'transcript_source_label' => $interview->source()->label(),
                'audio_size' => $interview->audio_size,
                'started_at' => $interview->started_at?->toIso8601String(),
                'finished_at' => $interview->finished_at?->toIso8601String(),
                'created_at' => $interview->created_at?->toIso8601String(),
                'deleted' => $interview->trashed(),
                'deleted_at' => $interview->deleted_at?->toIso8601String(),
                'reviewed_at' => $interview->reviewed_at?->toIso8601String(),
                'reviewed_by' => $interview->reviewedBy?->name,
                'has_unreviewed_ai' => ! $interview->isReviewed()
                    && $interview->answers->contains(fn ($a) => $a->origin === InterviewAnswerOrigin::AiSuggestion),
                'questionnaire' => $interview->questionnaire?->only(['id', 'name', 'version']),
                'company' => $interview->company?->only(['id', 'name']),
                'created_by' => $interview->createdBy?->only(['id', 'name']),
                'candidate' => $interview->candidate ? [
                    'id' => $interview->candidate->id,
                    'name' => $interview->candidate->name,
                    'url' => $user->canAccessAdmin(AdminPermissionModule::ContratacaoCandidatos, PermissionAction::View)
                        ? route('admin.contratacao.candidatos.show', $interview->candidate)
                        : null,
                ] : null,
                'application' => $application ? [
                    'id' => $application->id,
                    'vacancy_title' => $application->vacancy?->title,
                    'company_name' => $application->vacancy?->company?->name,
                    'url' => $application->vacancy && $user->canAccessAdmin(AdminPermissionModule::ContratacaoVagas, PermissionAction::View)
                        ? route('admin.contratacao.vagas.show', $application->vacancy)
                        : null,
                ] : null,
            ],
            'sections' => $sections,
            'can' => $this->abilities($user),
        ]);
    }

    public function link(Request $request, Interview $interview): RedirectResponse
    {
        $data = $request->validate([
            'talent_candidate_id' => ['required', 'integer', Rule::exists('talent_candidates', 'id')],
            'hiring_application_id' => ['nullable', 'integer', Rule::exists('hiring_applications', 'id')],
            'confirm_candidate_id' => ['nullable'],
            'confirm_application_id' => ['nullable'],
        ], [], ['talent_candidate_id' => 'candidato', 'hiring_application_id' => 'vaga']);

        $this->service->link($interview, $data);

        return back()->with('success', 'Entrevista vinculada ao candidato.');
    }

    public function saveAnswers(Request $request, Interview $interview): RedirectResponse
    {
        $data = $request->validate([
            'answers' => ['present', 'array', 'max:500'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable', 'string', 'max:10000'],
            'answers.*.raw_quote' => ['nullable', 'string', 'max:2000'],
            'answers.*.reviewer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->saveDraft($interview, $data['answers']);

        return back()->with('success', 'Rascunho salvo.');
    }

    public function concludeReview(Request $request, Interview $interview): RedirectResponse
    {
        $this->service->concludeReview($interview, $request->user()?->id);

        return back()->with('success', 'Revisão concluída.');
    }

    public function destroy(Interview $interview): RedirectResponse
    {
        $this->service->delete($interview);

        return redirect()
            ->route('admin.entrevistas.index')
            ->with('success', 'Entrevista excluída. Ela pode ser restaurada no filtro «Excluídas».');
    }

    public function restore(Interview $interview): RedirectResponse
    {
        $this->service->restore($interview);

        return redirect()
            ->route('admin.entrevistas.show', $interview)
            ->with('success', 'Entrevista restaurada.');
    }

    public function reprocess(Interview $interview): RedirectResponse
    {
        $this->service->reprocess($interview);

        return back()->with('success', 'Reprocessamento iniciado.');
    }

    /** Atalho do quadro da vaga: só abre entrevista da própria candidatura (scopeBindings). */
    public function showForApplication(HiringVacancy $vacancy, HiringApplication $application, Interview $interview): RedirectResponse
    {
        return redirect()->route('admin.entrevistas.show', $interview);
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateOption(TalentCandidate $candidate): array
    {
        $applications = HiringApplication::query()
            ->where('talent_candidate_id', $candidate->id)
            ->with(['vacancy:id,title,company_id', 'vacancy.company:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (HiringApplication $a) => [
                'id' => $a->id,
                'vacancy_title' => $a->vacancy?->title,
                'company_name' => $a->vacancy?->company?->name,
            ])
            ->values()
            ->all();

        return [
            'id' => $candidate->id,
            'name' => $candidate->name,
            'email' => $candidate->email,
            'current_role' => $candidate->current_role,
            'applications' => $applications,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(?User $user): array
    {
        $can = static fn (PermissionAction $a): bool => (bool) $user?->canAccessAdmin(AdminPermissionModule::EntrevistasIa, $a);

        return [
            'create' => $can(PermissionAction::Create),
            'edit' => $can(PermissionAction::Edit),
            'delete' => $can(PermissionAction::Delete),
        ];
    }
}
