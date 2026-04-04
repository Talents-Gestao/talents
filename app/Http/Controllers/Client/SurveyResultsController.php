<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiAnalysisJob;
use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Services\Nr1AiAnalyzer;
use App\Services\SurveyResultCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class SurveyResultsController extends Controller
{
    private function companyId(Request $request): int
    {
        return (int) $request->user()->company_id;
    }

    private function findSurvey(Request $request, Survey $survey): Survey
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        return $survey;
    }

    public function show(Request $request, Survey $survey): Response
    {
        $survey = $this->findSurvey($request, $survey);
        $survey->load(['template.sections', 'company', 'insights']);

        $results = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->with(['section', 'department'])
            ->orderBy('survey_template_section_id')
            ->orderBy('department_id')
            ->get();

        $overall = $results->first(fn ($r) => $r->survey_template_section_id === null && $r->department_id === null);

        $bySection = $results->filter(fn ($r) => $r->survey_template_section_id !== null && $r->department_id === null)->values();

        $deptOveralls = $results
            ->filter(fn ($r) => $r->department_id !== null && $r->survey_template_section_id === null)
            ->values();

        $deptSectionResults = $results
            ->filter(fn ($r) => $r->department_id !== null && $r->survey_template_section_id !== null);

        $deptSectionsByDepartment = $deptSectionResults
            ->groupBy('department_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'department_id' => $first->department_id,
                    'department_name' => $first->department?->name ?? 'Setor #'.$first->department_id,
                    'sections' => $rows->values()->map(function ($r) {
                        return [
                            'id' => $r->id,
                            'survey_template_section_id' => $r->survey_template_section_id,
                            'average_score' => (float) $r->average_score,
                            'risk_level' => $r->risk_level,
                            'respondent_count' => $r->respondent_count,
                            'meta' => $r->meta,
                        ];
                    }),
                ];
            })
            ->values();

        $aiSetting = AiSetting::current();
        $aiEnabled = $aiSetting
            && $aiSetting->is_enabled
            && $aiSetting->safeApiKey() !== null;

        $latestAi = AiAnalysis::query()
            ->where('survey_id', $survey->id)
            ->where('type', Nr1AiAnalyzer::TYPE_NR1_GUIDANCE)
            ->latest()
            ->first();

        $aiAnalysisPending = Cache::has('ai_job_pending_'.$survey->id);

        return Inertia::render('Client/Surveys/Results', [
            'survey' => $survey,
            'overall' => $overall,
            'bySection' => $bySection,
            'deptOveralls' => $deptOveralls->map(function ($r) {
                return [
                    'id' => $r->id,
                    'average_score' => (float) $r->average_score,
                    'risk_level' => $r->risk_level,
                    'respondent_count' => $r->respondent_count,
                    'department_id' => $r->department_id,
                    'department_name' => $r->department?->name ?? 'Setor #'.$r->department_id,
                ];
            }),
            'deptSectionsByDepartment' => $deptSectionsByDepartment,
            'insights' => $survey->insights,
            'aiEnabled' => $aiEnabled,
            'aiAnalysis' => $latestAi ? [
                'content' => $latestAi->content,
            ] : null,
            'aiAnalysisPending' => $aiAnalysisPending,
        ]);
    }

    public function generateAiAnalysis(Request $request, Survey $survey): RedirectResponse
    {
        $survey = $this->findSurvey($request, $survey);

        $setting = AiSetting::current();
        if (! $setting || ! $setting->is_enabled || $setting->safeApiKey() === null) {
            return back()->with('error', 'A análise por IA não está disponível.');
        }

        if (! $survey->results()->exists()) {
            return back()->with('error', 'Não há resultados calculados para esta pesquisa. Use Recalcular primeiro.');
        }

        $rateKey = 'ai-analysis:'.$survey->id.':'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            return back()->with('error', 'Limite de gerações por hora atingido. Tente mais tarde.');
        }

        $pendingKey = 'ai_job_pending_'.$survey->id;
        if (Cache::has($pendingKey)) {
            return back()->with('info', 'Já existe uma análise em processamento. Atualize a página em instantes.');
        }

        Cache::put($pendingKey, true, now()->addMinutes(15));
        RateLimiter::hit($rateKey, 3600);

        GenerateAiAnalysisJob::dispatch($survey->id, (int) $request->user()->id);

        return back()->with('success', 'Análise solicitada. Em alguns segundos atualize a página para ver o texto.');
    }

    public function recalculate(Request $request, Survey $survey, SurveyResultCalculator $calculator): RedirectResponse
    {
        $survey = $this->findSurvey($request, $survey);

        $calculator->recalculate($survey);

        return back()->with('success', 'Resultados recalculados.');
    }
}
