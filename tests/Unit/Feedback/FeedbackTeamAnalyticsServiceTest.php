<?php

declare(strict_types=1);

namespace Tests\Unit\Feedback;

use App\Enums\FeedbackSessionStatus;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackTemplateQuestion;
use App\Models\User;
use App\Services\Feedback\FeedbackTeamAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

class FeedbackTeamAnalyticsServiceTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    public function test_analytics_aggregate_completed_sessions_for_company_admin(): void
    {
        $company = $this->createFeedbackCompany();
        $leader = User::factory()->companyAdmin($company->id)->create();
        $employee = $this->createFeedbackEmployee($company, $leader);
        $template = $this->seedFeedbackTemplate();

        $termoQuestion = FeedbackTemplateQuestion::query()
            ->where('key', 'termometro_nivel')
            ->whereHas('section', fn ($q) => $q->where('feedback_template_id', $template->id))
            ->firstOrFail();

        $conquistasQuestion = FeedbackTemplateQuestion::query()
            ->where('key', 'conquistas_lista')
            ->whereHas('section', fn ($q) => $q->where('feedback_template_id', $template->id))
            ->firstOrFail();

        $session = $this->createFeedbackSession($company, $leader, $employee, [
            'status' => FeedbackSessionStatus::Completed,
            'completed_at' => now()->subDays(2),
        ]);

        FeedbackSessionAnswer::create([
            'feedback_session_id' => $session->id,
            'feedback_template_question_id' => $termoQuestion->id,
            'value_text' => 'muito_bom',
        ]);

        FeedbackSessionAnswer::create([
            'feedback_session_id' => $session->id,
            'feedback_template_question_id' => $conquistasQuestion->id,
            'value_json' => ['Comunicação clara', 'Proatividade'],
        ]);

        $analytics = app(FeedbackTeamAnalyticsService::class)->forCompany($company, $leader);

        $this->assertSame(1, $analytics['completed_count']);
        $this->assertContains('comunicação clara', $analytics['strengths']);
        $this->assertSame(1, $analytics['thermometer']['series'][1]); // muito_bom index
    }

    public function test_analytics_scoped_to_leader_for_company_user(): void
    {
        $company = $this->createFeedbackCompany();
        $leaderA = User::factory()->companyUser($company->id)->create();
        $leaderB = User::factory()->companyUser($company->id)->create();
        $employeeA = $this->createFeedbackEmployee($company, $leaderA, ['email' => 'a@test.local']);
        $employeeB = $this->createFeedbackEmployee($company, $leaderB, ['email' => 'b@test.local']);

        $this->createFeedbackSession($company, $leaderA, $employeeA, [
            'status' => FeedbackSessionStatus::Completed,
            'completed_at' => now(),
        ]);
        $this->createFeedbackSession($company, $leaderB, $employeeB, [
            'status' => FeedbackSessionStatus::Completed,
            'completed_at' => now(),
        ]);

        $analyticsA = app(FeedbackTeamAnalyticsService::class)->forCompany($company, $leaderA);
        $analyticsB = app(FeedbackTeamAnalyticsService::class)->forCompany($company, $leaderB);

        $this->assertSame(1, $analyticsA['completed_count']);
        $this->assertSame(1, $analyticsB['completed_count']);
    }
}
