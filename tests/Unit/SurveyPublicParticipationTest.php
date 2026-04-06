<?php

namespace Tests\Unit;

use App\Models\Survey;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SurveyPublicParticipationTest extends TestCase
{
    public function test_closure_reason_not_started_before_starts_at(): void
    {
        $survey = new Survey([
            'status' => 'active',
            'starts_at' => '2026-06-10 12:00:00',
            'ends_at' => '2026-06-20 12:00:00',
        ]);

        $this->assertSame(
            'not_started',
            $survey->publicParticipationClosureReason(Carbon::parse('2026-06-01 12:00:00'))
        );
    }

    public function test_closure_reason_ended_after_ends_at(): void
    {
        $survey = new Survey([
            'status' => 'active',
            'starts_at' => '2026-06-10 12:00:00',
            'ends_at' => '2026-06-20 12:00:00',
        ]);

        $this->assertSame(
            'ended',
            $survey->publicParticipationClosureReason(Carbon::parse('2026-06-25 12:00:00'))
        );
    }

    public function test_accepts_public_responses_inside_window(): void
    {
        $survey = new Survey([
            'status' => 'active',
            'starts_at' => '2026-06-10 12:00:00',
            'ends_at' => '2026-06-20 12:00:00',
        ]);

        $this->assertTrue(
            $survey->acceptsPublicResponses(Carbon::parse('2026-06-15 12:00:00'))
        );
    }

    public function test_inactive_status_blocks_participation(): void
    {
        $survey = new Survey([
            'status' => 'draft',
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->assertSame('inactive', $survey->publicParticipationClosureReason());
    }
}
