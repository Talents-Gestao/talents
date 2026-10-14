<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessRhidEspelhoParseJob;
use App\Models\Company;
use App\Models\RhidEspelhoImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProcessRhidEspelhoParseJobFailedTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_marks_pending_import_as_failed(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Espelho Fila',
            'is_active' => true,
        ]);

        $import = RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'id_person' => 12,
            'period_ini' => '2026-04-01',
            'period_fim' => '2026-04-14',
            'guid' => 'guid-fila',
            'storage_path' => 'rhid-espelhos/'.$company->id.'/x.pdf',
            'source' => 'api',
            'parse_status' => 'pending',
        ]);

        $job = new ProcessRhidEspelhoParseJob($import->id);
        $job->failed(new RuntimeException('worker timeout'));

        $import->refresh();
        $this->assertSame('failed', $import->parse_status);
        $this->assertStringContainsString('worker timeout', (string) $import->parse_error);
    }

    public function test_failed_does_not_overwrite_successful_parse(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Espelho Ok',
            'is_active' => true,
        ]);

        $import = RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'id_person' => 13,
            'period_ini' => '2026-04-01',
            'period_fim' => '2026-04-14',
            'guid' => 'guid-ok',
            'storage_path' => 'rhid-espelhos/'.$company->id.'/ok.pdf',
            'source' => 'api',
            'parse_status' => 'ok',
        ]);

        $job = new ProcessRhidEspelhoParseJob($import->id);
        $job->failed(new RuntimeException('should ignore'));

        $import->refresh();
        $this->assertSame('ok', $import->parse_status);
        $this->assertNull($import->parse_error);
    }
}
