<?php

declare(strict_types=1);

namespace Tests\Unit\Company;

use App\Actions\Company\ResolveOrCreateCompanyEmployee;
use App\Models\Company;
use App\Models\CompanyEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveOrCreateCompanyEmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_employee_by_name_without_email(): void
    {
        $company = Company::query()->create(['name' => 'Empresa A']);

        $employee = app(ResolveOrCreateCompanyEmployee::class)->execute($company, 'Maria Silva');

        $this->assertSame('Maria Silva', $employee->name);
        $this->assertNull($employee->email);
        $this->assertTrue($employee->is_active);
        $this->assertDatabaseCount('company_employees', 1);
    }

    public function test_reuses_same_name_without_email(): void
    {
        $company = Company::query()->create(['name' => 'Empresa A']);
        $action = app(ResolveOrCreateCompanyEmployee::class);

        $first = $action->execute($company, 'Maria Silva');
        $second = $action->execute($company, 'maria silva');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('company_employees', 1);
    }

    public function test_matches_and_updates_by_email(): void
    {
        $company = Company::query()->create(['name' => 'Empresa A']);
        $existing = CompanyEmployee::query()->create([
            'company_id' => $company->id,
            'name' => 'Nome Antigo',
            'email' => 'maria@empresa.local',
            'is_active' => true,
        ]);

        $resolved = app(ResolveOrCreateCompanyEmployee::class)->execute(
            $company,
            'Maria Silva',
            'MARIA@empresa.local',
        );

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame('Maria Silva', $resolved->fresh()->name);
        $this->assertSame('MARIA@empresa.local', $resolved->fresh()->email);
        $this->assertDatabaseCount('company_employees', 1);
    }

    public function test_does_not_reuse_across_companies(): void
    {
        $a = Company::query()->create(['name' => 'A']);
        $b = Company::query()->create(['name' => 'B']);
        $action = app(ResolveOrCreateCompanyEmployee::class);

        $empA = $action->execute($a, 'Maria Silva', 'maria@x.local');
        $empB = $action->execute($b, 'Maria Silva', 'maria@x.local');

        $this->assertNotSame($empA->id, $empB->id);
        $this->assertDatabaseCount('company_employees', 2);
    }
}
