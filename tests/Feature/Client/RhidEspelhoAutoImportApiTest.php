<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Jobs\ProcessRhidEspelhoBatchJob;
use App\Models\Company;
use App\Models\RhidEspelhoBatch;
use App\Models\User;
use App\Services\Rhid\RhidComplianceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RhidEspelhoAutoImportApiTest extends TestCase
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

    public function test_guest_cannot_read_auto_import_status(): void
    {
        $this->getJson(route('client.rhid.api.espelhos.auto-import'))
            ->assertUnauthorized();
    }

    public function test_company_admin_can_read_automatic_import_status(): void
    {
        $company = $this->rhidCompany();
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

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
            ->getJson(route('client.rhid.api.espelhos.auto-import'))
            ->assertOk()
            ->assertJsonPath('batch.source', 'automatic')
            ->assertJsonPath('batch.closed_day', '2026-04-14')
            ->assertJsonPath('batch.succeeded', 2);
    }

    public function test_company_admin_can_enqueue_automatic_import_now(): void
    {
        Bus::fake();
        $this->mockActivePeople([10, 20]);
        $company = $this->rhidCompany();
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->postJson(route('client.rhid.api.espelhos.auto-import.run'))
            ->assertOk()
            ->assertJsonPath('result.action', 'queued')
            ->assertJsonPath('result.backfill', true)
            ->assertJsonPath('batch.status', RhidEspelhoBatch::STATUS_PENDING);

        Bus::assertDispatched(ProcessRhidEspelhoBatchJob::class);
    }

    public function test_rejects_run_when_rhid_not_configured(): void
    {
        Bus::fake();
        $company = Company::query()->create([
            'name' => 'Sem senha',
            'is_active' => true,
            'rhid_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->postJson(route('client.rhid.api.espelhos.auto-import.run'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Integração RHID não configurada para esta empresa.');

        Bus::assertNotDispatched(ProcessRhidEspelhoBatchJob::class);
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

    private function rhidCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Cliente Auto RHID',
            'is_active' => true,
            'rhid_access' => true,
            'rhid_email' => 'ponto@example.com',
            'rhid_password' => 'secret',
        ]);
    }
}
