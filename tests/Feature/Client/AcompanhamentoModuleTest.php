<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\HiringProcessStage;
use App\Models\Company;
use App\Models\HiringProcess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AcompanhamentoModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_view_acompanhamento_when_enabled(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Acompanhamento',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Coordenador Comercial',
            'current_stage' => HiringProcessStage::EntrevistaGestor,
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.acompanhamento.index', [
                'stage' => HiringProcessStage::EntrevistaGestor->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/HiringFollowUp/Index')
                ->where('active_stage', HiringProcessStage::EntrevistaGestor->value)
                ->where('can_create', true)
                ->where('can_manage', true)
                ->where('can_delete', true)
                ->has('processes', 1)
                ->where('processes.0.title', 'Coordenador Comercial'));
    }

    public function test_company_admin_can_create_process(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Cria',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->post(route('client.acompanhamento.store'), [
                'title' => 'Analista de RH',
                'current_stage' => HiringProcessStage::AnaliseCurriculo->value,
            ])
            ->assertRedirect(route('client.acompanhamento.index', [
                'stage' => HiringProcessStage::AnaliseCurriculo->value,
            ]));

        $this->assertDatabaseHas('hiring_processes', [
            'company_id' => $company->id,
            'title' => 'Analista de RH',
            'current_stage' => HiringProcessStage::AnaliseCurriculo->value,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_candidates_and_comments_persist_and_update_across_stages_for_client(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Campos',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->post(route('client.acompanhamento.store'), [
                'title' => 'Vaga Cliente',
                'current_stage' => HiringProcessStage::AnaliseCurriculo->value,
                'notes' => 'Comentário cliente',
                'candidates_count' => 3,
            ])
            ->assertRedirect();

        $process = HiringProcess::query()->where('title', 'Vaga Cliente')->firstOrFail();
        $this->assertSame(3, $process->candidates_count);
        $this->assertNotNull($process->candidates_count_at);
        $this->assertNotNull($process->notes_at);

        $this->actingAs($admin)
            ->post(route('client.acompanhamento.advance', $process))
            ->assertRedirect();

        $process->refresh();
        $this->assertSame(3, $process->candidates_count);
        $this->assertSame('Comentário cliente', $process->notes);

        $this->travel(1)->seconds();

        $this->actingAs($admin)
            ->patch(route('client.acompanhamento.update', $process), [
                'candidates_count' => 7,
                'notes' => 'Comentário após avanço',
            ])
            ->assertRedirect();

        $process->refresh();
        $this->assertSame(7, $process->candidates_count);
        $this->assertSame('Comentário após avanço', $process->notes);
        $this->assertNotNull($process->candidates_count_at);
        $this->assertNotNull($process->notes_at);
    }

    public function test_company_user_cannot_create_process_but_can_manage(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa User',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $user = User::factory()->companyUser($company->id)->create();
        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $this->withoutVite();

        $this->actingAs($user)
            ->get(route('client.acompanhamento.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can_create', false)
                ->where('can_manage', true)
                ->where('can_delete', true));

        $this->actingAs($user)
            ->post(route('client.acompanhamento.store'), [
                'title' => 'Não deveria',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('client.acompanhamento.advance', $process))
            ->assertRedirect(route('client.acompanhamento.index', [
                'stage' => HiringProcessStage::AnaliseComportamental->value,
            ]));

        $this->assertSame(HiringProcessStage::AnaliseComportamental, $process->fresh()->current_stage);
        $this->assertDatabaseMissing('hiring_processes', ['title' => 'Não deveria']);
    }

    public function test_company_admin_forbidden_when_module_disabled(): void
    {
        $company = Company::query()->create([
            'name' => 'Sem Acompanhamento',
            'acompanhamento_access' => false,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get(route('client.acompanhamento.index'))
            ->assertForbidden();
    }

    public function test_client_only_sees_own_company_processes(): void
    {
        $company = Company::query()->create([
            'name' => 'Minha Empresa',
            'acompanhamento_access' => true,
        ]);
        $other = Company::query()->create([
            'name' => 'Outra Empresa',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);

        HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga própria',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);
        HiringProcess::query()->create([
            'company_id' => $other->id,
            'title' => 'Vaga alheia',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $admin = User::factory()->companyAdmin($company->id)->create();
        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.acompanhamento.index', [
                'stage' => HiringProcessStage::AnaliseCurriculo->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('processes', 1)
                ->where('processes.0.title', 'Vaga própria')
                ->where('active_stage', HiringProcessStage::AnaliseCurriculo->value));
    }

    public function test_company_user_can_view_acompanhamento_when_module_enabled(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Perm',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $user = User::factory()->companyUser($company->id)->create();

        $this->withoutVite();

        $this->actingAs($user)
            ->get(route('client.acompanhamento.index'))
            ->assertOk();
    }

    public function test_company_user_and_admin_can_add_observations(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Obs',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $clientUser = User::factory()->companyUser($company->id)->create();
        $clientAdmin = User::factory()->companyAdmin($company->id)->create();
        $talentsAdmin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga com observações',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $this->actingAs($clientUser)
            ->from(route('client.acompanhamento.index'))
            ->post(route('client.acompanhamento.comments.store', $process), [
                'body' => 'Observação do colaborador',
            ])
            ->assertRedirect();

        $this->actingAs($clientAdmin)
            ->from(route('client.acompanhamento.index'))
            ->post(route('client.acompanhamento.comments.store', $process), [
                'body' => 'Observação do admin da empresa',
            ])
            ->assertRedirect();

        $this->actingAs($talentsAdmin)
            ->from(route('admin.acompanhamento.index'))
            ->post(route('admin.acompanhamento.comments.store', $process), [
                'body' => 'Observação da Talents',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('hiring_process_comments', 3);
        $this->assertDatabaseHas('hiring_process_comments', [
            'hiring_process_id' => $process->id,
            'user_id' => $clientUser->id,
            'body' => 'Observação do colaborador',
        ]);
        $this->assertDatabaseHas('hiring_process_comments', [
            'hiring_process_id' => $process->id,
            'user_id' => $talentsAdmin->id,
            'body' => 'Observação da Talents',
        ]);
    }

    public function test_client_cannot_comment_on_other_company_process(): void
    {
        $company = Company::query()->create([
            'name' => 'Minha',
            'acompanhamento_access' => true,
        ]);
        $other = Company::query()->create([
            'name' => 'Outra',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();
        $process = HiringProcess::query()->create([
            'company_id' => $other->id,
            'title' => 'Alheia',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $this->actingAs($admin)
            ->post(route('client.acompanhamento.comments.store', $process), [
                'body' => 'Não deveria',
            ])
            ->assertForbidden();
    }

    public function test_client_cannot_mutate_via_admin_routes(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Client',
            'acompanhamento_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();
        $process = HiringProcess::query()->create([
            'company_id' => $company->id,
            'title' => 'Vaga',
            'current_stage' => HiringProcessStage::AnaliseCurriculo,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.acompanhamento.advance', $process))
            ->assertForbidden();
    }
}
