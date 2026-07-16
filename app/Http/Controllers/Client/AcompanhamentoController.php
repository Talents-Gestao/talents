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

        $allProcesses = HiringProcess::query()
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at')
            ->get();

        $stageCounts = [];
        foreach (HiringProcessStage::ordered() as $stage) {
            $stageCounts[$stage->value] = 0;
        }
        foreach ($allProcesses as $process) {
            $key = $process->current_stage->value;
            $stageCounts[$key] = ($stageCounts[$key] ?? 0) + 1;
        }

        $mapProcess = fn (HiringProcess $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'current_stage' => $p->current_stage->value,
            'current_stage_label' => $p->current_stage->label(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];

        $columns = [];
        foreach (HiringProcessStage::ordered() as $stage) {
            $columns[] = [
                'value' => $stage->value,
                'label' => $stage->label(),
                'order' => $stage->order(),
                'count' => $stageCounts[$stage->value] ?? 0,
                'processes' => $allProcesses
                    ->filter(fn (HiringProcess $p) => $p->current_stage === $stage)
                    ->values()
                    ->map($mapProcess)
                    ->all(),
            ];
        }

        return Inertia::render('Client/Acompanhamento/Index', [
            'stages' => HiringProcessStage::options(),
            'active_stage' => $activeStage->value,
            'stage_counts' => $stageCounts,
            'columns' => $columns,
            'company_name' => $company->name,
        ]);
    }
}
