<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiSetting;
use App\Models\Survey;
use App\Models\User;
use App\Services\ServiceSuggestionsGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateServiceSuggestionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $surveyId, public ?int $userId = null) {}

    public function handle(ServiceSuggestionsGenerator $generator): void
    {
        $cacheKey = ServiceSuggestionsGenerator::pendingCacheKey($this->surveyId);

        try {
            $survey = Survey::query()->find($this->surveyId);
            if ($survey === null || $survey->service_suggestions_generated_at !== null) {
                return;
            }

            $setting = AiSetting::current();
            if (! $setting || ! $setting->is_enabled || $setting->safeApiKey() === null) {
                Log::warning('GenerateServiceSuggestionsJob skipped: IA desabilitada ou sem chave.', [
                    'survey_id' => $this->surveyId,
                ]);

                return;
            }

            $actor = $this->userId !== null ? User::query()->find($this->userId) : null;
            $generator->generate($survey, $setting, $actor);
        } catch (\Throwable $exception) {
            Log::error('GenerateServiceSuggestionsJob failed', [
                'survey_id' => $this->surveyId,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        } finally {
            Cache::forget($cacheKey);
        }
    }
}
