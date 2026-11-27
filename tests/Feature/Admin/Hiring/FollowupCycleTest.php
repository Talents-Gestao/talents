<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\EmploymentCycleStatus;
use App\Enums\FollowupStatus;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\EmploymentCycle;
use App\Models\EmploymentCycleEvent;
use App\Models\HiringFollowupInvitation;
use App\Models\User;
use App\Models\UserWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

class FollowupCycleTest extends FollowupTestCase
{
    public function test_register_admission_dedupes_employee_by_email_and_links_candidate(): void
    {
        $owner = $this->owner();
        [$application, $vacancy] = $this->hiredApplication();
        $manager = $this->manager($vacancy->company_id);
        // Já existe na empresa um colaborador com o mesmo e-mail (outra caixa) e nome diferente.
        $existing = CompanyEmployee::query()->create(['company_id' => $vacancy->company_id, 'name' => 'Ana S.', 'email' => 'ANA.SOUZA@example.com', 'is_active' => true]);
        CompanyEmployee::query()->create(['company_id' => $vacancy->company_id, 'name' => 'Ana Souza', 'email' => 'outra.ana@example.com', 'is_active' => true]);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.admission.store', [$vacancy, $application]), [
                'role_title' => 'Analista de RH',
                'manager_contact_id' => $manager->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $cycle = EmploymentCycle::query()->firstOrFail();
        $this->assertSame($existing->id, $cycle->company_employee_id);
        $this->assertSame(EmploymentCycleStatus::PendingAdmission, $cycle->status);
        $this->assertSame(0, $cycle->milestones()->count());
        $this->assertSame($application->talent_candidate_id, $existing->fresh()->talent_candidate_id);

        // Uma segunda admissão da mesma candidatura é recusada.
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.admission.store', [$vacancy, $application]), ['role_title' => 'X'])
            ->assertSessionHasErrors('admission');
    }

    public function test_admission_requires_hired_stage_and_manager_with_followup_topic(): void
    {
        $owner = $this->owner();
        [, $application, $vacancy] = $this->candidateWithApplication();
        $vagasOnly = $this->contact($vacancy->company_id, ['topics' => ['vagas']]);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.admission.store', [$vacancy, $application]), [])
            ->assertSessionHasErrors('admission');

        [$hired, $hiredVacancy] = $this->hiredApplication('Bruno Lima', $vacancy);
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.admission.store', [$hiredVacancy, $hired]), ['manager_contact_id' => $vagasOnly->id])
            ->assertSessionHasErrors('manager_contact_id');
        $this->assertSame(0, EmploymentCycle::query()->count());
    }

