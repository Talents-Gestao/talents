<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Enums\TaskCardRecurrence;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TaskAttachment;
use App\Models\TaskBoard;
use App\Support\Tasks\BoardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    private function createDefaultLists(TaskBoard $board): void
    {
        foreach (
            [
                ['name' => 'A fazer', 'position' => 1000],
                ['name' => 'Em andamento', 'position' => 2000],
                ['name' => 'Concluído', 'position' => 3000],
            ] as $list
        ) {
            $board->lists()->create([
                'name' => $list['name'],
                'position' => $list['position'],
                'visibility' => 'company',
                'allow_company_drop_in' => true,
                'is_archived' => false,
            ]);
        }
    }

    private function ensureDefaultBoard(int $userId): TaskBoard
    {
        $board = TaskBoard::query()
            ->whereNull('company_id')
            ->where('is_archived', false)
            ->orderBy('id')
            ->first();

        if ($board) {
            return $board;
        }

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'process_template_id' => null,
            'name' => 'Quadro Único de Tarefas',
            'description' => 'Quadro central para todas as tarefas e empresas.',
            'cover_color' => null,
            'is_archived' => false,
            'created_by_user_id' => $userId,
        ]);

        $this->createDefaultLists($board);

        return $board;
    }

    public function index(Request $request): Response
    {
        if (TaskBoard::query()->where('is_archived', false)->doesntExist()) {
            $this->ensureDefaultBoard($request->user()->id);
        }

        $boards = TaskBoard::query()
            ->where('is_archived', false)
            ->orderByRaw('company_id is null desc')
            ->orderBy('name')
            ->get()
            ->map(fn (TaskBoard $board) => BoardPresenter::forAdminIndex($board));

        return Inertia::render('Admin/Tasks/Boards/Index', [
            'boards' => $boards,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tasks/Boards/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_color' => ['nullable', 'string', 'max:32'],
        ]);

        $cover = isset($data['cover_color']) ? trim($data['cover_color']) : null;
        if ($cover === '') {
            $cover = null;
        }

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'process_template_id' => null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'cover_color' => $cover,
            'is_archived' => false,
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->createDefaultLists($board);

        return redirect()
            ->route('admin.tarefas.quadros.show', $board)
            ->with('success', 'Quadro criado.');
    }

    public function show(Request $request, TaskBoard $board): Response
    {
        $includeArchived = $request->boolean('ver_arquivados');
        $payload = BoardPresenter::forAdmin($board, $includeArchived);
        $activity = $board->activityLogs()->with('actor:id,name')->latest()->limit(50)->get()->map(fn ($row) => [
            'id' => $row->id,
            'action' => $row->action,
            'payload' => $row->payload,
            'created_at' => $row->created_at?->toIso8601String(),
            'actor' => $row->actor ? ['id' => $row->actor->id, 'name' => $row->actor->name] : null,
            'card_id' => $row->task_card_id,
        ]);

        $companyUsers = BoardPresenter::allActiveCompanyUsers();
        $teamUsers = BoardPresenter::allActiveTalentsTeamUsers();
        $companies = Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Tasks/Boards/Show', [
            'boardPayload' => $payload,
            'activity' => $activity,
            'companyUsers' => $companyUsers,
            'teamUsers' => $teamUsers,
            'companies' => $companies,
            'visibilityListOptions' => [
                ['value' => 'internal', 'label' => 'Interno'],
                ['value' => 'company', 'label' => 'Empresa'],
            ],
            'visibilityCardOptions' => [
                ['value' => 'internal', 'label' => 'Interno'],
                ['value' => 'company', 'label' => 'Empresa'],
                ['value' => 'inherit', 'label' => 'Seguir a lista'],
            ],
            'recurrenceOptions' => array_map(
                fn (TaskCardRecurrence $r) => ['value' => $r->value, 'label' => $r->label()],
                TaskCardRecurrence::cases(),
            ),
        ]);
    }

    public function update(Request $request, TaskBoard $board): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cover_color' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        if (array_key_exists('cover_color', $data)) {
            $cover = $data['cover_color'] !== null ? trim($data['cover_color']) : null;
            $payload['cover_color'] = ($cover === '' || $cover === null) ? null : $cover;
        }

        if ($payload !== []) {
            $board->update($payload);
        }

        $message = array_key_exists('cover_color', $data)
            ? 'Quadro atualizado.'
            : 'Nome do quadro atualizado.';

        return back()->with('success', $message);
    }

    public function destroy(TaskBoard $board): RedirectResponse
    {
        $attachments = TaskAttachment::query()
            ->whereHas('card.list', fn ($q) => $q->where('board_id', $board->id))
            ->get(['id', 'disk', 'path']);

        foreach ($attachments as $attachment) {
            if (Storage::disk($attachment->disk)->exists($attachment->path)) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }
        }

        $board->delete();

        return redirect()
            ->route('admin.tarefas.quadros.index')
            ->with('success', 'Quadro excluído.');
    }
}
