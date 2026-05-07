<?php

namespace App\Http\Controllers\Admin\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TaskBoard;
use App\Support\Tasks\BoardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    public function index(Request $request): Response
    {
        $q = TaskBoard::query()
            ->with(['company:id,name'])
            ->orderByDesc('id');

        if ($request->filled('company_id')) {
            $q->where('company_id', (int) $request->input('company_id'));
        }

        if ($request->filled('scope')) {
            if ($request->input('scope') === 'internal') {
                $q->whereNull('company_id');
            } elseif ($request->input('scope') === 'company') {
                $q->whereNotNull('company_id');
            }
        }

        $boards = $q->paginate(20)->withQueryString();

        return Inertia::render('Admin/Tarefas/Quadros/Index', [
            'boards' => $boards,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['company_id', 'scope']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tarefas/Quadros/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cover_color' => ['nullable', 'string', 'max:32'],
        ]);

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'process_template_id' => null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'cover_color' => $data['cover_color'] ?? null,
            'is_archived' => false,
            'created_by_user_id' => $request->user()->id,
        ]);

        $board->lists()->create([
            'name' => 'A fazer',
            'position' => 1000,
            'visibility' => 'internal',
            'allow_company_drop_in' => false,
            'is_archived' => false,
        ]);

        return redirect()->route('admin.tarefas.quadros.show', $board)->with('success', 'Quadro interno criado.');
    }

    public function show(TaskBoard $board): Response
    {
        $payload = BoardPresenter::forAdmin($board);
        $activity = $board->activityLogs()->with('actor:id,name')->latest()->limit(50)->get()->map(fn ($row) => [
            'id' => $row->id,
            'action' => $row->action,
            'payload' => $row->payload,
            'created_at' => $row->created_at?->toIso8601String(),
            'actor' => $row->actor ? ['id' => $row->actor->id, 'name' => $row->actor->name] : null,
            'card_id' => $row->task_card_id,
        ]);

        $companyUsers = $board->company_id
            ? BoardPresenter::companyUsersForMentions($board->company_id)
            : collect();

        return Inertia::render('Admin/Tarefas/Quadros/Show', [
            'boardPayload' => $payload,
            'activity' => $activity,
            'companyUsers' => $companyUsers,
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

    public function destroy(TaskBoard $board): RedirectResponse
    {
        $board->delete();

        return redirect()->route('admin.tarefas.quadros.index')->with('success', 'Quadro removido.');
    }
}
