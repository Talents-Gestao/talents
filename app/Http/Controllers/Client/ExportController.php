<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Services\Nr1AiAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    private function companyId(Request $request): int
    {
        return (int) $request->user()->company_id;
    }

    public function json(Request $request, Survey $survey, Nr1AiAnalyzer $analyzer): Response
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        $survey->load(['template.sections.questions', 'company']);

        $aggregated = $analyzer->buildAggregatedPayload($survey);

        $raw = $this->buildRawRows($survey);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'company' => $survey->company?->name,
                'template_title' => $survey->template?->title,
            ],
            'aggregated' => $aggregated,
            'responses_anonymized' => $raw,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function csv(Request $request, Survey $survey): StreamedResponse
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        $survey->load([
            'template.sections.questions',
            'company.departments',
            'completedResponses.answers',
        ]);

        $deptNames = $survey->company?->departments->keyBy('id') ?? collect();

        $questionMeta = [];
        foreach ($survey->template?->sections ?? [] as $section) {
            foreach ($section->questions as $q) {
                $questionMeta[$q->id] = [
                    'dimension' => $section->title,
                    'body' => $q->body,
                ];
            }
        }

        $filename = 'talents-export-'.$survey->id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($survey, $deptNames, $questionMeta) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                'linha_resposta',
                'setor',
                'faixa_etaria',
                'tempo_empresa',
                'dimensao',
                'pergunta',
                'valor_likert_1_a_5',
            ], ';');

            $rowNum = 0;
            foreach ($survey->completedResponses as $response) {
                $rowNum++;
                $dept = $response->department_id
                    ? ($deptNames->get((int) $response->department_id)?->name ?? '')
                    : '';
                foreach ($response->answers as $answer) {
                    $meta = $questionMeta[$answer->survey_template_question_id] ?? ['dimension' => '', 'body' => ''];
                    fputcsv($out, [
                        $rowNum,
                        $dept,
                        $response->age_range ?? '',
                        $response->tenure_range ?? '',
                        $meta['dimension'],
                        $meta['body'],
                        $answer->value,
                    ], ';');
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRawRows(Survey $survey): array
    {
        $survey->load([
            'template.sections.questions',
            'completedResponses.answers',
            'company.departments',
        ]);

        $deptNames = $survey->company?->departments->keyBy('id') ?? collect();

        $questionMeta = [];
        foreach ($survey->template?->sections ?? [] as $section) {
            foreach ($section->questions as $q) {
                $questionMeta[$q->id] = [
                    'dimension' => $section->title,
                    'question' => $q->body,
                ];
            }
        }

        $out = [];
        $i = 0;
        foreach ($survey->completedResponses as $response) {
            $i++;
            $answers = [];
            foreach ($response->answers as $a) {
                $meta = $questionMeta[$a->survey_template_question_id] ?? null;
                $answers[] = [
                    'question_id' => $a->survey_template_question_id,
                    'dimension' => $meta['dimension'] ?? null,
                    'question' => $meta['question'] ?? null,
                    'value' => $a->value,
                ];
            }
            $out[] = [
                'anonymous_row' => $i,
                'department' => $response->department_id
                    ? ($deptNames->get($response->department_id)?->name ?? null)
                    : null,
                'age_range' => $response->age_range,
                'tenure_range' => $response->tenure_range,
                'answers' => $answers,
            ];
        }

        return $out;
    }
}
