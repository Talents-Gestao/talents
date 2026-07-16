<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\HiringProcessStage;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\HiringProcess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HiringProcessController extends Controller
{
    public function index(Request $request): Response
    {
        $stageFilter = $request->string('stage')->toString();
        $activeStage = HiringProcessStage::tryFrom($stageFilter) ?? HiringProcessStage::AnaliseCurriculo;

        $companyId = $request->integer('company_id') ?: null;
        if ($companyId !== null && $companyId <= 0) {
            $companyId = null;
        }

        $search = trim($request->string('q')->toString());

        $countsQuery = HiringProcess::query();
        if ($companyId !== null) {
            $countsQuery->where('company_id', $companyId);
        }
        if ($search !== '') {
            $this->applySearchFilter($countsQuery, $search);
        }

        $rawCounts = $countsQuery
            ->selectRaw('current_stage, count(*) as aggregate')
            ->groupBy('current_stage')
            ->pluck('aggregate', 'current_stage');

        $stageCounts = [];
        foreach (HiringProcessStage::ordered() as $stage) {
            $stageCounts[$stage->value] = (int) ($rawCounts[$stage->value] ?? 0);
        }

        $processesQuery = HiringProcess::query()
            ->with(['company:id,name', 'updatedByUser:id,name'])
            ->where('current_stage', $activeStage->value)
            ->orderByDesc('updated_at');

        if ($companyId !== null) {
            $processesQuery->where('company_id', $companyId);
        }
        if ($search !== '') {
            $this->applySearchFilter($processesQuery, $search);
        }

        $processes = $processesQuery->get()->map(fn (HiringProcess $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'notes' => $p->notes,
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
        ]);

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name]);

        return Inertia::render('Admin/Acompanhamento/Index', [
            'stages' => HiringProcessStage::options(),
            'active_stage' => $activeStage->value,
            'stage_counts' => $stageCounts,
            'processes' => $processes,
            'companies' => $companies,
            'filters' => [
                'stage' => $activeStage->value,
                'company_id' => $companyId,
                'q' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:255'],
            'current_stage' => ['nullable', Rule::enum(HiringProcessStage::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $stage = $data['current_stage'] ?? HiringProcessStage::AnaliseCurriculo;
        if (! $stage instanceof HiringProcessStage) {
            $stage = HiringProcessStage::from((string) $stage);
        }

        HiringProcess::query()->create([
            'company_id' => (int) $data['company_id'],
            'title' => $data['title'],
            'current_stage' => $stage,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.acompanhamento.index', ['stage' => $stage->value])
            ->with('success', 'Processo de acompanhamento criado.');
    }

    public function update(Request $request, HiringProcess $hiringProcess): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'current_stage' => ['sometimes', 'required', Rule::enum(HiringProcessStage::class)],
            'company_id' => ['sometimes', 'required', 'exists:companies,id'],
        ]);

        if (array_key_exists('title', $data)) {
            $hiringProcess->title = $data['title'];
        }
        if (array_key_exists('notes', $data)) {
            $hiringProcess->notes = $data['notes'];
        }
        if (array_key_exists('company_id', $data)) {
            $hiringProcess->company_id = (int) $data['company_id'];
        }
        if (array_key_exists('current_stage', $data)) {
            $hiringProcess->current_stage = $data['current_stage'] instanceof HiringProcessStage
                ? $data['current_stage']
                : HiringProcessStage::from((string) $data['current_stage']);
        }

        $hiringProcess->updated_by = $request->user()?->id;
        $hiringProcess->save();

        return redirect()
            ->route('admin.acompanhamento.index', [
                'stage' => $hiringProcess->current_stage->value,
            ])
            ->with('success', 'Processo atualizado.');
    }

    public function advance(Request $request, HiringProcess $hiringProcess): RedirectResponse
    {
        $next = $hiringProcess->current_stage->next();
        if ($next === null) {
            return back()->with('error', 'Este processo já está na última fase.');
        }

        $hiringProcess->current_stage = $next;
        $hiringProcess->updated_by = $request->user()?->id;
        $hiringProcess->save();

        return redirect()
            ->route('admin.acompanhamento.index', ['stage' => $next->value])
            ->with('success', 'Processo avançado para '.$next->label().'.');
    }

    public function retreat(Request $request, HiringProcess $hiringProcess): RedirectResponse
    {
        $previous = $hiringProcess->current_stage->previous();
        if ($previous === null) {
            return back()->with('error', 'Este processo já está na primeira fase.');
        }

        $hiringProcess->current_stage = $previous;
        $hiringProcess->updated_by = $request->user()?->id;
        $hiringProcess->save();

        return redirect()
            ->route('admin.acompanhamento.index', ['stage' => $previous->value])
            ->with('success', 'Processo movido para '.$previous->label().'.');
    }

    public function destroy(HiringProcess $hiringProcess): RedirectResponse
    {
        $stage = $hiringProcess->current_stage->value;
        $hiringProcess->delete();

        return redirect()
            ->route('admin.acompanhamento.index', ['stage' => $stage])
            ->with('success', 'Processo removido.');
    }

    private function applySearchFilter($query, string $search): void
    {
        $like = '%'.$search.'%';
        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query->where(function ($q) use ($like, $operator) {
            $q->where('title', $operator, $like)
                ->orWhereHas('company', fn ($c) => $c->where('name', $operator, $like));
        });
    }
}
