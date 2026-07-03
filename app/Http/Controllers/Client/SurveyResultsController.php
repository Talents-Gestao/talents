<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiAnalysisJob;
use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\Survey;
use App\Services\Nr1AiAnalyzer;
use App\Services\SurveyResultCalculator;
use App\Services\SurveyResultsPresenter;
use App\Support\Nr1RiskScenarioResolver;
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

        $presented = SurveyResultsPresenter::forSurvey($survey);

        $aiSetting = AiSetting::current();
        $aiEnabled = $aiSetting
            && $aiSetting->is_enabled
            && $aiSetting->safeApiKey() !== null;

        $latestAi = AiAnalysis::query()
            ->where('survey_id', $survey->id)
            ->where('type', Nr1AiAnalyzer::TYPE_NR1_GUIDANCE)
            ->latest()
            ->first();

        $aiAnalysisPending = Cache::has(Nr1AiAnalyzer::pendingCacheKey($survey->id, Nr1AiAnalyzer::TYPE_NR1_GUIDANCE));

        return Inertia::render('Client/Surveys/Results', array_merge($presented, [
            'aiEnabled' => $aiEnabled,
            'aiAnalysis' => $latestAi ? [
                'content' => $latestAi->content,
            ] : null,
            'aiAnalysisPending' => $aiAnalysisPending,
            'riskScenarioLabel' => Nr1RiskScenarioResolver::scenarioConfig(
                Nr1RiskScenarioResolver::forSurvey($survey) ?? 'green'
            )['short_label'] ?? null,
        ]));
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

        $pendingKey = Nr1AiAnalyzer::pendingCacheKey($survey->id, Nr1AiAnalyzer::TYPE_NR1_GUIDANCE);
        if (Cache::has($pendingKey)) {
            return back()->with('info', 'Já existe uma análise em processamento. Atualize a página em instantes.');
        }

        Cache::put($pendingKey, true, now()->addMinutes(15));
        RateLimiter::hit($rateKey, 3600);

        GenerateAiAnalysisJob::dispatch($survey->id, (int) $request->user()->id, Nr1AiAnalyzer::TYPE_NR1_GUIDANCE);

        return back()->with('success', 'Análise solicitada. Em alguns segundos atualize a página para ver o texto.');
    }

    public function recalculate(Request $request, Survey $survey, SurveyResultCalculator $calculator): RedirectResponse
    {
        $survey = $this->findSurvey($request, $survey);

        $calculator->recalculate($survey);

        return back()->with('success', 'Resultados recalculados.');
    }
}
