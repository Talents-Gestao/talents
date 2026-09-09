<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Commercial;

use App\Support\Commercial\ProposalValidity;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ProposalValidityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_days_from_settings_uses_minimum_of_one(): void
    {
        $this->assertSame(7, ProposalValidity::daysFromSettings(null));
        $this->assertSame(1, ProposalValidity::daysFromSettings(0));
        $this->assertSame(10, ProposalValidity::daysFromSettings(10));
    }

    public function test_valid_until_is_created_date_plus_validity_days(): void
    {
        $created = Carbon::parse('2026-09-01 18:40:00');

        $until = ProposalValidity::validUntil($created, 7);

        $this->assertSame('2026-09-08', $until->toDateString());
    }

    public function test_created_at_cutoff_includes_expiring_and_expired(): void
    {
        $today = Carbon::parse('2026-09-09 09:00:00');

        $cutoff = ProposalValidity::createdAtCutoff($today, 7, 3);

        $this->assertSame('2026-09-05', $cutoff->toDateString());
    }

    public function test_is_expired_when_valid_until_is_before_today(): void
    {
        $today = Carbon::parse('2026-09-09 09:00:00');
        $until = Carbon::parse('2026-09-08');

        $this->assertTrue(ProposalValidity::isExpired($until, $today));
        $this->assertFalse(ProposalValidity::isExpired(Carbon::parse('2026-09-09'), $today));
        $this->assertFalse(ProposalValidity::isExpired(Carbon::parse('2026-09-10'), $today));
    }
}
