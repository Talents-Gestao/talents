<?php

namespace Tests\Feature\Client;

use App\Enums\EmployeeLeaveStatus;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyRhidScheduleSetting;
use App\Models\EmployeeLeave;
use App\Models\RhidEspelhoDay;
use App\Models\RhidEspelhoImport;
use App\Models\User;
use App\Services\Rhid\RhidComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EspelhoMonthlyComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_monthly_compliance(): void
    {
        $this->getJson(route('client.rhid.api.espelhos.monthly-compliance', [
            'ini' => '2026-04-01',
            'fim' => '2026-04-30',
        ]))->assertUnauthorized();
    }

    public function test_rejects_range_over_max_days(): void
    {
        $company = Company::query()->create(['name' => 'E']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.monthly-compliance', [
                'ini' => '2026-01-01',
                'fim' => '2026-06-01',
            ]))
            ->assertStatus(422);
    }

    public function test_ranking_top5_excludes_feriado_atestado_and_ferias(): void
    {
        $company = Company::query()->create([
            'name' => 'Emp Cumprimento',
            'rhid_email' => 'rhid@example.com',
            'rhid_password' => 'secret',
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->seedMondaySchedule($company);
        $this->seedEspelhoDays($company, $admin, 100, 'Ana Completa', ['2026-04-06', '2026-04-13', '2026-04-20']);
        $this->seedEspelhoDays($company, $admin, 200, 'Bruno Parcial', ['2026-04-06', '2026-04-13']);

        $employee = CompanyEmployee::query()->create([
            'company_id' => $company->id,
            'name' => 'Ana Completa',
            'email' => 'ana@example.com',
            'is_active' => true,
        ]);
        EmployeeLeave::query()->create([
            'company_id' => $company->id,
            'company_employee_id' => $employee->id,
            'rhid_person_id' => 100,
            'employee_name' => 'Ana Completa',
            'employee_email' => 'ana@example.com',
            'start_date' => '2026-04-20',
            'end_date' => '2026-04-20',
            'status' => EmployeeLeaveStatus::Scheduled,
            'created_by' => $admin->id,
        ]);

        $mock = Mockery::mock(RhidComplianceService::class);
        $mock->shouldReceive('listJustificationTypes')->andReturn([
            'data' => [
                ['id' => 1, 'name' => 'Feriado'],
                ['id' => 2, 'name' => 'Atestado médico'],
            ],
        ]);
        $mock->shouldReceive('listJustifications')->andReturn([
            'data' => [
                [
                    'idPerson' => 999,
                    'idJustificationType' => 1,
                    'inicio' => '2026-04-13',
                    'fim' => '2026-04-13',
                    'justificativa' => 'Feriado',
                ],
                [
                    'idPerson' => 200,
                    'idJustificationType' => 2,
                    'inicio' => '2026-04-20',
                    'fim' => '2026-04-20',
                    'justificativa' => 'Atestado',
                ],
            ],
            'recordsTotal' => 2,
        ]);
        $this->app->instance(RhidComplianceService::class, $mock);

        // Dias úteis (só seg): 06, 13, 20.
        // Feriado 13 → esperado base = {06, 20}.
        // Ana (100): férias em 20 → esperado {06}; cumpridos {06} → 100%.
        // Bruno (200): atestado em 20 → esperado {06}; cumpridos {06} → 100%.
        $payload = $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.monthly-compliance', [
                'ini' => '2026-04-06',
                'fim' => '2026-04-20',
            ]))
            ->assertOk()
            ->assertJsonPath('resumo.dias_uteis_no_periodo', 3)
            ->assertJsonPath('resumo.dias_feriado_no_periodo', 1)
            ->assertJsonPath('resumo.justificativas_carregadas', true)
            ->json();

        $ranking = $payload['ranking_cumprimento_mes'];
        $this->assertCount(2, $ranking);

        $ana = collect($ranking)->firstWhere('id_person', 100);
        $bruno = collect($ranking)->firstWhere('id_person', 200);
        $this->assertNotNull($ana);
        $this->assertNotNull($bruno);

        $this->assertTrue($ana['completou_mes_todo']);
        $this->assertSame(1, $ana['dias_esperados']);
        $this->assertSame(1, $ana['dias_cumpridos']);
        $this->assertSame(1, $ana['dias_feriado_excluidos']);
        $this->assertSame(1, $ana['dias_ferias_excluidos']);
        $this->assertSame(100.0, (float) $ana['percentual']);

        $this->assertTrue($bruno['completou_mes_todo']);
        $this->assertSame(1, $bruno['dias_esperados']);
        $this->assertSame(1, $bruno['dias_cumpridos']);
        $this->assertSame(1, $bruno['dias_atestado_excluidos']);
        $this->assertSame(100.0, (float) $bruno['percentual']);
    }

    public function test_ranking_orders_by_percentual_when_not_complete(): void
    {
        $company = Company::query()->create(['name' => 'Emp Rank']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->seedMondaySchedule($company);
        // Período com 2 segundas: 06 e 13.
        $this->seedEspelhoDays($company, $admin, 1, 'Ze Cem', ['2026-04-06', '2026-04-13']);
        $this->seedEspelhoDay($company, $admin, 2, 'Maria Meio', '2026-04-06');
        $this->seedEspelhoDay($company, $admin, 3, 'Pedro Um', '2026-04-06');

        $mock = Mockery::mock(RhidComplianceService::class);
        $mock->shouldReceive('listJustificationTypes')->never();
        $mock->shouldReceive('listJustifications')->never();
        $this->app->instance(RhidComplianceService::class, $mock);

        $ranking = $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.monthly-compliance', [
                'ini' => '2026-04-06',
                'fim' => '2026-04-13',
            ]))
            ->assertOk()
            ->assertJsonPath('resumo.justificativas_carregadas', false)
            ->assertJsonPath('resumo.dias_uteis_no_periodo', 2)
            ->json('ranking_cumprimento_mes');

        $this->assertSame(
            [1, 2, 3],
            array_column($ranking, 'id_person'),
            'Ranking inesperado: '.json_encode($ranking, JSON_UNESCAPED_UNICODE),
        );
        $this->assertTrue($ranking[0]['completou_mes_todo']);
        $this->assertSame(100.0, (float) $ranking[0]['percentual']);
        $this->assertSame(50.0, (float) $ranking[1]['percentual']);
        $this->assertSame(50.0, (float) $ranking[2]['percentual']);
    }

    private function seedMondaySchedule(Company $company): void
    {
        $dias = [];
        foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $k) {
            $dias[$k] = [
                'ativo' => $k === 'seg',
                'entrada' => '08:00',
                'saida_almoco' => '12:00',
                'volta_almoco' => '13:00',
                'saida' => '17:00',
                'almoco2_inicio' => null,
                'almoco2_fim' => null,
                'trabalho2_entrada' => null,
                'trabalho2_saida' => null,
            ];
        }

        CompanyRhidScheduleSetting::query()->create([
            'company_id' => $company->id,
            'settings' => [
                'segundo_trabalho' => false,
                'segundo_almoco' => false,
                'tolerancia_minutos' => 0,
                'dias' => $dias,
            ],
        ]);
    }

    private function seedEspelhoDays(Company $company, User $admin, int $idPerson, string $nome, array $refDates): void
    {
        $sorted = $refDates;
        sort($sorted);
        $import = RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'id_person' => $idPerson,
            'period_ini' => $sorted[0],
            'period_fim' => $sorted[array_key_last($sorted)],
            'guid' => 'g-mc-'.$idPerson.'-'.$sorted[0].'-'.count($sorted),
            'storage_path' => 'rhid/'.$idPerson.'-'.$sorted[0].'.pdf',
            'parse_status' => 'ok',
            'parsed_at' => now(),
        ]);

        foreach ($refDates as $refDate) {
            RhidEspelhoDay::query()->create([
                'import_id' => $import->id,
                'ref_date' => $refDate,
                'row_json' => [
                    'colaboradores' => [[
                        'nome' => $nome,
                        'ent_1' => '08:00',
                        'sai_1' => '12:00',
                        'ent_2' => '13:00',
                        'sai_2' => '17:00',
                    ]],
                ],
            ]);
        }
    }

    private function seedEspelhoDay(Company $company, User $admin, int $idPerson, string $nome, string $refDate): void
    {
        $this->seedEspelhoDays($company, $admin, $idPerson, $nome, [$refDate]);
    }
}
