<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\HiringProcessStage;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\HiringProcessComment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AcompanhamentoController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = $this->requireCompany($user);

        $stageFilter = $request->string('stage')->toString();
        $activeStage = HiringProcessStage::tryFrom($stageFilter) ?? HiringProcessStage::EngenhariaCargo;
        $search = trim($request->string('q')->toString());

        $baseQuery = HiringProcess::query()->where('company_id', $company->id);
        if ($search !== '') {
            $this->applySearchFilter($baseQuery, $search);
        }

        $rawCounts = (clone $baseQuery)
            ->selectRaw('current_stage, count(*) as aggregate')
            ->groupBy('current_stage')
            ->pluck('aggregate', 'current_stage');

        $stageCounts = [];
        foreach (HiringProcessStage::ordered() as $stage) {
            $stageCounts[$stage->value] = (int) ($rawCounts[$stage->value] ?? 0);
        }

        $processes = (clone $baseQuery)
            ->with([
                'company:id,name',
                'updatedByUser:id,name',
                'comments.user:id,name,role',
            ])
            ->where('current_stage', $activeStage->value)
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (HiringProcess $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'notes' => $p->notes,
                'notes_at' => $p->notes_at?->toIso8601String(),
                'candidates_count' => $p->candidates_count,
                'current_stage' => $p->current_stage->value,
                'current_stage_label' => $p->current_stage->label(),
                'company' => $p->company ? [
                    'id' => $p->company->id,
                    'name' => $p->company->name,
                ] : null,
                'updated_by_name' => $p->updatedByUser?->name,
                'updated_at' => $p->updated_at?->toIso8601String(),
                'can_advance' => $p->current_stage->next() !== null,
                'can_retreat' => $p->current_stage->previous() !== null,
                'comments' => $p->comments->map(fn (HiringProcessComment $c) => $c->toFrontend())->values()->all(),
            ]);

        return Inertia::render('Client/HiringFollowUp/Index', [
            'stages' => HiringProcessStage::options(),
            'active_stage' => $activeStage->value,
            'stage_counts' => $stageCounts,
            'processes' => $processes,
            'company_name' => $company->name,
            'can_create' => $user->canAccess(PermissionModule::Acompanhamento, PermissionAction::Create),
            'can_manage' => $user->canAccess(PermissionModule::Acompanhamento, PermissionAction::Edit),
            'can_delete' => $user->canAccess(PermissionModule::Acompanhamento, PermissionAction::Delete),
            'filters' => [
                'stage' => $activeStage->value,
                'q' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $this->requireCompany($user);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'current_stage' => ['nullable', Rule::enum(HiringProcessStage::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'candidates_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $stage = $data['current_stage'] ?? HiringProcessStage::EngenhariaCargo;
        if (! $stage instanceof HiringProcessStage) {
            $stage = HiringProcessStage::from((string) $stage);
        }

        $nextOrder = (int) HiringProcess::query()
            ->where('company_id', $company->id)
            ->where('current_stage', $stage->value)
            ->max('sort_order');

        $notes = isset($data['notes']) && trim((string) $data['notes']) !== ''
            ? $data['notes']
            : null;

        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => $data['title'],
            'current_stage' => $stage,
            'notes' => $notes,
            'notes_at' => $notes !== null ? now() : null,
            'candidates_count' => array_key_exists('candidates_count', $data) && $data['candidates_count'] !== null
                ? (int) $data['candidates_count']
                : null,
            'sort_order' => $nextOrder + 1,
            'updated_by' => $user->id,
        ]);

        return redirect()
            ->route('client.acompanhamento.index', ['stage' => $stage->value])
            ->with('success', 'Processo de acompanhamento criado.');
    }

    public function update(Request $request, HiringProcess $hiringProcess): RedirectResponse
    {
        $this->authorizeCompanyProcess($request, $hiringProcess);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'candidates_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'current_stage' => ['sometimes', 'required', Rule::enum(HiringProcessStage::class)],
        ]);

        if (array_key_exists('title', $data)) {
            $hiringProcess->title = $data['title'];
        }
        if (array_key_exists('notes', $data)) {
            $notes = $data['notes'] !== null && trim((string) $data['notes']) !== ''
                ? $data['notes']
                : null;
            $hiringProcess->notes = $notes;
            $hiringProcess->notes_at = $notes !== null ? now() : null;
        }
        if (array_key_exists('candidates_count', $data)) {
            $hiringProcess->candidates_count = $data['candidates_count'] !== null
                ? (int) $data['candidates_count']
                : null;
        }
        if (array_key_exists('current_stage', $data)) {
            $hiringProcess->current_stage = $data['current_stage'] instanceof HiringProcessStage
                ? $data['current_stage']
                : HiringProcessStage::from((string) $data['current_stage']);
        }

        $hiringProcess->updated_by = $request->user()?->id;
        $hiringProcess->save();

        return redirect()
            ->route('client.acompanhamento.index', [
                'stage' => $hiringProcess->current_stage->value,
            ])
            ->with('success', 'Processo atualizado.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $company = $this->requireCompany($request->user());

        $data = $request->validate([
            'stage' => ['required', Rule::enum(HiringProcessStage::class)],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'distinct', 'exists:hiring_processes,id'],
        ]);

        $stage = $data['stage'] instanceof HiringProcessStage
            ? $data['stage']
            : HiringProcessStage::from((string) $data['stage']);

        $ids = array_map('intval', $data['ordered_ids']);

        $ownedIds = HiringProcess::query()
            ->where('company_id', $company->id)
            ->where('current_stage', $stage->value)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(count($ownedIds) === count($ids), 422);

        foreach ($ids as $index => $id) {
            HiringProcess::query()
                ->whereKey($id)
                ->where('company_id', $company->id)
                ->update([
                    'sort_order' => $index + 1,
                    'updated_by' => $request->user()?->id,
                ]);
        }

        return back()->with('success', 'Ordem da lista atualizada.');
    }

    public function advance(Request $request, HiringProcess $hiringProcess): RedirectResponse
    {
        $this->authorizeCompanyProcess($request, $hiringProcess);

        $next = $hiringProcess->current_stage->next();
        if ($next === null) {
            return back()->with('error', 'Este processo já está na última fase.');
        }

        $hiringProcess->current_stage = $next;
        $hiringProcess->updated_by = $request->user()?->id;
        $hiringProcess->save();

        return redirect()
            ->route('client.acompanhamento.index', ['stage' => $next->value])
            ->with('success', 'Processo avançado para '.$next->label().'.');
    }

    public function retreat(Request $request, HiringProcess $hiringProcess): RedirectResponse
    {
        $this->authorizeCompanyProcess($request, $hiringProcess);

        $previous = $hiringProcess->current_stage->previous();
        if ($previous === null) {
            return back()->with('error', 'Este processo já está na primeira fase.');
        }

        $hiringProcess->current_stage = $previous;
        $hiringProcess->updated_by = $request->user()?->id;
        $hiringProcess->save();

        return redirect()
            ->route('client.acompanhamento.index', ['stage' => $previous->value])
            ->with('success', 'Processo movido para '.$previous->label().'.');
    }

    public function destroy(Request $request, HiringProcess $hiringProcess): RedirectResponse
    {
        $this->authorizeCompanyProcess($request, $hiringProcess);

        $stage = $hiringProcess->current_stage->value;
        $hiringProcess->delete();

        return redirect()
            ->route('client.acompanhamento.index', ['stage' => $stage])
            ->with('success', 'Processo removido.');
    }

    public function storeComment(Request $request, HiringProcess $hiringProcess): RedirectResponse
    {
        $this->authorizeCompanyProcess($request, $hiringProcess);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $hiringProcess->comments()->create([
            'user_id' => (int) $request->user()->id,
            'body' => trim($data['body']),
        ]);

        return back()->with('success', 'Observação publicada.');
    }

    private function requireCompany(?User $user): Company
    {
        abort_unless($user !== null, 403);
        $company = $user->contextCompany();
        abort_unless($company !== null, 403);
        abort_unless($company->hasAcompanhamentoEnabled(), 403);

        return $company;
    }

    private function authorizeCompanyProcess(Request $request, HiringProcess $hiringProcess): Company
    {
        $company = $this->requireCompany($request->user());
        abort_unless((int) $hiringProcess->company_id === (int) $company->id, 403);

        return $company;
    }

    private function applySearchFilter($query, string $search): void
    {
        $like = '%'.$search.'%';
        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query->where('title', $operator, $like);
    }
}
