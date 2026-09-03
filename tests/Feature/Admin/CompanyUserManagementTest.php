<?php

namespace Tests\Feature\Admin;

use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_company_users_index(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa Admin',
            'cnpj' => '77.777.777/0001-77',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->get(route('admin.companies.users.index', $company))
            ->assertOk();
    }

    public function test_company_admin_cannot_view_admin_company_users_index(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa C',
            'cnpj' => '88.888.888/0001-88',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('admin.companies.users.index', $company))
            ->assertForbidden();
    }

    public function test_super_admin_can_resend_invitation_for_pending_company_user(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Convite',
            'cnpj' => '99.999.999/0001-99',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $super = User::factory()->superAdmin()->create();

        $pending = User::factory()->companyUser($company->id)->pendingRegistration()->create();

        $this->actingAs($super)
            ->post(route('admin.companies.users.resend-invitation', [$company, $pending]))
            ->assertRedirect(route('admin.companies.users.index', $company));

        Mail::assertSent(UserInvitationMail::class, fn ($mail) => $mail->hasTo($pending->email));
    }

    public function test_super_admin_can_resend_password_reset_for_registered_company_user(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa OK',
            'cnpj' => '11.111.111/0001-11',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $super = User::factory()->superAdmin()->create();
        $registered = User::factory()->companyUser($company->id)->create();

        $this->actingAs($super)
            ->post(route('admin.companies.users.resend-invitation', [$company, $registered]))
            ->assertRedirect(route('admin.companies.users.index', $company))
            ->assertSessionHas('success');

        Mail::assertSent(UserInvitationMail::class, fn ($mail) => $mail->hasTo($registered->email));
    }

    public function test_company_user_form_does_not_include_hidden_capacitacao_module(): void
    {
        $this->withoutVite();

        $company = Company::query()->create([
            'name' => 'Empresa Permissões',
            'cnpj' => '55.555.555/0001-55',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);

        $super = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($super)
            ->get(route('admin.companies.users.create', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Companies/Users/Form')
                ->where(
                    'permissionModules',
                    fn ($modules) => collect($modules)->pluck('value')->doesntContain('capacitacao')
                        && collect($modules)->pluck('label')->doesntContain('Capacitação'),
                )
            );
    }
}
