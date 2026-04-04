<?php

namespace Tests\Feature\Console;

use App\Models\AiSetting;
use App\Models\MailSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckEncryptionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_failures_for_invalid_ciphertext(): void
    {
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

        Artisan::call('app:check-encryption');
        $output = Artisan::output();

        $this->assertStringContainsString('AiSetting', $output);
        $this->assertStringContainsString('MailSetting', $output);
        $this->assertStringContainsString('1 falha(s)', $output);
    }

    public function test_command_reports_zero_failures_when_no_encrypted_data(): void
    {
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

        Artisan::call('app:check-encryption');
        $output = Artisan::output();

        $this->assertStringContainsString('0 falha(s)', $output);
    }
}
