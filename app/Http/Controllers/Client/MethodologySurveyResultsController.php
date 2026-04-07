<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\MethodologySurvey;
use App\Models\MethodologySurveyAnswer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MethodologySurveyResultsController extends Controller
{
    private function guardSurvey(Request $request, MethodologySurvey $survey): Company
    {
        $company = $request->user()->company;
        abort_unless($company && $company->hasMethodologyEnabled(), 403);
        abort_unless($survey->company_id === $company->id, 403);

        return $company;
    }

    public function show(Request $request, MethodologySurvey $survey): Response
    {
        $this->guardSurvey($request, $survey);

        $survey->load([
            'template.sections.questions',
        ]);

        $completedCount = $survey->completedResponses()->count();

        $bySection = [];
        $radarLabels = [];
        $radarValues = [];

        foreach ($survey->template->sections as $section) {
            $scaleQuestions = $section->questions->filter(fn ($q) => $q->type === 'scale');
            $textQuestions = $section->questions->filter(fn ($q) => $q->type === 'text');

            $sectionScales = [];
            foreach ($scaleQuestions as $q) {
                $stats = MethodologySurveyAnswer::query()
                    ->whereHas('response', function ($q2) use ($survey) {
                        $q2->where('methodology_survey_id', $survey->id)
                            ->whereNotNull('completed_at');
                    })
                    ->where('methodology_form_question_id', $q->id)
                    ->whereNotNull('value_numeric')
                    ->selectRaw('AVG(value_numeric) as avg_val, COUNT(*) as cnt')
                    ->first();

                $avg = $stats && $stats->cnt > 0 ? round((float) $stats->avg_val, 2) : null;
                $sectionScales[] = [
                    'question' => $q,
                    'average' => $avg,
                    'count' => (int) ($stats->cnt ?? 0),
                ];
            }

            $openAnswers = [];
            foreach ($textQuestions as $q) {
                $texts = MethodologySurveyAnswer::query()
                    ->whereHas('response', function ($q2) use ($survey) {
                        $q2->where('methodology_survey_id', $survey->id)
                            ->whereNotNull('completed_at');
                    })
                    ->where('methodology_form_question_id', $q->id)
                    ->whereNotNull('value_text')
                    ->where('value_text', '!=', '')
                    ->orderByDesc('id')
                    ->limit(200)
                    ->pluck('value_text')
                    ->all();

                $openAnswers[] = [
                    'question' => $q,
                    'answers' => $texts,
                ];
            }

            $sectionAvg = null;
            $nonNull = array_filter($sectionScales, fn ($s) => $s['average'] !== null);
            if (count($nonNull) > 0) {
                $sectionAvg = round(array_sum(array_column($nonNull, 'average')) / count($nonNull), 2);
            }

            $bySection[] = [
                'section' => $section,
                'scales' => $sectionScales,
                'open' => $openAnswers,
                'section_average' => $sectionAvg,
            ];

            if ($sectionAvg !== null) {
                $radarLabels[] = mb_substr($section->title, 0, 40);
                $radarValues[] = $sectionAvg;
            }
        }

        return Inertia::render('Client/Methodology/Surveys/Results', [
            'survey' => $survey,
            'completedCount' => $completedCount,
            'bySection' => $bySection,
            'radar' => [
                'labels' => $radarLabels,
                'series' => $radarValues,
            ],
        ]);
    }

    public function exportCsv(Request $request, MethodologySurvey $survey): StreamedResponse
    {
        $this->guardSurvey($request, $survey);

        $survey->load(['template.sections.questions']);

        $filename = 'pesquisa-satisfacao-'.$survey->id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($survey) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, ['Pergunta', 'Tipo', 'Resposta', 'E-mail (se coletado)', 'Data resposta'], ';');

            $responses = $survey->completedResponses()
                ->with('answers.question')
                ->orderBy('id')
                ->get();

            foreach ($responses as $response) {
                foreach ($response->answers as $answer) {
                    $q = $answer->question;
                    if (! $q) {
                        continue;
                    }
                    $val = $q->type === 'scale'
                        ? (string) $answer->value_numeric
                        : (string) $answer->value_text;
                    fputcsv($out, [
                        $q->body,
                        $q->type,
                        $val,
                        $response->email ?? '',
                        optional($response->completed_at)?->format('Y-m-d H:i:s') ?? '',
                    ], ';');
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
