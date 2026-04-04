<?php

namespace Tests\Feature\Admin;

use App\Models\AiSetting;
use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect(route('login'));
    }

    public function test_non_super_admin_cannot_access_admin_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/settings')
            ->assertForbidden();
    }

    public function test_super_admin_can_view_admin_settings(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/admin/settings')
            ->assertOk();
    }

    /**
     * Regressão: payload inválido ou criptografado com outra APP_KEY não pode quebrar a página
     * ao montar api_key_set / password_set (uso de getRawOriginal, sem decrypt só para exibir).
     */
    public function test_admin_settings_loads_when_stored_encrypted_columns_are_unreadable(): void
    {
        $user = User::factory()->superAdmin()->create();

        AiSetting::query()->create([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'is_enabled' => false,
            'max_tokens' => 2000,
            'temperature' => 0.30,
        ]);

        MailSetting::query()->create([
            'port' => 587,
            'is_enabled' => false,
        ]);

        DB::table('ai_settings')->update(['api_key' => 'not-valid-laravel-encrypted-payload']);
        DB::table('mail_settings')->update(['password' => 'also-invalid-ciphertext']);

        $this->actingAs($user)
            ->get('/admin/settings')
            ->assertOk();
    }

    public function test_admin_settings_marks_api_key_and_password_unreadable_when_ciphertext_invalid(): void
    {
        $user = User::factory()->superAdmin()->create();

        AiSetting::query()->create([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'is_enabled' => false,
            'max_tokens' => 2000,
            'temperature' => 0.30,
        ]);

        MailSetting::query()->create([
            'port' => 587,
            'is_enabled' => false,
        ]);

        DB::table('ai_settings')->update(['api_key' => 'not-valid-laravel-encrypted-payload']);
        DB::table('mail_settings')->update(['password' => 'also-invalid-ciphertext']);

        $this->actingAs($user)
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Settings/Index')
                ->where('aiSettings.api_key_readable', false)
                ->where('mailSettings.password_readable', false));
    }
}
