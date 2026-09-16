<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\Survey;
use App\Services\Nr1AiAnalyzer;
use App\Support\TechnicalOpinionDisclaimerStripper;
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
        public int $userId,
        public string $type = Nr1AiAnalyzer::TYPE_NR1_GUIDANCE
    ) {}

    public function handle(Nr1AiAnalyzer $analyzer): void
    {
        $cacheKey = Nr1AiAnalyzer::pendingCacheKey($this->surveyId, $this->type);

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

            $result = $analyzer->generateNarrative($survey, $setting, $this->type);
            $content = $result['content'];
            if ($this->type === Nr1AiAnalyzer::TYPE_NR1_TECHNICAL_OPINION) {
                $content = TechnicalOpinionDisclaimerStripper::strip($content) ?? '';
            }

            AiAnalysis::query()->create([
                'survey_id' => $survey->id,
                'type' => $this->type,
                'content' => $content,
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
