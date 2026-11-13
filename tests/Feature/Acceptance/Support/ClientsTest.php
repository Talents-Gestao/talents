<?php

// Cartão do quadro SUPORTE: «CLIENTES» (Ramo de Atividade e Mês do Dissídio no cadastro da empresa).
// Já cobertos: atualizar os dois campos e mês 13 inválido (Admin/CompanyActivityFieldsTest).

namespace Tests\Feature\Acceptance\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_company_saves_activity_branch_and_bargaining_month_and_shows_them_when_editing(): void
    {
        $this->withoutVite();
        Mail::fake();
        $admin = User::factory()->talentsOwner()->create();

        $this->actingAs($admin)
            ->post(route('admin.companies.store'), [
                'name' => 'Padaria Aceite',
                'contact_email' => 'rh@padaria-aceite.test',
                'activity_branch' => '  Panificação  ',
                'collective_bargaining_month' => 5,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $company = Company::query()->where('name', 'Padaria Aceite')->firstOrFail();
        $this->assertSame('Panificação', $company->activity_branch);
        $this->assertSame(5, $company->collective_bargaining_month);

        $this->get(route('admin.companies.edit', $company))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Companies/Edit')
                ->where('company.activity_branch', 'Panificação')
                ->where('company.collective_bargaining_month', 5));
    }

    public function test_bargaining_month_outside_one_to_twelve_is_rejected_on_creation(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->talentsOwner()->create())
            ->post(route('admin.companies.store'), [
                'name' => 'Empresa Mês Inválido',
                'contact_email' => 'rh@mes-invalido.test',
                'collective_bargaining_month' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('collective_bargaining_month');

        $this->assertDatabaseMissing('companies', ['name' => 'Empresa Mês Inválido']);
    }
}
