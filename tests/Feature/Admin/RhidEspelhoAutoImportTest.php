<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\ProcessRhidEspelhoBatchJob;
use App\Models\Company;
use App\Models\RhidEspelhoBatch;
use App\Models\RhidEspelhoDay;
use App\Models\RhidEspelhoImport;
use App\Models\User;
use App\Services\Rhid\RhidComplianceService;
use App\Services\Rhid\RhidEspelhoAutoImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RhidEspelhoAutoImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-15 00:30:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_skips_company_without_rhid_credentials(): void
    {
        Bus::fake();
        $this->mockActivePeople([10]);

        Company::query()->create([
            'name' => 'Sem senha',
            'is_active' => true,
            'rhid_access' => true,
        ]);

        $results = app(RhidEspelhoAutoImportService::class)->enqueueClosedDay();

        $this->assertSame([], $results);
        Bus::assertNotDispatched(ProcessRhidEspelhoBatchJob::class);
        $this->assertSame(0, RhidEspelhoBatch::query()->count());
    }

    public function test_skips_when_closed_day_already_imported(): void
    {
        Bus::fake();
        $this->mockActivePeople([10, 20]);
        $company = $this->rhidCompany();
        $this->seedOkDay($company, 10, '2026-04-14');
        $this->seedOkDay($company, 20, '2026-04-14');

        $results = app(RhidEspelhoAutoImportService::class)->enqueueClosedDay($company->id);

        $this->assertCount(1, $results);
        $this->assertSame('skipped', $results[0]['action']);
        $this->assertStringContainsString('já têm o dia fechado', (string) $results[0]['reason']);
        Bus::assertNotDispatched(ProcessRhidEspelhoBatchJob::class);
    }

    public function test_empty_month_queues_backfill_from_first_day_to_closed_day(): void
    {
        Bus::fake();
        $this->mockActivePeople([10, 20]);
        $company = $this->rhidCompany();

        $results = app(RhidEspelhoAutoImportService::class)->enqueueClosedDay($company->id);

        $this->assertCount(1, $results);
        $this->assertSame('queued', $results[0]['action']);
        $this->assertTrue($results[0]['backfill']);
        $this->assertSame('2026-04-01', $results[0]['ini']);
        $this->assertSame('2026-04-14', $results[0]['fim']);
        $this->assertSame(2, $results[0]['people']);

        $batch = RhidEspelhoBatch::query()->first();
        $this->assertNotNull($batch);
        $this->assertNull($batch->user_id);
        $this->assertSame(RhidEspelhoBatch::SOURCE_AUTOMATIC, $batch->meta_json['source']);
        $this->assertSame('20260401', $batch->meta_json['ini']);
        $this->assertSame('20260414', $batch->meta_json['fim']);
        $this->assertSame([10, 20], $batch->meta_json['person_ids']);
        Bus::assertDispatched(ProcessRhidEspelhoBatchJob::class, fn (ProcessRhidEspelhoBatchJob $job): bool => $job->batchId === $batch->id);
    }

    public function test_month_with_data_queues_only_closed_day(): void
    {
        Bus::fake();
        $this->mockActivePeople([10]);
        $company = $this->rhidCompany();
        $this->seedOkDay($company, 10, '2026-04-10');

        $results = app(RhidEspelhoAutoImportService::class)->enqueueClosedDay($company->id);

        $this->assertSame('queued', $results[0]['action']);
        $this->assertFalse($results[0]['backfill']);
        $this->assertSame('2026-04-14', $results[0]['ini']);
        $this->assertSame('2026-04-14', $results[0]['fim']);
    }

    public function test_does_not_queue_when_batch_already_running(): void
    {
        Bus::fake();
        $this->mockActivePeople([10]);
        $company = $this->rhidCompany();

        RhidEspelhoBatch::query()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'status' => RhidEspelhoBatch::STATUS_RUNNING,
            'total' => 1,
            'processed' => 0,
            'succeeded' => 0,
            'skipped' => 0,
            'meta_json' => ['source' => RhidEspelhoBatch::SOURCE_AUTOMATIC],
        ]);

        $results = app(RhidEspelhoAutoImportService::class)->enqueueClosedDay($company->id);

        $this->assertSame('skipped', $results[0]['action']);
        $this->assertStringContainsString('em andamento', (string) $results[0]['reason']);
        Bus::assertNotDispatched(ProcessRhidEspelhoBatchJob::class);
        $this->assertSame(1, RhidEspelhoBatch::query()->count());
    }

    public function test_command_queues_only_the_requested_company(): void
    {
        Bus::fake();
        $this->mockActivePeople([7]);
        $target = $this->rhidCompany(['name' => 'Alvo']);
        $this->rhidCompany(['name' => 'Outra']);

        $this->artisan('rhid:import-closed-day', [
            '--company' => $target->id,
            '--date' => '2026-04-14',
        ])->assertSuccessful();

        $this->assertSame(1, RhidEspelhoBatch::query()->count());
        $this->assertSame($target->id, RhidEspelhoBatch::query()->value('company_id'));
        Bus::assertDispatched(ProcessRhidEspelhoBatchJob::class);
    }

    public function test_admin_can_read_automatic_import_status(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->rhidCompany();

        RhidEspelhoBatch::query()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'status' => 'completed',
            'total' => 2,
            'processed' => 2,
            'succeeded' => 2,
            'skipped' => 0,
            'meta_json' => [
                'source' => RhidEspelhoBatch::SOURCE_AUTOMATIC,
                'closed_day' => '2026-04-14',
                'backfill' => true,
            ],
            'message' => 'Importados 2 PDF(s).',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.ponto.companies.espelhos.auto-import', $company))
            ->assertOk()
            ->assertJsonPath('batch.source', 'automatic')
            ->assertJsonPath('batch.closed_day', '2026-04-14')
            ->assertJsonPath('batch.succeeded', 2);
    }

    /**
     * @param  list<int>  $ids
     */
    private function mockActivePeople(array $ids): void
    {
        $this->mock(RhidComplianceService::class, function ($mock) use ($ids): void {
            $mock->shouldReceive('listPersons')->andReturn([
                'data' => array_map(
                    static fn (int $id): array => ['id' => $id, 'name' => 'Pessoa '.$id, 'status' => 1],
                    $ids,
                ),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function rhidCompany(array $extra = []): Company
    {
        return Company::query()->create(array_merge([
            'name' => 'Auto RHID',
            'is_active' => true,
            'rhid_access' => true,
            'rhid_email' => 'ponto@example.com',
            'rhid_password' => 'secret',
        ], $extra));
    }

    private function seedOkDay(Company $company, int $idPerson, string $date): void
    {
        $import = RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'id_person' => $idPerson,
            'period_ini' => $date,
            'period_fim' => $date,
            'guid' => 'g-'.$idPerson.'-'.$date,
            'storage_path' => 'rhid-espelhos/'.$company->id.'/'.$idPerson.'.pdf',
            'source' => RhidEspelhoImport::SOURCE_API,
            'parse_status' => 'ok',
            'parsed_at' => now(),
        ]);

        RhidEspelhoDay::query()->create([
            'import_id' => $import->id,
            'ref_date' => $date,
            'row_json' => [],
        ]);
    }
}
