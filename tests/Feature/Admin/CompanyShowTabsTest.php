<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CompanyShowTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_removed_tabs_fall_back_to_empresa(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Tabs',
            'is_active' => true,
        ]);

        foreach (['rhid', 'destaques', 'ferias', 'uniformes', 'inexistente'] as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.companies.show', ['company' => $company->id, 'tab' => $tab]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Admin/Companies/Show')
                    ->where('tab', 'empresa')
                );
        }
    }

    public function test_kept_tabs_still_work(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Tabs Ok',
            'is_active' => true,
        ]);

        foreach (['empresa', 'ponto', 'colaboradores', 'regulamento'] as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.companies.show', ['company' => $company->id, 'tab' => $tab]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Admin/Companies/Show')
                    ->where('tab', $tab)
                );
        }
    }

    public function test_companies_index_paginates_after_first_page(): void
    {
        $admin = User::factory()->superAdmin()->create();

        for ($i = 1; $i <= 16; $i++) {
            Company::query()->create([
                'name' => sprintf('Empresa Lista %02d', $i),
                'is_active' => true,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.companies.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Companies/Index')
                ->has('companies.data', 15)
                ->where('companies.total', 16)
                ->where('companies.last_page', 2)
            );

        $this->actingAs($admin)
            ->get(route('admin.companies.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('companies.data', 1)
            );
    }
}
