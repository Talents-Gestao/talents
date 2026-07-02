<?php

namespace App\Services;

use App\Models\ActionPlan;
use App\Models\Survey;
use App\Support\Nr1RiskScenarioResolver;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportGenerator
{
    /**
     * @return array<string, mixed>
     */
    private function baseViewData(Survey $survey): array
    {
        $resolved = Nr1RiskScenarioResolver::resolve($survey);
        $scenario = $resolved['scenario'] ?? 'green';
        $scenarioConfig = Nr1RiskScenarioResolver::scenarioConfig($scenario);

        return [
            'survey' => $survey,
            'scenario' => $scenario,
            'scenarioConfig' => $scenarioConfig,
            'riskLevelLabel' => fn (?string $l) => match ($l) {
                'green' => config('nr1.risk_labels.green'),
                'yellow' => config('nr1.risk_labels.yellow'),
                'red' => config('nr1.risk_labels.red'),
                default => strtoupper((string) $l),
            },
        ];
    }

    public function executivePdf(Survey $survey): \Barryvdh\DomPDF\PDF
    {
        $survey->load([
            'company',
            'template.sections',
            'results' => fn ($q) => $q->orderBy('id'),
            'insights',
        ]);

        return Pdf::loadView('reports.executive', $this->baseViewData($survey))->setPaper('a4');
    }

    public function technicalPdf(Survey $survey): \Barryvdh\DomPDF\PDF
    {
        $survey->load([
            'company',
            'template.sections.questions',
            'results' => fn ($q) => $q->orderBy('id'),
            'insights',
            'responses',
        ]);

        return Pdf::loadView('reports.technical', $this->baseViewData($survey))->setPaper('a4');
    }

    public function technicalReferralPdf(Survey $survey): \Barryvdh\DomPDF\PDF
    {
        $survey->load([
            'company',
            'results' => fn ($q) => $q->orderBy('id'),
            'insights',
        ]);

        return Pdf::loadView('reports.referral', $this->baseViewData($survey))->setPaper('a4');
    }

    public function actionPlanPdf(Survey $survey): \Barryvdh\DomPDF\PDF
    {
        $survey->load([
            'company',
            'results' => fn ($q) => $q->orderBy('id'),
        ]);

        $plan = ActionPlan::query()
            ->where('survey_id', $survey->id)
            ->where('company_id', $survey->company_id)
            ->with('items')
            ->first();

        $data = $this->baseViewData($survey);
        $data['plan'] = $plan;
        $data['items'] = $plan?->items ?? collect();

        return Pdf::loadView('reports.action_plan', $data)->setPaper('a4');
    }
}
