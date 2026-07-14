<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\EmployeeLeaveStatus;
use App\Models\Company;
use App\Models\EmployeeLeave;
use App\Support\Rhid\RhidPersonDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class FeriasModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_crud_leave(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Férias',
            'ferias_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = \App\Models\User::factory()->companyAdmin($company->id)->create();

        $this->bindRhidPerson(42, 'Colaborador Férias', 'colab-ferias@teste.local');

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.ferias.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('client.ferias.store'), [
                'rhid_person_id' => 42,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-15',
                'status' => EmployeeLeaveStatus::Scheduled->value,
                'notes' => 'Primeira parcela',
            ])
            ->assertRedirect(route('client.ferias.index'));

        $leave = EmployeeLeave::query()->first();
        $this->assertNotNull($leave);
        $this->assertSame(42, (int) $leave->rhid_person_id);
        $this->assertSame('Colaborador Férias', $leave->employee_name);
        $this->assertSame(15, $leave->daysCount());

        $this->actingAs($admin)
            ->put(route('client.ferias.update', $leave), [
                'rhid_person_id' => 42,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-20',
                'status' => EmployeeLeaveStatus::InProgress->value,
                'notes' => 'Atualizado',
            ])
            ->assertRedirect(route('client.ferias.index'));

        $this->assertSame(EmployeeLeaveStatus::InProgress, $leave->fresh()->status);

        $this->actingAs($admin)
            ->delete(route('client.ferias.destroy', $leave))
            ->assertRedirect(route('client.ferias.index'));

        $this->assertDatabaseMissing('employee_leaves', ['id' => $leave->id]);
    }

    public function test_company_user_cannot_access_ferias(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Férias',
            'ferias_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $user = \App\Models\User::factory()->companyUser($company->id)->create();

        $this->actingAs($user)
            ->get(route('client.ferias.index'))
            ->assertForbidden();
    }

    private function bindRhidPerson(int $id, string $name, string $email): void
    {
        $person = ['id' => $id, 'name' => $name, 'email' => $email];

        $directory = Mockery::mock(RhidPersonDirectory::class);
        $directory->shouldReceive('activePersons')->andReturn(new Collection([$person]));
        $directory->shouldReceive('findActive')->andReturnUsing(
            fn ($company, $personId) => (int) $personId === $id ? $person : null,
        );

        $this->app->instance(RhidPersonDirectory::class, $directory);
    }
}
