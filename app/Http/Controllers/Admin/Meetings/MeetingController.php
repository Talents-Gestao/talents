<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Meetings;

use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessMeetingAudioJob;
use App\Models\Company;
use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function index(Request $request): Response
    {
        $meetings = Meeting::query()
            ->with(['company:id,name', 'createdBy:id,name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'ilike', '%'.$search.'%')
                        ->orWhere('participants_text', 'ilike', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'status' => $m->status->value,
                'status_label' => $m->status->label(),
                'is_processing' => $m->status->isProcessing(),
                'company' => $m->company?->only(['id', 'name']),
                'created_by' => $m->createdBy?->only(['id', 'name']),
                'created_at' => $m->created_at?->toIso8601String(),
                'finished_at' => $m->finished_at?->toIso8601String(),
                'has_audio' => (bool) $m->audio_path,
            ]);

        return Inertia::render('Admin/Meetings/Index', [
            'meetings' => $meetings,
            'filters' => [
                'status' => $request->query('status'),
                'q' => $request->query('q'),
            ],
        ]);
    }

    public function create(): Response
    {
        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Meetings/Create', [
            'companies' => $companies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'company_id' => ['nullable', Rule::exists('companies', 'id')],
            'participants_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $meeting = Meeting::query()->create([
            'title' => $data['title'],
            'company_id' => $data['company_id'] ?? null,
            'participants_text' => $data['participants_text'] ?? null,
            'status' => MeetingStatus::Draft,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.reunioes.show', $meeting)
            ->with('success', 'Reunião criada. Grave o áudio para gerar a ata.');
    }

    public function show(Meeting $meeting): Response
    {
        $meeting->load(['company:id,name', 'createdBy:id,name']);

        return Inertia::render('Admin/Meetings/Show', [
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'participants_text' => $meeting->participants_text,
                'status' => $meeting->status->value,
                'status_label' => $meeting->status->label(),
                'is_processing' => $meeting->status->isProcessing(),
                'can_receive_audio' => $meeting->status->canReceiveAudio(),
                'failure_reason' => $meeting->failure_reason,
                'transcript_text' => $meeting->transcript_text,
                'minutes_text' => $meeting->minutes_text,
                'audio_size' => $meeting->audio_size,
                'duration_seconds' => $meeting->duration_seconds,
                'has_audio' => (bool) $meeting->audio_path,
                'started_at' => $meeting->started_at?->toIso8601String(),
                'finished_at' => $meeting->finished_at?->toIso8601String(),
                'created_at' => $meeting->created_at?->toIso8601String(),
                'company' => $meeting->company?->only(['id', 'name']),
                'created_by' => $meeting->createdBy?->only(['id', 'name']),
            ],
            'maxUploadMb' => (int) config('meeting.max_upload_mb', 500),
        ]);
    }

    public function storeAudio(Request $request, Meeting $meeting): RedirectResponse
    {
        if (! $meeting->status->canReceiveAudio()) {
            return back()->with('error', 'Não é possível enviar áudio enquanto o processamento está em andamento.');
        }

        $maxKb = (int) config('meeting.max_upload_mb', 500) * 1024;

        $data = $request->validate([
            'audio' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,audio/ogg,audio/webm,video/webm,application/octet-stream',
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        if ($meeting->audio_path) {
            Storage::disk('local')->delete($meeting->audio_path);
        }

        $file = $request->file('audio');
        $extension = $file->getClientOriginalExtension() ?: 'webm';

        $path = $file->storeAs(
            'private/meetings/'.$meeting->id,
            'audio.'.$extension,
            'local'
        );

        $meeting->update([
            'status' => MeetingStatus::Queued,
            'audio_path' => $path,
            'audio_mime' => $file->getMimeType(),
            'audio_size' => $file->getSize(),
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'transcript_text' => null,
            'minutes_text' => null,
            'failure_reason' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        ProcessMeetingAudioJob::dispatch($meeting->id);

        return redirect()
            ->route('admin.reunioes.show', $meeting)
            ->with('success', 'Áudio enviado. A ata será gerada em alguns minutos.');
    }

    public function updateMinutes(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'minutes_text' => ['required', 'string', 'max:100000'],
        ]);

        $meeting->update([
            'minutes_text' => $data['minutes_text'],
        ]);

        return back()->with('success', 'Ata atualizada.');
    }

    public function reprocess(Meeting $meeting): RedirectResponse
    {
        if (! $meeting->audio_path || ! Storage::disk('local')->exists($meeting->audio_path)) {
            return back()->with('error', 'Arquivo de áudio não disponível para reprocessamento.');
        }

        if ($meeting->status->isProcessing()) {
            return back()->with('error', 'O processamento ainda está em andamento.');
        }

        $meeting->update([
            'status' => MeetingStatus::Queued,
            'failure_reason' => null,
            'transcript_text' => null,
            'minutes_text' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        ProcessMeetingAudioJob::dispatch($meeting->id);

        return back()->with('success', 'Reprocessamento iniciado.');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        if ($meeting->audio_path) {
            Storage::disk('local')->delete($meeting->audio_path);
            Storage::disk('local')->deleteDirectory('private/meetings/'.$meeting->id);
        }

        $meeting->delete();

        return redirect()
            ->route('admin.reunioes.index')
            ->with('success', 'Reunião removida.');
    }
}
