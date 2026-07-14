<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Rhid;

use App\Models\Company;
use App\Support\Rhid\RhidPersonDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RhidPersonDirectoryDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_demo_persons_when_rhid_unconfigured_and_demo_enabled(): void
    {
        config(['rhid.demo_persons' => true]);

        $company = Company::query()->create([
            'name' => 'Sem RHID',
            'is_active' => true,
            'rhid_access' => true,
            'rhid_email' => null,
            'rhid_password' => null,
        ]);

        $persons = app(RhidPersonDirectory::class)->activePersons($company);

        $this->assertGreaterThanOrEqual(3, $persons->count());
        $this->assertSame(900001, $persons->first()['id']);
        $this->assertNotNull(
            app(RhidPersonDirectory::class)->findActive($company, 900002),
        );
    }

    public function test_returns_empty_when_demo_disabled_and_rhid_unconfigured(): void
    {
        config(['rhid.demo_persons' => false]);

        $company = Company::query()->create([
            'name' => 'Sem RHID',
            'is_active' => true,
            'rhid_access' => true,
        ]);

        $this->assertTrue(app(RhidPersonDirectory::class)->activePersons($company)->isEmpty());
    }
}
