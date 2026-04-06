<?php

namespace Tests\Feature\Public;

use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class PublicSurveyTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_show_renders_closed_for_draft_survey(): void
    {
        $fx = $this->createSurveyFixture(['status' => 'draft']);

        $this->get(route('survey.public', $fx->survey->public_token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Survey/Closed'));
    }

    public function test_submit_forbidden_when_active_but_past_ends_at(): void
    {
        $fx = $this->createSurveyFixture([
            'status' => 'active',
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subHour(),
        ]);

        $this->post(route('survey.public.submit', $fx->survey->public_token), [
            'answers' => [$fx->question->id => 3],
        ])->assertForbidden();
    }

    public function test_submit_redirects_when_survey_is_open(): void
    {
        $fx = $this->createSurveyFixture([
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->post(route('survey.public.submit', $fx->survey->public_token), [
            'answers' => [$fx->question->id => 3],
        ])->assertRedirect(route('survey.public.thanks', ['token' => $fx->survey->public_token]));
    }

    public function test_submit_rejects_department_from_another_company(): void
    {
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $deptB = Department::query()->create([
            'company_id' => $fxB->company->id,
            'name' => 'Setor B',
        ]);

        $this->post(route('survey.public.submit', $fxA->survey->public_token), [
            'answers' => [$fxA->question->id => 4],
            'department_id' => $deptB->id,
        ])->assertStatus(422);
    }

    public function test_survey_submit_throttle_returns_429_after_limit(): void
    {
        Config::set('public_rate_limits.survey_submit_per_minute', 5);

        $fx = $this->createSurveyFixture();

        $url = route('survey.public.submit', $fx->survey->public_token);
        $payload = ['answers' => [$fx->question->id => 3]];

        for ($i = 0; $i < 5; $i++) {
            $this->post($url, $payload)->assertRedirect();
        }

        $this->post($url, $payload)->assertStatus(429);
    }
}
