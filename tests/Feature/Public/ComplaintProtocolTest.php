<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Company;
use App\Models\Complaint;
use App\Support\Complaints\ComplaintProtocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComplaintProtocolTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_store_assigns_sequential_human_protocol(): void
    {
        $token = (string) Str::uuid();
        Company::query()->create([
            'name' => 'Empresa Protocolo',
            'cnpj' => '44.444.444/0001-44',
            'is_active' => true,
            'complaints_public_token' => $token,
        ]);

        $payload = [
            'category' => 'outros',
            'description' => str_repeat('x', 25),
            'is_anonymous' => true,
        ];

        $this->post(route('denuncia.store', ['token' => $token]), $payload)->assertRedirect();
        $this->post(route('denuncia.store', ['token' => $token]), $payload)->assertRedirect();

        $year = now()->year;
        $this->assertSame("DEN-{$year}-00001", Complaint::query()->orderBy('id')->value('protocol'));
        $this->assertSame("DEN-{$year}-00002", Complaint::query()->orderByDesc('id')->value('protocol'));
    }

    public function test_track_lookup_accepts_protocol_without_hyphens(): void
    {
        $token = (string) Str::uuid();
        $company = Company::query()->create([
            'name' => 'Empresa Acompanhar',
            'cnpj' => '55.555.555/0001-55',
            'is_active' => true,
            'complaints_public_token' => $token,
        ]);

        $protocol = sprintf('DEN-%d-00003', now()->year);
        Complaint::query()->create([
            'company_id' => $company->id,
            'protocol' => $protocol,
            'category' => 'outros',
            'description' => str_repeat('a', 25),
            'status' => 'new',
            'is_anonymous' => true,
        ]);

        $this->from(route('denuncia.track', $token))
            ->post(route('denuncia.track.lookup', ['token' => $token]), [
                'protocol' => str_replace('-', '', strtolower($protocol)),
            ])
            ->assertRedirect(route('denuncia.protocol', ['token' => $token, 'protocol' => $protocol]));
    }

    public function test_normalize_formats_compact_protocol(): void
    {
        $this->assertSame('DEN-2026-00001', ComplaintProtocol::normalize('den 2026 1'));
        $this->assertSame('DEN-2026-00001', ComplaintProtocol::normalize('DEN-2026-00001'));
        $this->assertTrue(ComplaintProtocol::isValid('DEN-2026-00001'));
        $this->assertFalse(ComplaintProtocol::isValid((string) Str::uuid()));
    }
}
