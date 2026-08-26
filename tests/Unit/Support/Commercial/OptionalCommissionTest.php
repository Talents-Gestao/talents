<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Commercial;

use App\Models\User;
use App\Support\Commercial\OptionalCommission;
use PHPUnit\Framework\TestCase;

class OptionalCommissionTest extends TestCase
{
    public function test_null_seller_resolves_to_zero(): void
    {
        $this->assertSame(0.0, OptionalCommission::percentForSeller(null));
        $this->assertSame(0.0, OptionalCommission::resolveForProposal(null));
    }

    public function test_seller_percent_comes_from_user_profile(): void
    {
        $seller = new User(['commission_percent' => 12.5]);

        $this->assertSame(12.5, OptionalCommission::percentForSeller($seller));
        $this->assertSame(12.5, OptionalCommission::resolveForProposal($seller));
    }

    public function test_seller_with_zero_percent_has_no_commission(): void
    {
        $seller = new User(['commission_percent' => 0]);

        $this->assertSame(0.0, OptionalCommission::resolveForProposal($seller));
    }

    public function test_percent_is_clamped_between_zero_and_one_hundred(): void
    {
        $this->assertSame(0.0, OptionalCommission::percentForSeller(new User(['commission_percent' => -5])));
        $this->assertSame(100.0, OptionalCommission::percentForSeller(new User(['commission_percent' => 150])));
    }

    public function test_for_conversion_uses_proposal_snapshot(): void
    {
        $result = OptionalCommission::forConversion(10.0, 1_000, 10_000, false);

        $this->assertSame(10.0, $result['percent']);
        $this->assertSame(1_000, $result['cents']);
    }

    public function test_for_conversion_recomputes_cents_when_requested(): void
    {
        $result = OptionalCommission::forConversion(10.0, 500, 20_000, true);

        $this->assertSame(10.0, $result['percent']);
        $this->assertSame(2_000, $result['cents']);
    }

    public function test_cents_from_percent(): void
    {
        $this->assertSame(1_000, OptionalCommission::centsFromPercent(10_000, 10));
        $this->assertSame(0, OptionalCommission::centsFromPercent(10_000, 0));
    }
}
