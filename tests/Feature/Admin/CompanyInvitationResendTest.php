<?php

namespace Tests\Feature\Admin;

use App\Mail\CompanyAdminInvitationMail;
use App\Models\Company;
use App\Models\User;
use App\Support\InvitationPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyInvitationResendTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_without_permission_cannot_resend_invitation(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Pendente',
            'contact_email' => 'admin@empresa.test',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        User::factory()->companyAdmin($company->id)->pendingRegistration()->create(['email' => 'admin@empresa.test']);

        $super = User::factory()->superAdmin()->create(['is_owner' => false]);

        $this->actingAs($super)
            ->post(route('admin.companies.resend-invitation', $company))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_super_admin_can_resend_company_registration_invitation(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Pendente',
            'contact_email' => 'admin@empresa.test',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        User::factory()->companyAdmin($company->id)->pendingRegistration()->create([
            'email' => 'admin@empresa.test',
        ]);

        $super = User::factory()->talentsOwner()->create();

        $this->actingAs($super)
            ->post(route('admin.companies.resend-invitation', $company))
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(CompanyAdminInvitationMail::class, fn ($mail) => $mail->hasTo('admin@empresa.test'));
    }

    public function test_super_admin_can_resend_password_reset_for_registered_company(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Ativa',
            'contact_email' => 'ok@empresa.test',
            'cnpj' => '33.333.333/0001-33',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        User::factory()->companyAdmin($company->id)->create([
            'email' => 'ok@empresa.test',
        ]);

        $super = User::factory()->talentsOwner()->create();

        $this->actingAs($super)
            ->post(route('admin.companies.resend-invitation', $company))
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(CompanyAdminInvitationMail::class, fn ($mail) => $mail->hasTo('ok@empresa.test'));
    }

    public function test_registered_user_can_reset_password_via_resent_invitation(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Ativa',
            'contact_email' => 'ok@empresa.test',
            'cnpj' => '33.333.333/0001-33',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $admin = User::factory()->companyAdmin($company->id)->create([
            'email' => 'ok@empresa.test',
        ]);

        $super = User::factory()->talentsOwner()->create();

        $this->actingAs($super)
            ->post(route('admin.companies.resend-invitation', $company))
            ->assertSessionHas('success');

        $this->post(route('logout'));

        $token = InvitationPassword::createToken($admin);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $admin->refresh();
        $this->assertTrue(Hash::check('nova-senha-123', $admin->password));
    }

    public function test_resend_company_user_invitation_uses_admin_mail_for_company_admin(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Admin',
            'contact_email' => 'admin@empresa.test',
            'cnpj' => '44.444.444/0001-44',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $super = User::factory()->talentsOwner()->create();

        $pendingAdmin = User::factory()->companyAdmin($company->id)->pendingRegistration()->create([
            'email' => 'admin@empresa.test',
        ]);

        $this->actingAs($super)
            ->post(route('admin.companies.users.resend-invitation', [$company, $pendingAdmin]))
            ->assertRedirect(route('admin.companies.users.index', $company));

        Mail::assertSent(CompanyAdminInvitationMail::class, fn ($mail) => $mail->hasTo('admin@empresa.test'));
    }
}
