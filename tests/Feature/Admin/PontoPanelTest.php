<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PontoPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_super_admin_can_open_ponto_hub(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Company::query()->create([
            'name' => 'Empresa RHID',
            'is_active' => true,
            'rhid_email' => 'ponto@example.com',
            'rhid_password' => 'secret',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ponto.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/TimeClock/Index')
                ->has('companies', 1)
                ->where('companies.0.name', 'Empresa RHID'));
    }

    public function test_coming_soon_ponto_redirects_to_hub(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.coming-soon.show', 'ponto'))
            ->assertRedirect(route('admin.ponto.index'));
    }

    public function test_last_punches_requires_rhid_credentials(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Sem RHID',
            'is_active' => true,
            'rhid_email' => null,
            'rhid_password' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ponto.companies.last-punches', $company))
            ->assertStatus(422);
    }

    public function test_store_justification_validates_payload(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Justificativas',
            'is_active' => true,
            'rhid_email' => 'ponto@example.com',
            'rhid_password' => 'secret',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ponto.companies.justifications.store', $company), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'idPerson',
                'idJustificationType',
                'justificativa',
                'inicio',
                'fim',
            ]);
    }

    public function test_list_justifications_requires_period(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Lista Just',
            'is_active' => true,
            'rhid_email' => 'ponto@example.com',
            'rhid_password' => 'secret',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ponto.companies.justifications.list', $company), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ini', 'fim']);
    }
}
