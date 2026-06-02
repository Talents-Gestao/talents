<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyInsight;
use App\Models\SurveyResult;
use App\Support\Nr1Scoring;

class InsightGenerator
{
    public function generateForSurvey(Survey $survey): void
    {
        $survey->insights()->delete();

        $survey->load('company');

        $overall = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->first();

        if (! $overall) {
            return;
        }

        if ($overall->risk_level === 'red') {
            SurveyInsight::create([
                'survey_id' => $survey->id,
                'type' => 'alert',
                'message' => 'Risco psicossocial elevado no indicador geral. Priorize ações imediatas com SESMT/RH e liderança.',
                'meta' => ['average_score' => $overall->average_score],
            ]);
        }

        $benchmark = Nr1Scoring::segmentRiskBenchmark($survey->company->segment);
        $margin = (float) config('nr1.benchmark_alert_margin', 5);
        if ($overall->average_score > $benchmark + $margin) {
            SurveyInsight::create([
                'survey_id' => $survey->id,
                'type' => 'benchmark',
                'message' => 'Atenção: o índice de risco está acima do benchmark estimado do segmento ('.$benchmark.' pontos).',
                'meta' => ['benchmark' => $benchmark, 'company' => $overall->average_score],
            ]);
        }

        $sections = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->whereNotNull('survey_template_section_id')
            ->whereNull('department_id')
            ->get();

        foreach ($sections as $row) {
            if (in_array($row->risk_level, ['red', 'yellow'], true)) {
                $title = $row->meta['section_title'] ?? 'Dimensão';
                $levelLabel = Nr1Scoring::riskLabel($row->risk_level);
                SurveyInsight::create([
                    'survey_id' => $survey->id,
                    'type' => 'alert',
                    'message' => $levelLabel.': '.$title.'. Documente medidas no plano de ação do PGR.',
                    'meta' => ['section_id' => $row->survey_template_section_id, 'risk_level' => $row->risk_level],
                ]);
            }
        }

        $previous = Survey::query()
            ->where('company_id', $survey->company_id)
            ->where('id', '<', $survey->id)
            ->orderByDesc('id')
            ->first();

        if ($previous) {
            $prevOverall = SurveyResult::query()
                ->where('survey_id', $previous->id)
                ->whereNull('survey_template_section_id')
                ->whereNull('department_id')
                ->first();

            $trendThreshold = (float) config('nr1.trend_change_threshold', 3);

            if ($prevOverall && $overall->average_score < $prevOverall->average_score - $trendThreshold) {
                SurveyInsight::create([
                    'survey_id' => $survey->id,
                    'type' => 'trend',
                    'message' => 'Tendência positiva: o risco geral reduziu em relação à campanha anterior.',
                    'meta' => ['previous' => $prevOverall->average_score, 'current' => $overall->average_score],
                ]);
            }
            if ($prevOverall && $overall->average_score > $prevOverall->average_score + $trendThreshold) {
                SurveyInsight::create([
                    'survey_id' => $survey->id,
                    'type' => 'trend',
                    'message' => 'Atenção: o risco psicossocial aumentou em relação à campanha anterior.',
                    'meta' => ['previous' => $prevOverall->average_score, 'current' => $overall->average_score],
                ]);
            }
        }
    }
}
