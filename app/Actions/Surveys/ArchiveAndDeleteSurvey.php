<?php

declare(strict_types=1);

namespace App\Actions\Surveys;

use App\Models\Survey;
use App\Models\User;
use App\Services\Nr1AiAnalyzer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class ArchiveAndDeleteSurvey
{
    public function __construct(
        private readonly Nr1AiAnalyzer $analyzer,
    ) {}

    public function handle(Survey $survey, User $actor): void
    {
        DB::transaction(function () use ($survey, $actor): void {
            $survey->load([
                'company',
                'template.sections.questions',
                'company.departments',
                'completedResponses.answers',
                'results',
                'insights',
                'actionPlans.items',
                'aiAnalyses',
                'nr1Reports',
            ]);

            $path = $this->storeArchive($survey, $actor);

            $survey->update([
                'deleted_by' => $actor->id,
                'archive_path' => $path,
            ]);

            $survey->delete();
        });
    }

    private function storeArchive(Survey $survey, User $actor): string
    {
        $payload = $this->buildPayload($survey, $actor);
        $filename = sprintf(
            '%d_%s.json',
            $survey->id,
            now()->format('Y-m-d_His'),
        );
        $path = "survey-archives/{$survey->company_id}/{$filename}";

        Storage::disk('local')->put(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Survey $survey, User $actor): array
    {
        $template = $survey->template;

        return [
            'exported_at' => now()->toIso8601String(),
            'deleted_by' => [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
            ],
            'company' => [
                'id' => $survey->company?->id,
                'name' => $survey->company?->name,
            ],
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'public_token' => $survey->public_token,
                'status' => $survey->status,
                'starts_at' => $survey->starts_at?->toIso8601String(),
                'ends_at' => $survey->ends_at?->toIso8601String(),
                'min_responses_for_breakdown' => $survey->min_responses_for_breakdown,
                'answers_reconstructed_at' => $survey->answers_reconstructed_at?->toIso8601String(),
                'survey_template_id' => $survey->survey_template_id,
                'created_at' => $survey->created_at?->toIso8601String(),
                'updated_at' => $survey->updated_at?->toIso8601String(),
            ],
            'template' => $template ? [
                'id' => $template->id,
                'title' => $template->title,
                'description' => $template->description,
                'sections' => $template->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'sort_order' => $section->sort_order,
                    'questions' => $section->questions->map(fn ($question) => [
                        'id' => $question->id,
                        'body' => $question->body,
                        'reverse_score' => $question->reverse_score,
                        'sort_order' => $question->sort_order,
                        'weight' => $question->weight ?? null,
                        'response_scale' => $question->response_scale ?? null,
                    ])->values()->all(),
                ])->values()->all(),
            ] : null,
            'aggregated' => $this->analyzer->buildAggregatedPayload($survey),
            'responses_anonymized' => $this->buildRawRows($survey),
            'results' => $survey->results->map(fn ($result) => [
                'id' => $result->id,
                'survey_template_section_id' => $result->survey_template_section_id,
                'department_id' => $result->department_id,
                'average_score' => $result->average_score,
                'risk_level' => $result->risk_level,
                'respondent_count' => $result->respondent_count,
                'meta' => $result->meta,
            ])->values()->all(),
            'insights' => $survey->insights->map(fn ($insight) => [
                'id' => $insight->id,
                'type' => $insight->type,
                'message' => $insight->message,
                'meta' => $insight->meta,
            ])->values()->all(),
            'action_plans' => $survey->actionPlans->map(fn ($plan) => [
                'id' => $plan->id,
                'status' => $plan->status,
                'admin_published_at' => $plan->admin_published_at?->toIso8601String(),
                'technical_opinion' => $plan->technical_opinion,
                'technical_opinion_file_path' => $plan->technical_opinion_file_path,
                'technical_opinion_file_name' => $plan->technical_opinion_file_name,
                'items' => $plan->items->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'sort_order' => $item->sort_order,
                    'status' => $item->status ?? null,
                ])->values()->all(),
            ])->values()->all(),
            'ai_analyses' => $survey->aiAnalyses->map(fn ($analysis) => [
                'id' => $analysis->id,
                'type' => $analysis->type,
                'content' => $analysis->content,
                'prompt_tokens' => $analysis->prompt_tokens,
                'completion_tokens' => $analysis->completion_tokens,
                'model_used' => $analysis->model_used,
                'generated_by' => $analysis->generated_by,
                'created_at' => $analysis->created_at?->toIso8601String(),
            ])->values()->all(),
            'nr1_reports' => $survey->nr1Reports->map(fn ($report) => [
                'id' => $report->id,
                'type' => $report->type,
                'file_path' => $report->file_path,
                'file_name' => $report->file_name,
                'published_at' => $report->published_at?->toIso8601String(),
                'uploaded_by' => $report->uploaded_by,
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRawRows(Survey $survey): array
    {
        $deptNames = $survey->company?->departments->keyBy('id') ?? collect();

        $questionMeta = [];
        foreach ($survey->template?->sections ?? [] as $section) {
            foreach ($section->questions as $question) {
                $questionMeta[$question->id] = [
                    'dimension' => $section->title,
                    'question' => $question->body,
                ];
            }
        }

        $out = [];
        $i = 0;
        foreach ($survey->completedResponses as $response) {
            $i++;
            $answers = [];
            foreach ($response->answers as $answer) {
                $meta = $questionMeta[$answer->survey_template_question_id] ?? null;
                $answers[] = [
                    'question_id' => $answer->survey_template_question_id,
                    'dimension' => $meta['dimension'] ?? null,
                    'question' => $meta['question'] ?? null,
                    'value' => $answer->value,
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
