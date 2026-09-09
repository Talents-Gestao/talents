<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialProcess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CommercialProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_super_admin_can_open_index(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        CommercialProcess::query()->create([
            'title' => 'Processo de prospecção',
            'summary' => 'Resumo',
            'body_html' => '<p>Conteúdo</p>',
            'is_published' => true,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.processos.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Commercial/Processes/Index')
                ->has('processes.data', 1)
                ->where('processes.data.0.title', 'Processo de prospecção'));
    }

    public function test_admin_can_create_process_with_sanitized_html(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->post(route('admin.comercial.processos.store'), [
                'title' => 'Processo de fechamento',
                'summary' => 'Negociação e contrato',
                'body_html' => '<p>Olá <script>alert(1)</script><strong>mundo</strong></p>',
                'is_published' => true,
                'sort_order' => 2,
            ])
            ->assertRedirect();

        $row = CommercialProcess::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('Processo de fechamento', $row->title);
        $this->assertStringContainsString('<strong>mundo</strong>', (string) $row->body_html);
        $this->assertStringNotContainsString('<script>', (string) $row->body_html);
    }

    public function test_admin_can_attach_pdf_and_download(): void
    {
        Storage::fake('local');

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $file = UploadedFile::fake()->create('processo.pdf', 120, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('admin.comercial.processos.store'), [
                'title' => 'Processo com anexo',
                'body_html' => '<p>Texto</p>',
                'is_published' => true,
                'file' => $file,
            ])
            ->assertRedirect();

        $row = CommercialProcess::query()->first();
        $this->assertNotNull($row);
        $this->assertTrue($row->hasFile());
        Storage::disk('local')->assertExists($row->file_path);

        $this->actingAs($admin)
            ->get(route('admin.comercial.processos.download', $row))
            ->assertOk();
    }

    public function test_admin_can_view_and_update_process(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $process = CommercialProcess::query()->create([
            'title' => 'Original',
            'body_html' => '<p>A</p>',
            'is_published' => true,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.comercial.processos.show', $process))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Commercial/Processes/Show')
                ->where('process.title', 'Original'));

        $this->actingAs($admin)
            ->put(route('admin.comercial.processos.update', $process), [
                'title' => 'Atualizado',
                'body_html' => '<p>B</p>',
                'is_published' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('Atualizado', $process->fresh()->title);
    }
}
