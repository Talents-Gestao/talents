<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\InterviewStatus;
use App\Enums\InterviewTranscriptSource;
use App\Models\AiSetting;
use App\Models\HiringApplication;
use App\Models\HiringVacancy;
use App\Models\Interview;
use App\Models\InterviewQuestionnaire;
use App\Models\TalentCandidate;
use App\Services\Hiring\HiringVacancyService;
use Database\Seeders\InterviewQuestionnaireSeeder;

abstract class InterviewTestCase extends HiringVacancyTestCase
{
    protected function questionnaire(): InterviewQuestionnaire
    {
        $this->seed(InterviewQuestionnaireSeeder::class);

        return InterviewQuestionnaire::query()->where('is_default', true)->firstOrFail();
    }

    protected function enableAi(): AiSetting
    {
        return AiSetting::query()->create([
            'provider' => 'openai',
            'model' => 'gpt-test',
            'is_enabled' => true,
            'api_key' => 'chave-de-teste',
            'max_tokens' => 800,
            'temperature' => 0.2,
        ]);
    }

    /**
     * @return array{0: TalentCandidate, 1: HiringApplication, 2: HiringVacancy}
     */
    protected function candidateWithApplication(string $name = 'Ana Souza', ?HiringVacancy $vacancy = null): array
    {
        $vacancy ??= $this->vacancy();
        $candidate = TalentCandidate::query()->create(['name' => $name, 'email' => mb_strtolower(str_replace(' ', '.', $name)).'@example.com']);
        $application = app(HiringVacancyService::class)->addCandidate($vacancy, $candidate, null, null);

        return [$candidate, $application, $vacancy];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function interview(InterviewQuestionnaire $questionnaire, array $overrides = []): Interview
    {
        return Interview::query()->create([
            'questionnaire_id' => $questionnaire->id,
            'candidate_name' => 'Entrevista antiga',
            'status' => InterviewStatus::Completed,
            'transcript_source' => InterviewTranscriptSource::ManualText,
            'transcript_text' => 'Entrevistadora: conte sua experiência. Candidata: trabalhei cinco anos com recrutamento.',
            'finished_at' => now(),
            ...$overrides,
        ]);
    }
}
