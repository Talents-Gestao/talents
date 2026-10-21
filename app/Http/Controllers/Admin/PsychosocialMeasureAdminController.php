<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\PsychosocialMeasureException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Services\PsychosocialMeasureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela do Admin Talents para registrar medidas da campanha.
 * A criação nasce «A aprovar». «Implantada» só entra pelo fluxo de status, com o usuário logado.
 */
class PsychosocialMeasureAdminController extends Controller
{
    public function __construct(private readonly PsychosocialMeasureService $measures)
    {
    }

    public function edit(Company $company, Survey $survey): Response
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $rows = PsychosocialMeasure::query()
            ->where('survey_id', $survey->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/Measures/Edit', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'survey' => ['id' => $survey->id, 'title' => $survey->title],
            'measures' => $rows->map(fn (PsychosocialMeasure $measure): array => [
                'id' => $measure->id,
                'factor_label' => $measure->factor_label,
                'measure_description' => $measure->measure_description,
                'status' => $measure->status,
                'status_label' => $measure->statusLabel(),
                'responsible_text' => $measure->responsible_text,
                'suggested_due_date' => $measure->suggested_due_date?->toDateString(),
                'origin' => $measure->origin,
            ])->all(),
            'statuses' => PsychosocialMeasure::STATUS_LABELS,
        ]);
    }

    public function store(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $data = $request->validate([
            'factor_label' => ['required', 'string', 'max:255'],
            'measure_description' => ['required', 'string', 'max:5000'],
            'evidence_basis' => ['required', 'string', 'in:'.implode(',', PsychosocialMeasure::EVIDENCE_BASES)],
            'responsible_text' => ['nullable', 'string', 'max:255'],
            'suggested_due_date' => ['nullable', 'date'],
        ]);

        $data['responsible_text'] = filled($data['responsible_text'] ?? null) ? $data['responsible_text'] : null;
        $data['suggested_due_date'] = filled($data['suggested_due_date'] ?? null) ? $data['suggested_due_date'] : null;

        try {
            $this->measures->createManual($survey, $data, $request->user());
        } catch (PsychosocialMeasureException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Medida registrada como «A aprovar».');
    }

    public function updateStatus(Request $request, Company $company, Survey $survey, PsychosocialMeasure $measure): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);
        abort_unless((int) $measure->survey_id === (int) $survey->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', PsychosocialMeasure::STATUSES)],
        ]);

        try {
            $this->measures->changeStatus($measure, $data['status'], $request->user());
        } catch (PsychosocialMeasureException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Status da medida atualizado.');
    }

    private function assertSurveyBelongsToCompany(Company $company, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
    }
}
