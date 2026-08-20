<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Commercial;

use App\Models\User;
use App\Support\Commercial\OptionalCommission;
use PHPUnit\Framework\TestCase;

class OptionalCommissionTest extends TestCase
{
    public function test_owner_without_seller_defaults_commission_off(): void
    {
        $owner = new User(['is_owner' => true]);

        $this->assertTrue(OptionalCommission::defaultsOff(null, $owner));
        $this->assertSame(0.0, OptionalCommission::resolveFromRequest(
            [],
            10.0,
            null,
            $owner,
        ));
    }

    public function test_eligible_seller_uses_settings_default_when_unspecified(): void
    {
        $owner = new User(['is_owner' => true]);
        $seller = new User(['is_owner' => false, 'is_commercial' => true]);

        $this->assertFalse(OptionalCommission::defaultsOff($seller, $owner));
        $this->assertSame(10.0, OptionalCommission::resolveFromRequest(
            [],
            10.0,
            $seller,
            $owner,
        ));
    }

    public function test_pay_commission_false_zeros_even_with_percent(): void
    {
        $this->assertSame(0.0, OptionalCommission::resolveFromRequest(
            ['pay_commission' => false, 'commission_percent' => 12],
            10.0,
            null,
            null,
        ));
    }

    public function test_pay_commission_true_uses_requested_percent(): void
    {
        $this->assertSame(8.0, OptionalCommission::resolveFromRequest(
            ['pay_commission' => true, 'commission_percent' => 8],
            10.0,
            null,
            null,
        ));
    }

    public function test_owner_seller_defaults_off_but_can_be_overridden(): void
    {
        $owner = new User(['is_owner' => true]);

        $this->assertTrue(OptionalCommission::defaultsOff($owner, $owner));
        $this->assertSame(5.0, OptionalCommission::resolveFromRequest(
            ['pay_commission' => true, 'commission_percent' => 5],
            10.0,
            $owner,
            $owner,
        ));
    }
}
