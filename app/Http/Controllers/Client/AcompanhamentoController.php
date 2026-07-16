<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\HiringProcessStage;
use App\Http\Controllers\Controller;
use App\Models\HiringProcess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcompanhamentoController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->contextCompany();
        abort_unless($company !== null, 403);
        abort_unless($company->hasAcompanhamentoEnabled(), 403);

        $stageFilter = $request->string('stage')->toString();
        $activeStage = HiringProcessStage::tryFrom($stageFilter) ?? HiringProcessStage::AnaliseCurriculo;

        $baseQuery = HiringProcess::query()->where('company_id', $company->id);

        $rawCounts = (clone $baseQuery)
            ->selectRaw('current_stage, count(*) as aggregate')
            ->groupBy('current_stage')
            ->pluck('aggregate', 'current_stage');

        $stageCounts = [];
        foreach (HiringProcessStage::ordered() as $stage) {
            $stageCounts[$stage->value] = (int) ($rawCounts[$stage->value] ?? 0);
        }

        $processes = (clone $baseQuery)
            ->where('current_stage', $activeStage->value)
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (HiringProcess $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'current_stage' => $p->current_stage->value,
                'current_stage_label' => $p->current_stage->label(),
                'updated_at' => $p->updated_at?->toIso8601String(),
            ]);

        $allProcesses = HiringProcess::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (HiringProcess $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'current_stage' => $p->current_stage->value,
                'current_stage_label' => $p->current_stage->label(),
                'updated_at' => $p->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Client/Acompanhamento/Index', [
            'stages' => HiringProcessStage::options(),
            'active_stage' => $activeStage->value,
            'stage_counts' => $stageCounts,
            'processes' => $processes,
            'all_processes' => $allProcesses,
            'company_name' => $company->name,
        ]);
    }
}
