<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyLogoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_logo_on_create(): void
    {
        $this->withoutVite();
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->post(route('admin.companies.store'), [
                'name' => 'Empresa Logo',
                'contact_email' => 'admin-logo@example.test',
                'is_active' => true,
                'logo' => UploadedFile::fake()->image('logo.png', 800, 800),
            ])
            ->assertRedirect();

        $company = Company::query()->where('name', 'Empresa Logo')->firstOrFail();
        $this->assertNotNull($company->logo_path);
        $this->assertSame('public', $company->logo_disk);
        Storage::disk('public')->assertExists($company->logo_path);
        $this->assertNotNull($company->logo_url);
    }

    public function test_admin_can_replace_and_remove_logo(): void
    {
        $this->withoutVite();
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Cliente X',
            'contact_email' => 'x@example.test',
            'is_active' => true,
        ]);

        $first = UploadedFile::fake()->image('a.png', 400, 400);
        $company->storeLogo($first);
        $oldPath = $company->fresh()->logo_path;
        $this->assertNotNull($oldPath);
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($admin)
            ->post(route('admin.companies.update', $company), [
                '_method' => 'put',
                'name' => 'Cliente X',
                'contact_email' => 'x@example.test',
                'is_active' => true,
                'strategic_calendar_access_mode' => 'inherit',
                'tasks_access_mode' => 'inherit',
                'rhid_access_mode' => 'inherit',
                'denuncias_access_mode' => 'inherit',
                'ferias_access_mode' => 'inherit',
                'desligamento_access_mode' => 'inherit',
                'acompanhamento_access_mode' => 'inherit',
                'logo' => UploadedFile::fake()->image('b.webp', 512, 512),
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $company->refresh();
        $this->assertNotSame($oldPath, $company->logo_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($company->logo_path);

        $path = $company->logo_path;

        $this->actingAs($admin)
            ->post(route('admin.companies.update', $company), [
                '_method' => 'put',
                'name' => 'Cliente X',
                'contact_email' => 'x@example.test',
                'is_active' => true,
                'strategic_calendar_access_mode' => 'inherit',
                'tasks_access_mode' => 'inherit',
                'rhid_access_mode' => 'inherit',
                'denuncias_access_mode' => 'inherit',
                'ferias_access_mode' => 'inherit',
                'desligamento_access_mode' => 'inherit',
                'acompanhamento_access_mode' => 'inherit',
                'remove_logo' => true,
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $company->refresh();
        $this->assertNull($company->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_logo_rejects_invalid_mime(): void
    {
        $this->withoutVite();
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Cliente Y',
            'contact_email' => 'y@example.test',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.companies.edit', $company))
            ->post(route('admin.companies.update', $company), [
                '_method' => 'put',
                'name' => 'Cliente Y',
                'contact_email' => 'y@example.test',
                'is_active' => true,
                'strategic_calendar_access_mode' => 'inherit',
                'tasks_access_mode' => 'inherit',
                'rhid_access_mode' => 'inherit',
                'denuncias_access_mode' => 'inherit',
                'ferias_access_mode' => 'inherit',
                'desligamento_access_mode' => 'inherit',
                'acompanhamento_access_mode' => 'inherit',
                'logo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.companies.edit', $company))
            ->assertSessionHasErrors('logo');
    }
}
