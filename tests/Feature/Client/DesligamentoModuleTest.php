<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\ExitInterviewStatus;
use App\Models\Company;
use App\Models\ExitInterview;
use App\Support\Rhid\RhidPersonDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class DesligamentoModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_crud_exit_interview(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Desligamento',
            'desligamento_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = \App\Models\User::factory()->companyAdmin($company->id)->create();

        $this->bindRhidPerson(77, 'Colaborador Saída', 'saida@teste.local');

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.desligamento.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('client.desligamento.store'), [
                'rhid_person_id' => 77,
                'interview_date' => '2026-07-10',
                'status' => ExitInterviewStatus::Completed->value,
                'answers' => [
                    'q1' => 'Experiência positiva no geral.',
                    'q4' => 'Proposta melhor no mercado.',
                ],
                'consultant_notes' => [
                    'main_reasons' => 'Oportunidade externa.',
                    'consultant_perceptions' => 'Colaborador engajado até o fim.',
                ],
            ])
            ->assertRedirect(route('client.desligamento.index'));

        $interview = ExitInterview::query()->first();
        $this->assertNotNull($interview);
        $this->assertSame(77, (int) $interview->rhid_person_id);
        $this->assertSame('Colaborador Saída', $interview->employee_name);
        $this->assertSame('Experiência positiva no geral.', $interview->answers['q1']);
        $this->assertSame('Oportunidade externa.', $interview->consultant_notes['main_reasons']);

        $this->actingAs($admin)
            ->get(route('client.desligamento.show', $interview))
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('client.desligamento.destroy', $interview))
            ->assertRedirect(route('client.desligamento.index'));

        $this->assertDatabaseMissing('exit_interviews', ['id' => $interview->id]);
    }

    public function test_company_user_cannot_access_desligamento(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Desligamento',
            'desligamento_access' => true,
            'is_active' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $user = \App\Models\User::factory()->companyUser($company->id)->create();

        $this->actingAs($user)
            ->get(route('client.desligamento.index'))
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
