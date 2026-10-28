<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\Rhid\RhidClient;
use App\Services\Rhid\RhidComplianceService;
use App\Support\RhidBankBalanceFormat;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Mockery;
use Tests\TestCase;

/**
 * Pipeline person_banco_horas sem HTTP real nem base de dados (Mockery no RhidClient).
 */
class RhidPersonBankHoursPipelineTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_pipeline_preserves_saldo_minus_1581_and_matches_minus_26_21_display(): void
    {
        $payload = [
            'data' => [
                [
                    'excluded' => false,
                    'cpf' => '52664958801',
                    'departmentName' => 'DESENVOLVIMENTO',
                    'idDepartment' => 7,
                    'idPersonRole' => null,
                    'id_funcionario' => 0,
                    'name' => 'PEDRO PAULO BIROLIM JACINTO',
                    'pis' => 52664958801,
                    'registration' => null,
                    'roleName' => null,
                    'saldoBancoHoras' => -1581,
                    'socialName' => null,
                ],
            ],
        ];

        $client = Mockery::mock(RhidClient::class);
        $client->shouldReceive('request')->andReturnUsing(function ($company, $user, $method, $path) use ($payload) {
            $psr = new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode(
                str_contains((string) $path, 'person_banco_horas')
                    ? $payload
                    : ['data' => []],
            ));

            return new Response($psr);
        });

        $compliance = new RhidComplianceService($client);
        $company = Mockery::mock(Company::class);
        $company->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $company->allows('rhidConfigured')->andReturn(true);
        $company->allows('usesLocalPontoFixture')->andReturn(false);

        $rows = $compliance->personBankHours($company, null, ['date' => '20260413']);

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(-1581, $row['saldoBancoHoras']);
        $this->assertSame('-26:21', RhidBankBalanceFormat::minutesToHhMm((float) $row['saldoBancoHoras']));

        $espelho = RhidBankBalanceFormat::hhMmToSignedMinutes('-06:05');
        $this->assertSame(-365, $espelho);
        $this->assertNotSame($espelho, $row['saldoBancoHoras']);
    }
}
