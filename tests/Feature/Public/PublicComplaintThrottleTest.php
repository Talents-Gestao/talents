<?php

namespace Tests\Feature\Public;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicComplaintThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_complaint_store_throttle_returns_429_after_limit(): void
    {
        Config::set('public_rate_limits.complaint_store_per_minute', 4);

        $token = (string) Str::uuid();
        Company::query()->create([
            'name' => 'Empresa Denúncia',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => $token,
        ]);

        $url = route('denuncia.store', ['token' => $token]);
        $payload = [
            'category' => 'outros',
            'description' => str_repeat('x', 25),
            'is_anonymous' => true,
        ];

        for ($i = 0; $i < 4; $i++) {
            $this->post($url, $payload)->assertRedirect();
        }

        $this->post($url, $payload)->assertStatus(429);
    }

    public function test_complaint_track_lookup_throttle_returns_429_after_limit(): void
    {
        Config::set('public_rate_limits.complaint_track_lookup_per_minute', 3);

        $token = (string) Str::uuid();
        Company::query()->create([
            'name' => 'Empresa Track',
            'cnpj' => '33.333.333/0001-33',
            'is_active' => true,
            'complaints_public_token' => $token,
        ]);

        $url = route('denuncia.track.lookup', ['token' => $token]);
        $payload = ['protocol' => (string) Str::uuid()];

        for ($i = 0; $i < 3; $i++) {
            $this->post($url, $payload);
        }

        $this->post($url, $payload)->assertStatus(429);
    }
}
