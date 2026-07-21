<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\CompanyInternalRegulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InternalRegulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_super_admin_can_open_index(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create(['name' => 'Empresa Reg', 'is_active' => true]);

        CompanyInternalRegulation::query()->create([
            'company_id' => $company->id,
            'title' => 'Regulamento geral',
            'body_html' => '<p>Conteúdo</p>',
            'is_published' => true,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.regulamento-interno.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/RegulamentoInterno/Index')
                ->has('regulations.data', 1)
                ->where('regulations.data.0.title', 'Regulamento geral'));
    }

    public function test_admin_can_create_regulation_with_sanitized_html(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create(['name' => 'Empresa HTML', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.regulamento-interno.store'), [
                'company_id' => $company->id,
                'title' => 'Código de conduta',
                'body_html' => '<p>Olá <script>alert(1)</script><strong>mundo</strong></p>',
                'is_published' => true,
            ])
            ->assertRedirect();

        $row = CompanyInternalRegulation::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('Código de conduta', $row->title);
        $this->assertStringContainsString('<strong>mundo</strong>', (string) $row->body_html);
        $this->assertStringNotContainsString('<script>', (string) $row->body_html);
    }

    public function test_coming_soon_redirects_to_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.coming-soon.show', 'regulamento-interno'))
            ->assertRedirect(route('admin.regulamento-interno.index'));
    }

    public function test_admin_can_delete_regulation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create(['name' => 'Empresa Del', 'is_active' => true]);
        $row = CompanyInternalRegulation::query()->create([
            'company_id' => $company->id,
            'title' => 'Para remover',
            'body_html' => '<p>x</p>',
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.regulamento-interno.destroy', $row))
            ->assertRedirect();

        $this->assertDatabaseMissing('company_internal_regulations', ['id' => $row->id]);
    }
}
