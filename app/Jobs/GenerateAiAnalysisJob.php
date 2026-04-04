<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\Survey;
use App\Services\Nr1AiAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateAiAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public int $surveyId,
        public int $userId
    ) {}

    public function handle(Nr1AiAnalyzer $analyzer): void
    {
        $cacheKey = 'ai_job_pending_'.$this->surveyId;

        try {
            $survey = Survey::query()->find($this->surveyId);
            if (! $survey) {
                return;
            }

            $setting = AiSetting::current();
            if (! $setting || ! $setting->is_enabled || $setting->safeApiKey() === null) {
                Log::warning('GenerateAiAnalysisJob skipped: IA desabilitada ou sem chave.', ['survey_id' => $this->surveyId]);

                return;
            }

            $result = $analyzer->generateNarrative($survey, $setting);

            AiAnalysis::query()->create([
                'survey_id' => $survey->id,
                'type' => Nr1AiAnalyzer::TYPE_NR1_GUIDANCE,
                'content' => $result['content'],
                'prompt_tokens' => $result['prompt_tokens'],
                'completion_tokens' => $result['completion_tokens'],
                'model_used' => $result['model_used'],
                'generated_by' => $this->userId,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateAiAnalysisJob failed', [
                'survey_id' => $this->surveyId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            Cache::forget($cacheKey);
        }
    }
}
