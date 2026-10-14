<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\RhidEspelhoDay;
use App\Models\RhidEspelhoImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PontoPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_super_admin_can_open_ponto_hub(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        Company::query()->create([
            'name' => 'Empresa RHID',
            'is_active' => true,
            'rhid_email' => 'ponto@example.com',
            'rhid_password' => 'secret',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ponto.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/TimeClock/Index')
                ->has('companies', 1)
                ->where('companies.0.name', 'Empresa RHID'));
    }

    public function test_legacy_rhid_portfolio_redirects_to_ponto_hub(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->get('/admin/rhid')
            ->assertRedirect(route('admin.ponto.index'));
    }

    public function test_coming_soon_ponto_redirects_to_hub(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->get(route('admin.coming-soon.show', 'ponto'))
            ->assertRedirect(route('admin.ponto.index'));
    }

    public function test_last_punches_requires_rhid_credentials(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Sem RHID',
            'is_active' => true,
            'rhid_email' => null,
            'rhid_password' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ponto.companies.last-punches', $company))
            ->assertStatus(422);
    }

    public function test_store_justification_validates_payload(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Empresa Justificativas',
            'is_active' => true,
            'rhid_email' => 'ponto@example.com',
            'rhid_password' => 'secret',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ponto.companies.justifications.store', $company), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'idPerson',
                'idJustificationType',
                'justificativa',
                'inicio',
                'fim',
            ]);
    }

    public function test_list_justifications_requires_period(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Empresa Lista Just',
            'is_active' => true,
            'rhid_email' => 'ponto@example.com',
            'rhid_password' => 'secret',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ponto.companies.justifications.list', $company), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ini', 'fim']);
    }

    public function test_admin_can_save_rhid_credentials_from_company_ponto_tab(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Empresa Credenciais',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.ponto.companies.settings.update', $company), [
                'rhid_email' => 'rhid@example.com',
                'rhid_password' => 'segredo-api',
                'rhid_domain' => 'acme',
            ])
            ->assertRedirect(route('admin.companies.show', ['company' => $company, 'tab' => 'ponto']));

        $company->refresh();
        $this->assertTrue($company->rhidConfigured());
        $this->assertSame('rhid@example.com', $company->rhid_email);
        $this->assertSame('acme', $company->rhid_domain);
        $this->assertSame('segredo-api', $company->rhid_password);
    }

    public function test_test_connection_requires_credentials(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Empresa Sem Credencial',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ponto.companies.settings.test', $company))
            ->assertStatus(422)
            ->assertJsonFragment(['ok' => false]);
    }

    public function test_local_fixture_fills_punches_people_and_justifications(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->seedLocalPontoFixtureCompany();

        $this->actingAs($admin)
            ->getJson(route('admin.ponto.companies.last-punches', $company))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ana Pontual']);

        $this->actingAs($admin)
            ->getJson(route('admin.ponto.companies.people', $company))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ana Pontual'])
            ->assertJsonFragment(['name' => 'Carla Atestado'])
            ->assertJsonFragment(['departmentName' => 'Operações']);

        $this->actingAs($admin)
            ->getJson(route('admin.ponto.companies.justification-types', $company))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Atestado médico']);

        $this->actingAs($admin)
            ->postJson(route('admin.ponto.companies.justifications.list', $company), [
                'ini' => now()->startOfMonth()->format('Ymd'),
                'fim' => now()->endOfMonth()->format('Ymd'),
            ])
            ->assertOk()
            ->assertJsonFragment(['personName' => 'Carla Atestado']);

        $metrics = $this->actingAs($admin)
            ->getJson(route('admin.ponto.companies.metrics', $company))
            ->assertOk()
            ->json();

        $this->assertSame('ok', $metrics['status'] ?? null);
        $this->assertNotEmpty($metrics['bank']['rows'] ?? []);
        $this->assertSame('Bruno Faltas', $metrics['bank']['rows'][0]['name'] ?? null);
        $this->assertSame(-480, $metrics['bank']['rows'][0]['minutes'] ?? null);
    }

    private function seedLocalPontoFixtureCompany(): Company
    {
        $company = Company::query()->create([
            'name' => 'QA Ponto Visual',
            'is_active' => true,
            'rhid_email' => (string) config('rhid.visual_qa_email'),
            'rhid_password' => 'secret-qa',
        ]);

        $today = now()->toDateString();
        $this->seedFixturePerson($company, 910001, 'Ana Pontual', $today, false);
        $this->seedFixturePerson($company, 910002, 'Bruno Faltas', $today, false);
        $this->seedFixturePerson($company, 910003, 'Carla Atestado', $today, true);

        return $company;
    }

    private function seedFixturePerson(Company $company, int $idPerson, string $nome, string $refDate, bool $atestado): void
    {
        $import = RhidEspelhoImport::query()->create([
            'company_id' => $company->id,
            'id_person' => $idPerson,
            'period_ini' => $refDate,
            'period_fim' => $refDate,
            'guid' => 'qa-test-'.$idPerson,
            'storage_path' => 'rhid/qa-test-'.$idPerson.'.pdf',
            'source' => 'test',
            'parse_status' => 'ok',
            'parsed_at' => now(),
        ]);

        $colab = [
            'nome' => $nome,
            'id_person' => $idPerson,
        ];
        if ($atestado) {
            $colab['atestado'] = true;
        } else {
            $colab['ent_1'] = '08:00';
            $colab['sai_1'] = '12:00';
            $colab['ent_2'] = '13:00';
            $colab['sai_2'] = '17:00';
        }

        RhidEspelhoDay::query()->create([
            'import_id' => $import->id,
            'ref_date' => $refDate,
            'row_json' => [
                'atestado' => $atestado,
                'colaboradores' => [$colab],
            ],
        ]);
    }
}
