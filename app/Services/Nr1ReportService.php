<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyNr1Report;
use App\Support\Nr1RiskScenarioResolver;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Nr1ReportService
{
    public function __construct(
        private readonly ReportGenerator $generator,
    ) {}

    /**
     * @return array{scenario: string|null, scenario_label: string|null, config: array<string, mixed>}
     */
    public function scenarioMeta(Survey $survey): array
    {
        $resolved = Nr1RiskScenarioResolver::resolve($survey);
        $config = $resolved['config'];

        return [
            'scenario' => $resolved['scenario'],
            'scenario_label' => $config['short_label'] ?? null,
            'config' => $config,
        ];
    }

    public function streamExecutive(Survey $survey): Response
    {
        $upload = $this->publishedUpload($survey, SurveyNr1Report::TYPE_EXECUTIVE);
        if ($upload !== null) {
            return $this->downloadUpload($upload, 'relatorio-executivo-'.$survey->id);
        }

        return $this->generator->executivePdf($survey)->stream('relatorio-executivo-'.$survey->id.'.pdf');
    }

    public function streamTechnicalReferral(Survey $survey): Response
    {
        $upload = $this->publishedUpload($survey, SurveyNr1Report::TYPE_TECHNICAL_REFERRAL);
        if ($upload !== null) {
            return $this->downloadUpload($upload, 'encaminhamento-tecnico-'.$survey->id);
        }

        return $this->generator->technicalReferralPdf($survey)->stream('encaminhamento-tecnico-'.$survey->id.'.pdf');
    }

    public function streamActionPlan(Survey $survey): Response
    {
        return $this->generator->actionPlanPdf($survey)->stream('plano-de-acao-'.$survey->id.'.pdf');
    }

    private function publishedUpload(Survey $survey, string $type): ?SurveyNr1Report
    {
        $report = SurveyNr1Report::query()
            ->where('survey_id', $survey->id)
            ->where('type', $type)
            ->first();

        if ($report === null || ! $report->isPublished()) {
            return null;
        }

        if (! Storage::disk('local')->exists($report->file_path)) {
            return null;
        }

        return $report;
    }

    private function downloadUpload(SurveyNr1Report $report, string $fallbackName): StreamedResponse
    {
        return Storage::disk('local')->download(
            $report->file_path,
            $report->file_name ?? $fallbackName
        );
    }
}
