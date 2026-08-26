<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\CompanyInternalRegulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
                ->component('Admin/InternalRegulations/Index')
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

    public function test_admin_can_create_regulation_with_pdf_attachment(): void
    {
        Storage::fake('local');

        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create(['name' => 'Empresa Anexo', 'is_active' => true]);
        $file = UploadedFile::fake()->create('regulamento.pdf', 120, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('admin.regulamento-interno.store'), [
                'company_id' => $company->id,
                'title' => 'Regulamento com anexo',
                'body_html' => '<p>Texto</p>',
                'is_published' => false,
                'file' => $file,
            ])
            ->assertRedirect();

        $row = CompanyInternalRegulation::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('regulamento.pdf', $row->file_name);
        $this->assertNotNull($row->file_path);
        Storage::disk('local')->assertExists($row->file_path);

        $this->actingAs($admin)
            ->get(route('admin.regulamento-interno.download', $row))
            ->assertOk();
    }

    public function test_admin_can_remove_regulation_file_on_update(): void
    {
        Storage::fake('local');

        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create(['name' => 'Empresa Remove Anexo', 'is_active' => true]);
        $path = 'internal-regulations/1/old.pdf';
        Storage::disk('local')->put($path, 'conteudo');

        $row = CompanyInternalRegulation::query()->create([
            'company_id' => $company->id,
            'title' => 'Com arquivo',
            'body_html' => '<p>x</p>',
            'file_path' => $path,
            'file_name' => 'old.pdf',
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.regulamento-interno.update', $row), [
                'company_id' => $company->id,
                'title' => 'Com arquivo',
                'body_html' => '<p>x</p>',
                'is_published' => false,
                'remove_file' => true,
            ])
            ->assertRedirect();

        $row->refresh();
        $this->assertNull($row->file_path);
        $this->assertNull($row->file_name);
        Storage::disk('local')->assertMissing($path);
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
        Storage::fake('local');

        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create(['name' => 'Empresa Del', 'is_active' => true]);
        $path = 'internal-regulations/99/doc.pdf';
        Storage::disk('local')->put($path, 'pdf');

        $row = CompanyInternalRegulation::query()->create([
            'company_id' => $company->id,
            'title' => 'Para remover',
            'body_html' => '<p>x</p>',
            'file_path' => $path,
            'file_name' => 'doc.pdf',
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.regulamento-interno.destroy', $row))
            ->assertRedirect();

        $this->assertDatabaseMissing('company_internal_regulations', ['id' => $row->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
