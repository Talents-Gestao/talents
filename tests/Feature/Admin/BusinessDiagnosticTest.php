<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessDiagnostic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BusinessDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_list_and_create_business_diagnostic(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.diagnostico-empresarial.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/BusinessDiagnostics/Index'));

        $this->actingAs($admin)
            ->post(route('admin.diagnostico-empresarial.store'), [
                'company_name' => 'Empresa Demo LTDA',
                'cnpj' => '12.345.678/0001-90',
                'segment' => 'Varejo',
                'employee_count' => '50',
                'responsible_name' => 'Maria Silva',
                'email' => 'maria@empresa.demo',
                'phone' => '11999999999',
                'company_history' => 'Empresa com 10 anos de mercado.',
                'biggest_challenge' => 'Engajamento da liderança.',
                'hr_maturity' => 7,
            ])
            ->assertRedirect();

        $diagnostic = BusinessDiagnostic::query()->where('email', 'maria@empresa.demo')->first();
        $this->assertNotNull($diagnostic);
        $this->assertSame('Empresa Demo LTDA', $diagnostic->company_name);
        $this->assertSame(7, $diagnostic->hr_maturity);
        $this->assertSame($admin->id, $diagnostic->created_by);

        $this->actingAs($admin)
            ->get(route('admin.diagnostico-empresarial.show', $diagnostic))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/BusinessDiagnostics/Show')
                ->where('diagnostic.company_name', 'Empresa Demo LTDA'));
    }

    public function test_coming_soon_redirects_to_business_diagnostic_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.coming-soon.show', 'diagnostico-empresarial'))
            ->assertRedirect(route('admin.diagnostico-empresarial.index'));
    }
}
