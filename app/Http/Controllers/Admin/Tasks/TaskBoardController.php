<?php

namespace App\Http\Controllers\Admin\Tasks;

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
    private function getOrCreateSingleBoard(int $userId): TaskBoard
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

        $board->lists()->create([
            'name' => 'A fazer',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);
        $board->lists()->create([
            'name' => 'Em andamento',
            'position' => 2000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);
        $board->lists()->create([
            'name' => 'Concluído',
            'position' => 3000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        return $board;
    }

    public function index(Request $request): RedirectResponse
    {
        $board = $this->getOrCreateSingleBoard($request->user()->id);

        return redirect()->route('admin.tarefas.quadros.show', $board);
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

        $companyUsers = BoardPresenter::allActiveCompanyUsers();
        $teamUsers = BoardPresenter::allActiveTalentsTeamUsers();
        $companies = Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Tarefas/Quadros/Show', [
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
        ]);
    }

    public function update(Request $request, TaskBoard $board): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $board->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Nome do quadro atualizado.');
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
            ->route('admin.tarefas.processos.index')
            ->with('success', 'Quadro excluído.');
    }
}
