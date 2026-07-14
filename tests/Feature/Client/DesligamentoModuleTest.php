<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\ExitInterviewStatus;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\ExitInterview;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $employee = CompanyEmployee::query()->create([
            'company_id' => $company->id,
            'name' => 'Colaborador Saída',
            'email' => 'saida@teste.local',
            'is_active' => true,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.desligamento.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('client.desligamento.store'), [
                'company_employee_id' => $employee->id,
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
}
