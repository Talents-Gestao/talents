<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\MonthlyHighlightCategory;
use App\Models\Company;
use App\Models\CompanyMonthlyHighlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MonthlyHighlightTest extends TestCase
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
        $company = Company::query()->create(['name' => 'Empresa Destaque', 'is_active' => true]);

        CompanyMonthlyHighlight::query()->create([
            'company_id' => $company->id,
            'person_name' => 'Ana Silva',
            'category' => MonthlyHighlightCategory::Produtividade,
            'year' => 2026,
            'month' => 7,
            'is_published' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.destaques-mes.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/DestaquesMes/Index')
                ->has('highlights.data', 1)
                ->where('highlights.data.0.person_name', 'Ana Silva'));
    }

    public function test_admin_can_create_highlight_with_photo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create(['name' => 'Empresa Foto', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.destaques-mes.store'), [
                'company_id' => $company->id,
                'person_name' => 'Bruno Costa',
                'category' => MonthlyHighlightCategory::Pontualidade->value,
                'year' => 2026,
                'month' => 6,
                'description' => 'Chegou sempre no horário.',
                'is_published' => true,
                'photo' => UploadedFile::fake()->image('bruno.jpg', 200, 200),
            ])
            ->assertRedirect();

        $row = CompanyMonthlyHighlight::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('Bruno Costa', $row->person_name);
        $this->assertSame(MonthlyHighlightCategory::Pontualidade, $row->category);
        $this->assertNotNull($row->photo_path);
        Storage::disk('public')->assertExists($row->photo_path);
    }

    public function test_coming_soon_redirects_to_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.coming-soon.show', 'destaques-mes'))
            ->assertRedirect(route('admin.destaques-mes.index'));
    }

    public function test_admin_can_delete_highlight_and_photo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create(['name' => 'Empresa Del', 'is_active' => true]);

        $path = UploadedFile::fake()->image('x.jpg')->store('monthly-highlights/1', 'public');

        $row = CompanyMonthlyHighlight::query()->create([
            'company_id' => $company->id,
            'person_name' => 'Para remover',
            'category' => MonthlyHighlightCategory::Engajamento,
            'year' => 2026,
            'month' => 1,
            'photo_path' => $path,
            'photo_disk' => 'public',
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.destaques-mes.destroy', $row))
            ->assertRedirect();

        $this->assertDatabaseMissing('company_monthly_highlights', ['id' => $row->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
