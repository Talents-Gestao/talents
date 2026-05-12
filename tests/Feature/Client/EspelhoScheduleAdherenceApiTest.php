<?php

namespace Tests\Feature\Client;

use App\Models\Company;
use App\Models\CompanyRhidScheduleSetting;
use App\Models\RhidEspelhoDay;
use App\Models\RhidEspelhoImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspelhoScheduleAdherenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_schedule_adherence(): void
    {
        $this->getJson(route('client.rhid.api.espelhos.schedule-adherence', [
            'ini' => '2026-04-01',
            'fim' => '2026-04-30',
        ]))->assertUnauthorized();
    }

    public function test_guest_cannot_access_schedule_adherence_marks(): void
    {
        $this->getJson(route('client.rhid.api.espelhos.schedule-adherence.marks', [
            'ini' => '2026-04-01',
            'fim' => '2026-04-30',
            'id_person' => 1,
        ]))->assertUnauthorized();
    }

    public function test_rejects_range_over_max_days(): void
    {
        $company = Company::query()->create(['name' => 'E']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.schedule-adherence', [
                'ini' => '2026-01-01',
                'fim' => '2026-06-01',
            ]))
            ->assertStatus(422);
    }

    public function test_aggregate_uses_espelho_and_settings(): void
    {
        $company = Company::query()->create(['name' => 'Emp']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

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

        $import = RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'id_person' => 100,
            'period_ini' => '2026-04-06',
            'period_fim' => '2026-04-12',
            'guid' => 'g-adh-1',
            'storage_path' => 'rhid/x.pdf',
            'parse_status' => 'ok',
            'parsed_at' => now(),
        ]);

        RhidEspelhoDay::query()->create([
            'import_id' => $import->id,
            'ref_date' => '2026-04-06',
            'row_json' => [
                'colaboradores' => [[
                    'nome' => 'Colab Teste',
                    'ent_1' => '09:00',
                    'sai_1' => '12:00',
                    'ent_2' => '13:00',
                    'sai_2' => '17:00',
                ]],
            ],
        ]);

        $full = $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.schedule-adherence', [
                'ini' => '2026-04-01',
                'fim' => '2026-04-30',
            ]))
            ->assertOk()
            ->assertJsonPath('resumo.dias_registro_analisados', 1)
            ->assertJsonPath('resumo.dias_calendario_distintos', 1)
            ->json();

        $rows = $full['ranking_atrasos_entrada'];

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $hit = collect($rows)->firstWhere('id_person', 100);
        $this->assertNotNull($hit);
        $this->assertSame(60, $hit['total_atraso_entrada_minutos']);

        $this->assertArrayHasKey('ranking_pior_aderencia_marcacoes', $full);
        $this->assertArrayHasKey('ranking_melhor_aderencia_marcacoes', $full);
        $this->assertCount(1, $full['ranking_pior_aderencia_marcacoes']);
        $this->assertCount(1, $full['ranking_melhor_aderencia_marcacoes']);
        $this->assertSame(60, $full['ranking_pior_aderencia_marcacoes'][0]['total_minutos_penalidade']);
        $this->assertSame('Colab Teste', $full['ranking_melhor_aderencia_marcacoes'][0]['nome']);
    }

    public function test_diagnostics_reports_pending_imports_and_no_schedule(): void
    {
        $company = Company::query()->create(['name' => 'Emp Diag']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        // Sem configurar horários: deve sinalizar.
        RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'id_person' => 100,
            'period_ini' => '2026-05-01',
            'period_fim' => '2026-05-31',
            'guid' => 'g-pending-1',
            'storage_path' => 'rhid/p.pdf',
            'parse_status' => 'pending',
            'parsed_at' => null,
        ]);

        $payload = $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.schedule-adherence', [
                'ini' => '2026-05-01',
                'fim' => '2026-05-31',
            ]))
            ->assertOk()
            ->assertJsonPath('diagnostics.imports_no_periodo', 1)
            ->assertJsonPath('diagnostics.imports_por_status.pending', 1)
            ->assertJsonPath('diagnostics.imports_por_status.ok', 0)
            ->assertJsonPath('diagnostics.horarios_configurados', false)
            ->json();

        $this->assertNotEmpty($payload['diagnostics']['hint'] ?? null);
        $this->assertSame(0, $payload['diagnostics']['dias_uteis_configurados']);
        $this->assertSame(0, $payload['diagnostics']['dias_uteis_no_periodo']);
    }

    public function test_diagnostics_lists_failed_imports_with_error(): void
    {
        $company = Company::query()->create(['name' => 'Emp Fail']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

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

        RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'id_person' => 200,
            'period_ini' => '2026-05-01',
            'period_fim' => '2026-05-31',
            'guid' => 'g-fail-1',
            'storage_path' => 'rhid/f.pdf',
            'parse_status' => 'failed',
            'parse_error' => 'Parser Python falhou: pymupdf não encontrado',
            'parsed_at' => now(),
        ]);

        $payload = $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.schedule-adherence', [
                'ini' => '2026-05-01',
                'fim' => '2026-05-31',
            ]))
            ->assertOk()
            ->assertJsonPath('diagnostics.imports_por_status.failed', 1)
            ->assertJsonPath('diagnostics.horarios_configurados', true)
            ->json();

        $problemas = $payload['diagnostics']['ultimos_problemas'];
        $this->assertNotEmpty($problemas);
        $this->assertSame('failed', $problemas[0]['parse_status']);
        $this->assertStringContainsString('pymupdf', (string) $problemas[0]['parse_error_short']);
    }

    public function test_schedule_adherence_marks_returns_days_for_person(): void
    {
        $company = Company::query()->create(['name' => 'Emp']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

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

        $import = RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'id_person' => 100,
            'period_ini' => '2026-04-06',
            'period_fim' => '2026-04-12',
            'guid' => 'g-adh-marks',
            'storage_path' => 'rhid/y.pdf',
            'parse_status' => 'ok',
            'parsed_at' => now(),
        ]);

        RhidEspelhoDay::query()->create([
            'import_id' => $import->id,
            'ref_date' => '2026-04-06',
            'row_json' => [
                'colaboradores' => [[
                    'nome' => 'Colab Teste',
                    'ent_1' => '09:00',
                    'sai_1' => '12:00',
                    'ent_2' => '13:00',
                    'sai_2' => '17:00',
                ]],
            ],
        ]);

        $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.schedule-adherence.marks', [
                'ini' => '2026-04-01',
                'fim' => '2026-04-30',
                'id_person' => 100,
            ]))
            ->assertOk()
            ->assertJsonPath('id_person', 100)
            ->assertJsonPath('tolerancia_minutos', 0)
            ->assertJsonPath('preferencia_almoco', 'auto')
            ->assertJsonPath('dias.0.situacao', 'analisavel')
            ->assertJsonPath('dias.0.ent_1', '09:00')
            ->assertJsonPath('dias.0.lunch_interval_used', 1)
            ->assertJsonPath('dias.0.atraso_entrada_minutos', 60);
    }

    /**
     * Reproduz o caso reportado em campo: empresa com 2 intervalos de almoco (11:30-12:30 e 12:30-13:30),
     * colaborador que almoca no 2º intervalo (sai_1=13:05, ent_2=13:56). Sem auto-detect daria ~150min de
     * falso atraso/dia; com auto-detect o intervalo correto é aplicado.
     */
    public function test_auto_detect_second_lunch_avoids_false_positives_in_aggregate(): void
    {
        $company = Company::query()->create(['name' => 'Emp Auto']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $dias = [];
        foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $k) {
            $dias[$k] = [
                'ativo' => $k === 'seg',
                'entrada' => '07:30',
                'saida_almoco' => '11:30',
                'volta_almoco' => '12:30',
                'saida' => '17:30',
                'almoco2_inicio' => '12:30',
                'almoco2_fim' => '13:30',
                'trabalho2_entrada' => null,
                'trabalho2_saida' => null,
            ];
        }
        CompanyRhidScheduleSetting::query()->create([
            'company_id' => $company->id,
            'settings' => [
                'segundo_trabalho' => false,
                'segundo_almoco' => true,
                'tolerancia_minutos' => 15,
                'dias' => $dias,
            ],
        ]);

        $import = RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'id_person' => 300,
            'period_ini' => '2026-05-01',
            'period_fim' => '2026-05-31',
            'guid' => 'g-auto-1',
            'storage_path' => 'rhid/auto.pdf',
            'parse_status' => 'ok',
            'parsed_at' => now(),
        ]);

        // Segunda-feira (2026-05-04) — claramente usa o 2º intervalo
        RhidEspelhoDay::query()->create([
            'import_id' => $import->id,
            'ref_date' => '2026-05-04',
            'row_json' => [
                'colaboradores' => [[
                    'nome' => 'Colab Almoço 2',
                    'ent_1' => '07:31',
                    'sai_1' => '13:05',
                    'ent_2' => '13:56',
                    'sai_2' => '17:39',
                ]],
            ],
        ]);

        $payload = $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.schedule-adherence', [
                'ini' => '2026-05-01',
                'fim' => '2026-05-31',
            ]))
            ->assertOk()
            ->assertJsonPath('resumo.dias_calendario_distintos', 1)
            ->json();

        $row = collect($payload['ranking_atrasos_entrada'])->firstWhere('id_person', 300);
        $this->assertNotNull($row);
        $this->assertLessThanOrEqual(5, $row['total_atraso_entrada_minutos']);

        // Soma de saida + volta almoco (com tolerância 15min) deve ficar pequena, não ~150
        $this->assertLessThanOrEqual(70, $row['total_minutos_atraso_saida_almoco'] + $row['total_minutos_atraso_volta_almoco']);

        // Verifica marks: lunch_interval_used = 2 no dia 04/05
        $marks = $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.schedule-adherence.marks', [
                'ini' => '2026-05-01',
                'fim' => '2026-05-31',
                'id_person' => 300,
            ]))
            ->assertOk()
            ->assertJsonPath('preferencia_almoco', 'auto')
            ->json();

        $dia = collect($marks['dias'])->firstWhere('ref_date', '2026-05-04');
        $this->assertNotNull($dia);
        $this->assertSame('analisavel', $dia['situacao']);
        $this->assertSame(2, $dia['lunch_interval_used']);
    }
}
