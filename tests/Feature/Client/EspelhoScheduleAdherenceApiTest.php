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

    public function test_rejects_range_over_max_days(): void
    {
        $company = Company::query()->create(['name' => 'E']);
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

        $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.schedule-adherence', [
                'ini' => '2026-04-01',
                'fim' => '2026-04-30',
            ]))
            ->assertOk()
            ->assertJsonPath('resumo.dias_registro_analisados', 1);

        $rows = $this->actingAs($admin)
            ->getJson(route('client.rhid.api.espelhos.schedule-adherence', [
                'ini' => '2026-04-01',
                'fim' => '2026-04-30',
            ]))
            ->json('ranking_atrasos_entrada');

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $hit = collect($rows)->firstWhere('id_person', 100);
        $this->assertNotNull($hit);
        $this->assertSame(60, $hit['total_atraso_entrada_minutos']);
    }
}
