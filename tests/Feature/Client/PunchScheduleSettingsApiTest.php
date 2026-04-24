<?php

namespace Tests\Feature\Client;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PunchScheduleSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_get_punch_schedule_settings(): void
    {
        $this->getJson(route('client.rhid.api.punch-schedule-settings.show'))
            ->assertUnauthorized();
    }

    public function test_guest_cannot_put_punch_schedule_settings(): void
    {
        $this->putJson(route('client.rhid.api.punch-schedule-settings.update'), [])
            ->assertUnauthorized();
    }

    public function test_company_admin_gets_default_settings_when_none_saved(): void
    {
        $company = Company::query()->create(['name' => 'Empresa A']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->getJson(route('client.rhid.api.punch-schedule-settings.show'))
            ->assertOk()
            ->assertJsonPath('settings.segundo_trabalho', false)
            ->assertJsonPath('settings.segundo_almoco', false)
            ->assertJsonPath('settings.dias.seg.ativo', false);
    }

    public function test_company_admin_can_persist_punch_schedule_settings(): void
    {
        $company = Company::query()->create(['name' => 'Empresa B']);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $body = [
            'segundo_trabalho' => true,
            'segundo_almoco' => false,
            'dias' => [
                'seg' => [
                    'ativo' => true,
                    'entrada' => '08:00',
                    'saida_almoco' => '12:00',
                    'volta_almoco' => '13:00',
                    'saida' => '17:00',
                    'almoco2_inicio' => null,
                    'almoco2_fim' => null,
                    'trabalho2_entrada' => '18:00',
                    'trabalho2_saida' => '22:00',
                ],
                'ter' => [
                    'ativo' => false,
                    'entrada' => null,
                    'saida_almoco' => null,
                    'volta_almoco' => null,
                    'saida' => null,
                    'almoco2_inicio' => null,
                    'almoco2_fim' => null,
                    'trabalho2_entrada' => null,
                    'trabalho2_saida' => null,
                ],
                'qua' => [
                    'ativo' => false,
                    'entrada' => null,
                    'saida_almoco' => null,
                    'volta_almoco' => null,
                    'saida' => null,
                    'almoco2_inicio' => null,
                    'almoco2_fim' => null,
                    'trabalho2_entrada' => null,
                    'trabalho2_saida' => null,
                ],
                'qui' => [
                    'ativo' => false,
                    'entrada' => null,
                    'saida_almoco' => null,
                    'volta_almoco' => null,
                    'saida' => null,
                    'almoco2_inicio' => null,
                    'almoco2_fim' => null,
                    'trabalho2_entrada' => null,
                    'trabalho2_saida' => null,
                ],
                'sex' => [
                    'ativo' => false,
                    'entrada' => null,
                    'saida_almoco' => null,
                    'volta_almoco' => null,
                    'saida' => null,
                    'almoco2_inicio' => null,
                    'almoco2_fim' => null,
                    'trabalho2_entrada' => null,
                    'trabalho2_saida' => null,
                ],
                'sab' => [
                    'ativo' => false,
                    'entrada' => null,
                    'saida_almoco' => null,
                    'volta_almoco' => null,
                    'saida' => null,
                    'almoco2_inicio' => null,
                    'almoco2_fim' => null,
                    'trabalho2_entrada' => null,
                    'trabalho2_saida' => null,
                ],
                'dom' => [
                    'ativo' => false,
                    'entrada' => null,
                    'saida_almoco' => null,
                    'volta_almoco' => null,
                    'saida' => null,
                    'almoco2_inicio' => null,
                    'almoco2_fim' => null,
                    'trabalho2_entrada' => null,
                    'trabalho2_saida' => null,
                ],
            ],
        ];

        $this->actingAs($admin)
            ->putJson(route('client.rhid.api.punch-schedule-settings.update'), $body)
            ->assertOk()
            ->assertJsonPath('settings.segundo_trabalho', true)
            ->assertJsonPath('settings.dias.seg.entrada', '08:00')
            ->assertJsonPath('settings.dias.seg.trabalho2_saida', '22:00');

        $this->actingAs($admin)
            ->getJson(route('client.rhid.api.punch-schedule-settings.show'))
            ->assertOk()
            ->assertJsonPath('settings.dias.seg.entrada', '08:00');
    }

    public function test_other_company_does_not_see_foreign_settings(): void
    {
        $companyA = Company::query()->create(['name' => 'A']);
        $companyB = Company::query()->create(['name' => 'B']);
        $this->subscribeCompanyToNr1($companyA);
        $this->subscribeCompanyToNr1($companyB);
        $adminA = User::factory()->companyAdmin($companyA->id)->create();
        $adminB = User::factory()->companyAdmin($companyB->id)->create();

        $minimalDay = static fn (): array => [
            'ativo' => false,
            'entrada' => null,
            'saida_almoco' => null,
            'volta_almoco' => null,
            'saida' => null,
            'almoco2_inicio' => null,
            'almoco2_fim' => null,
            'trabalho2_entrada' => null,
            'trabalho2_saida' => null,
        ];

        $fullDias = [];
        foreach (['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'] as $k) {
            $fullDias[$k] = $minimalDay();
        }
        $fullDias['seg']['ativo'] = true;
        $fullDias['seg']['entrada'] = '07:30';

        $this->actingAs($adminA)
            ->putJson(route('client.rhid.api.punch-schedule-settings.update'), [
                'segundo_trabalho' => false,
                'segundo_almoco' => false,
                'dias' => $fullDias,
            ])
            ->assertOk();

        $this->actingAs($adminB)
            ->getJson(route('client.rhid.api.punch-schedule-settings.show'))
            ->assertOk()
            ->assertJsonPath('settings.dias.seg.entrada', null);
    }
}
