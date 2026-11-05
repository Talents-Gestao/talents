<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateActionMeasuresJob;
use App\Models\AiSetting;
use App\Models\PsychosocialMeasure;
use App\Models\SurveyResult;
use App\Services\ActionMeasuresGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class ActionMeasuresGenerationTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_parser_reads_measures_from_json(): void
    {
        $rows = app(ActionMeasuresGenerator::class)->parseMeasures(<<<'JSON'
{"measures":[
  {"factor_label":"Demandas","measure_description":"Rever prazos da equipe.","suggested_due_days":45},
  {"factor_label":"","measure_description":"Ignorar"}
]}
JSON);

        $this->assertCount(1, $rows);
        $this->assertSame('Demandas', $rows[0]['factor_label']);
        $this->assertSame(45, $rows[0]['suggested_due_days']);
    }

    public function test_command_queues_each_ended_survey_only_until_generated(): void
    {
        Queue::fake();
        $fx = $this->createSurveyFixture();
        $fx->survey->forceFill([
            'status' => 'closed',
            'ends_at' => now()->subDay(),
        ])->save();
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => null,
            'department_id' => null,
            'average_score' => '2.800',
            'risk_level' => 'yellow',
            'respondent_count' => 12,
        ]);

        $this->artisan('nr1:generate-action-measures')->assertSuccessful();
        $this->artisan('nr1:generate-action-measures')->assertSuccessful();

        Queue::assertPushed(GenerateActionMeasuresJob::class, 1);

        $fx->survey->forceFill(['action_measures_generated_at' => now()])->save();
        $this->artisan('nr1:generate-action-measures')->assertSuccessful();
        Queue::assertPushed(GenerateActionMeasuresJob::class, 1);
    }

    public function test_generator_creates_suggestions_and_stamps_the_survey_once(): void
    {
        $fx = $this->createSurveyFixture();
        $fx->survey->forceFill(['ends_at' => now()->subDay(), 'status' => 'closed'])->save();
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => null,
            'department_id' => null,
            'average_score' => '2.800',
            'risk_level' => 'yellow',
            'respondent_count' => 12,
        ]);
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => $fx->section->id,
            'department_id' => null,
            'average_score' => '3.100',
            'risk_level' => 'yellow',
            'respondent_count' => 12,
            'meta' => ['section_title' => $fx->section->title],
        ]);

        AiSetting::query()->create([
            'provider' => 'openai',
            'model' => 'gpt-test',
            'is_enabled' => true,
            'api_key' => 'chave-de-teste',
            'max_tokens' => 800,
            'temperature' => 0.2,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"measures":[{"factor_label":"Demandas","measure_description":"Redistribuir prazos com a liderança.","suggested_due_days":30}]}',
                    ],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
                'model' => 'gpt-test',
            ]),
        ]);

        $count = app(ActionMeasuresGenerator::class)->generate($fx->survey->fresh(), AiSetting::current());

        $this->assertSame(1, $count);
        $this->assertNotNull($fx->survey->fresh()->action_measures_generated_at);
        $measure = PsychosocialMeasure::query()->where('survey_id', $fx->survey->id)->sole();
        $this->assertSame(PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION, $measure->origin);
        $this->assertSame(PsychosocialMeasure::STATUS_TO_APPROVE, $measure->status);
        $this->assertSame('Demandas', $measure->factor_label);
        Http::assertSentCount(1);
    }
}
