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
}