    public function test_old_admission_creates_overdue_milestones_without_sending(): void
    {
        Mail::fake();
        Queue::fake();
        $owner = $this->owner();
        $admission = now('America/Sao_Paulo')->subDays(100)->toDateString();
        $cycle = $this->activeCycle($admission);

        $this->assertSame(EmploymentCycleStatus::Active, $cycle->status);
        $milestones = $cycle->milestones()->get();
        $this->assertSame(['d15', 'd30', 'd60', 'd90'], $milestones->map(fn ($m) => $m->moment->value)->all());
        $this->assertSame(now('America/Sao_Paulo')->subDays(85)->toDateString(), $milestones[0]->due_on->toDateString());
        foreach ($milestones as $m) {
            $this->assertSame(FollowupStatus::Overdue, $m->status($cycle->status));
        }

        Mail::assertNothingSent();
        Queue::assertNothingPushed();
        $this->assertSame(0, HiringFollowupInvitation::query()->count());

        $this->actingAs($owner)
            ->get(route('admin.contratacao.pos.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('cycles.data', 1)
                ->where('cycles.data.0.status_label', 'Vencido · revisar')
                ->where('cycles.data.0.role_title', 'Analista de RH')
                ->where('cycles.data.0.manager_name', 'Marcos Gestor'));
    }

    public function test_pending_admission_is_confirmed_with_date(): void
    {
        $owner = $this->owner();
        [$application, $vacancy] = $this->hiredApplication();
        $this->actingAs($owner)->post(route('admin.contratacao.vagas.applications.admission.store', [$vacancy, $application]), []);
        $cycle = EmploymentCycle::query()->firstOrFail();

        $this->actingAs($owner)->post(route('admin.contratacao.pos.confirm-admission', $cycle), ['admission_date' => '31/02/2026'])
            ->assertSessionHasErrors('admission_date');
        $this->actingAs($owner)->post(route('admin.contratacao.pos.confirm-admission', $cycle), ['admission_date' => now()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame(EmploymentCycleStatus::Active, $cycle->fresh()->status);
        $this->assertNotNull($cycle->fresh()->active_confirmed_at);
        $this->assertSame(4, $cycle->milestones()->count());
    }

    public function test_readmission_requires_date_and_blocks_duplicate_open_cycle(): void
    {
        $owner = $this->owner();
        $cycle = $this->activeCycle(now()->subDays(200)->toDateString());

        $this->actingAs($owner)->post(route('admin.contratacao.pos.readmit', $cycle), ['admission_date' => now()->toDateString()])
            ->assertSessionHasErrors('cycle');

        $this->actingAs($owner)->post(route('admin.contratacao.pos.terminate', $cycle), ['terminated_on' => now()->subDays(30)->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame(EmploymentCycleStatus::Terminated, $cycle->fresh()->status);

        $this->actingAs($owner)->post(route('admin.contratacao.pos.readmit', $cycle), [])->assertSessionHasErrors('admission_date');
        $this->actingAs($owner)->post(route('admin.contratacao.pos.readmit', $cycle), ['admission_date' => now()->subDays(60)->toDateString()])
            ->assertSessionHasErrors('admission_date');

        $this->actingAs($owner)->post(route('admin.contratacao.pos.readmit', $cycle), ['admission_date' => now()->toDateString()])
            ->assertSessionHasNoErrors();

        $cycles = EmploymentCycle::query()->where('company_employee_id', $cycle->company_employee_id)->orderBy('id')->get();
        $this->assertCount(2, $cycles);
        $this->assertSame(EmploymentCycleStatus::Terminated, $cycles[0]->status);
        $this->assertSame(EmploymentCycleStatus::Active, $cycles[1]->status);
        $this->assertSame(4, $cycles[0]->milestones()->count());

        // Readmitir de novo o ciclo antigo: já existe ciclo aberto.
        $this->actingAs($owner)->post(route('admin.contratacao.pos.readmit', $cycles[0]), ['admission_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('admission');
        $this->assertSame(2, EmploymentCycle::query()->count());
    }

    public function test_pause_resume_terminate_and_permissions(): void
    {
        $cycle = $this->activeCycle();
        $editor = $this->adminWith(['contratacao_pos' => ['view', 'edit']]);
        $viewer = $this->adminWith(['contratacao_pos' => ['view']]);

        $this->actingAs($viewer)->get(route('admin.contratacao.pos.show', $cycle))->assertOk();
        $this->actingAs($viewer)->post(route('admin.contratacao.pos.pause', $cycle), ['reason' => 'Férias'])->assertForbidden();

        $this->actingAs($editor)->post(route('admin.contratacao.pos.pause', $cycle), ['reason' => 'Licença'])->assertSessionHasNoErrors();
        $milestone = $cycle->milestones()->first();
        $this->assertSame(FollowupStatus::Paused, $milestone->status($cycle->fresh()->status));
        $this->actingAs($editor)->post(route('admin.contratacao.pos.resume', $cycle))->assertSessionHasNoErrors();

        // Desligar e readmitir exigem delete.
        $this->actingAs($editor)->post(route('admin.contratacao.pos.terminate', $cycle), [])->assertForbidden();
        $this->assertSame(EmploymentCycleStatus::Active, $cycle->fresh()->status);

        $this->actingAs($editor)->post(route('admin.contratacao.pos.notes.store', $cycle), ['note' => 'Conversei com o gestor.'])->assertSessionHasNoErrors();
        $note = EmploymentCycleEvent::query()->where('type', 'note')->firstOrFail();
        $this->expectException(\LogicException::class);
        $note->delete();
    }

    public function test_backfill_grants_followup_from_vacancies(): void
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        $workspace = UserWorkspace::query()->where('user_id', $user->id)->firstOrFail();
        DB::table('admin_user_permissions')->insert([
            ['user_workspace_id' => $workspace->id, 'module' => 'contratacao_vagas', 'action' => 'view', 'created_at' => now(), 'updated_at' => now()],
            ['user_workspace_id' => $workspace->id, 'module' => 'contratacao_vagas', 'action' => 'edit', 'created_at' => now(), 'updated_at' => now()],
            ['user_workspace_id' => $workspace->id, 'module' => 'contratacao_vagas', 'action' => 'validate', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration = require database_path('migrations/2026_11_29_100000_create_hiring_followup_module.php');
        $backfill = new \ReflectionMethod($migration, 'backfillPermissions');
        $backfill->invoke($migration);
        $backfill->invoke($migration);

        $actions = DB::table('admin_user_permissions')->where('user_workspace_id', $workspace->id)->where('module', 'contratacao_pos')->orderBy('action')->pluck('action')->all();
        $this->assertSame(['edit', 'view'], $actions);

        // O grant legado «solides» também cobre o módulo novo.
        $this->assertContains(
            AdminPermissionModule::Solides,
            AdminPermissionModule::ContratacaoPos->legacyParents(),
        );
        $this->actingAs($this->adminWith(['tarefas' => ['view']]))->get(route('admin.contratacao.pos.index'))->assertForbidden();
    }

    public function test_idor_between_companies(): void
    {
        $owner = $this->owner();
        $cycleA = $this->activeCycle();
        $cycleB = $this->activeCycle(null, [], null);
        $milestoneB = $cycleB->milestones()->first();
        $otherCompany = Company::query()->create(['name' => 'Concorrente', 'is_active' => true]);
        $foreignManager = $this->manager($otherCompany->id, ['email' => 'fora@concorrente.test']);
        $foreignEmployee = CompanyEmployee::query()->create(['company_id' => $otherCompany->id, 'name' => 'Fora', 'email' => 'fora@x.test', 'is_active' => true]);

        $this->actingAs($owner)->postJson(route('admin.contratacao.pos.milestones.send', [$cycleA, $milestoneB]), ['target' => 'collaborator', 'channel' => 'copy'])
            ->assertNotFound();
        $this->actingAs($owner)->post(route('admin.contratacao.pos.milestones.skip', [$cycleA, $milestoneB]), ['skip_reason' => 'x'])->assertNotFound();
        $this->assertNull($milestoneB->fresh()->skipped_at);

        $this->actingAs($owner)->patch(route('admin.contratacao.pos.update', $cycleA), ['manager_contact_id' => $foreignManager->id])
            ->assertSessionHasErrors('manager_contact_id');

        [$application, $vacancy] = $this->hiredApplication('Caio Reis');
        $this->actingAs($owner)->post(route('admin.contratacao.vagas.applications.admission.store', [$vacancy, $application]), ['company_employee_id' => $foreignEmployee->id])
            ->assertSessionHasErrors('company_employee_id');

        // Candidatura de outra vaga dá 404 na admissão.
        $this->actingAs($owner)->post(route('admin.contratacao.vagas.applications.admission.store', [$this->vacancy(null, 'Outra'), $application]), [])
            ->assertNotFound();
    }
}
