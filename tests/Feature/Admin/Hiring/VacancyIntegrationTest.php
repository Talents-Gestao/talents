<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\HiringProcessStage;
use App\Enums\WorkspaceType;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\TalentCandidate;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Services\Hiring\HiringVacancyService;
use App\Support\Hiring\ActiveHiringProcessCounter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

class VacancyIntegrationTest extends HiringVacancyTestCase
{
    public function test_migration_backfills_vacancy_and_candidate_permissions(): void
    {
        $fromAcompanhamento = $this->talentsWorkspace();
        $fromBanco = $this->talentsWorkspace();
        $fromLegacy = $this->talentsWorkspace();
        $none = $this->talentsWorkspace();

        $now = now();
        DB::table('admin_user_permissions')->insert([
            ['user_workspace_id' => $fromAcompanhamento->id, 'module' => 'solides_acompanhamento', 'action' => 'view', 'created_at' => $now, 'updated_at' => $now],
            ['user_workspace_id' => $fromAcompanhamento->id, 'module' => 'solides_acompanhamento', 'action' => 'edit', 'created_at' => $now, 'updated_at' => $now],
            ['user_workspace_id' => $fromBanco->id, 'module' => 'solides_banco_talentos', 'action' => 'view', 'created_at' => $now, 'updated_at' => $now],
            ['user_workspace_id' => $fromLegacy->id, 'module' => 'solides', 'action' => 'delete', 'created_at' => $now, 'updated_at' => $now],
            ['user_workspace_id' => $none->id, 'module' => 'tarefas', 'action' => 'view', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Só o backfill da migration (down/up inteiro não cabe mais: tabelas das fases seguintes dependem destas).
        $migration = $this->migration();
        $backfill = fn () => (new \ReflectionMethod($migration, 'backfillPermissions'))->invoke($migration);
        $backfill();

        $this->assertSame(['edit', 'view'], $this->actionsFor($fromAcompanhamento, 'contratacao_vagas'));
        $this->assertSame([], $this->actionsFor($fromAcompanhamento, 'contratacao_candidatos'));
        $this->assertSame([], $this->actionsFor($fromBanco, 'contratacao_vagas'));
        $this->assertSame(['view'], $this->actionsFor($fromBanco, 'contratacao_candidatos'));
        $this->assertSame(['delete'], $this->actionsFor($fromLegacy, 'contratacao_vagas'));
        $this->assertSame(['delete'], $this->actionsFor($fromLegacy, 'contratacao_candidatos'));
        $this->assertSame([], $this->actionsFor($none, 'contratacao_vagas'));

        // Idempotente: rodar o backfill de novo não duplica.
        $backfill();
        $this->assertSame(['edit', 'view'], $this->actionsFor($fromAcompanhamento, 'contratacao_vagas'));
    }

    public function test_admin_follow_up_sends_vacancies_only_with_permission(): void
    {
        $process = $this->process(HiringProcessStage::AnaliseCurriculo);
        $vacancy = $this->vacancy($process, 'Vaga no processo');
        app(HiringVacancyService::class)->addCandidate($vacancy, TalentCandidate::query()->create(['name' => 'Lia']), null, null);

        $withVacancies = $this->adminWith([
            'solides_acompanhamento' => ['view'],
            'contratacao_vagas' => ['view'],
        ]);
        $this->actingAs($withVacancies)
            ->get(route('admin.acompanhamento.index', ['stage' => HiringProcessStage::AnaliseCurriculo->value]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vacancies_url', route('admin.contratacao.vagas.index'))
                ->has('processes.0.vacancies', 1)
                ->where('processes.0.vacancies.0.title', 'Vaga no processo')
                ->where('processes.0.vacancies.0.applications_count', 1)
                ->where('processes.0.vacancies.0.url', route('admin.contratacao.vagas.show', $vacancy)));

        $withoutVacancies = $this->adminWith(['solides_acompanhamento' => ['view']]);
        $this->actingAs($withoutVacancies)
            ->get(route('admin.acompanhamento.index', ['stage' => HiringProcessStage::AnaliseCurriculo->value]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vacancies_url', null)
                ->missing('processes.0.vacancies'));
    }

    public function test_portal_payload_never_contains_vacancies(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Portal', 'is_active' => true, 'acompanhamento_access' => true]);
        $this->subscribeCompanyToNr1($company);
        $process = $this->process(HiringProcessStage::EntrevistaGestor, $company);
        $vacancy = $this->vacancy($process, 'Vaga interna secreta');
        app(HiringVacancyService::class)->addCandidate(
            $vacancy,
            TalentCandidate::query()->create(['name' => 'Candidata Oculta', 'email' => 'oculta@example.com']),
            null,
            null,
        );

        $companyAdmin = User::factory()->companyAdmin($company->id)->create();

        $response = $this->actingAs($companyAdmin)
            ->get(route('client.acompanhamento.index', ['stage' => HiringProcessStage::EntrevistaGestor->value]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/HiringFollowUp/Index')
                ->has('processes', 1)
                ->missing('processes.0.vacancies')
                ->missing('vacancies_url'));

        $html = $response->getContent();
        $this->assertStringNotContainsString('Vaga interna secreta', $html);
        $this->assertStringNotContainsString('Candidata Oculta', $html);
        $this->assertStringNotContainsString('oculta@example.com', $html);
        $this->assertStringNotContainsString('contratacao/vagas', $html);

        $this->actingAs($companyAdmin)
            ->get(route('admin.contratacao.vagas.show', $vacancy))
            ->assertForbidden();
    }

    public function test_active_process_counter_works_for_non_owner_with_acompanhamento(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Contador', 'is_active' => true]);
        HiringProcess::query()->create(['company_id' => $company->id, 'title' => 'Ativa', 'current_stage' => HiringProcessStage::AnuncioVagas]);
        HiringProcess::query()->create(['company_id' => $company->id, 'title' => 'Fechada', 'current_stage' => HiringProcessStage::Contratacao]);

        $withGrant = $this->adminWith(['solides_acompanhamento' => ['view']]);
        $this->assertSame(1, app(ActiveHiringProcessCounter::class)->forUser($withGrant));

        $withoutGrant = $this->adminWith(['tarefas' => ['view']]);
        $this->assertSame(0, app(ActiveHiringProcessCounter::class)->forUser($withoutGrant));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_25_100000_create_hiring_vacancies_module.php');
    }

    private function talentsWorkspace(): UserWorkspace
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);

        return UserWorkspace::query()
            ->where('user_id', $user->id)
            ->where('workspace_type', WorkspaceType::Talents)
            ->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function actionsFor(UserWorkspace $workspace, string $module): array
    {
        return DB::table('admin_user_permissions')
            ->where('user_workspace_id', $workspace->id)
            ->where('module', $module)
            ->orderBy('action')
            ->pluck('action')
            ->all();
    }
}
