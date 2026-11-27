<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\HiringProcessStage;
use App\Models\HiringApplication;
use App\Models\HiringVacancy;
use App\Models\TalentCandidate;
use App\Services\Hiring\HiringVacancyService;
use Inertia\Testing\AssertableInertia;

class VacancyAccessTest extends HiringVacancyTestCase
{
    public function test_admin_without_permission_gets_403(): void
    {
        $admin = $this->adminWith(['solides_acompanhamento' => ['view']]);
        $vacancy = $this->vacancy();

        $this->actingAs($admin)->get(route('admin.contratacao.vagas.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.contratacao.vagas.show', $vacancy))->assertForbidden();
        $this->actingAs($admin)
            ->getJson(route('admin.contratacao.vagas.candidates.available', $vacancy))
            ->assertForbidden();
    }

    public function test_admin_with_view_permission_sees_list_and_board(): void
    {
        $admin = $this->adminWith(['contratacao_vagas' => ['view']]);
        $vacancy = $this->vacancy();

        $this->actingAs($admin)
            ->get(route('admin.contratacao.vagas.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Hiring/Vacancies/Index')
                ->has('vacancies.data', 1)
                ->where('can.create', false));

        $this->actingAs($admin)
            ->get(route('admin.contratacao.vagas.show', $vacancy))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Hiring/Vacancies/Show')
                ->has('stages', 4)
                ->where('can.edit', false));
    }

    public function test_each_route_requires_its_action(): void
    {
        $admin = $this->adminWith(['contratacao_vagas' => ['view']]);
        $process = $this->process();
        $vacancy = $this->vacancy($process);
        $stage = $vacancy->stages()->first();

        $this->actingAs($admin)
            ->post(route('admin.contratacao.vagas.store'), ['hiring_process_id' => $process->id, 'title' => 'Nova'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->patch(route('admin.contratacao.vagas.update', $vacancy), ['title' => 'X'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->delete(route('admin.contratacao.vagas.stages.destroy', [$vacancy, $stage]))
            ->assertForbidden();
    }

    public function test_quick_candidate_registration_requires_candidates_create(): void
    {
        $admin = $this->adminWith(['contratacao_vagas' => ['view', 'create']]);
        $vacancy = $this->vacancy();

        $this->actingAs($admin)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), ['name' => 'Fulana'])
            ->assertForbidden();

        $candidate = TalentCandidate::query()->create(['name' => 'Cadastrada', 'email' => 'cad@example.com']);
        $this->actingAs($admin)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), ['talent_candidate_id' => $candidate->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, HiringApplication::query()->count());
    }

    public function test_stage_or_application_of_another_vacancy_returns_404(): void
    {
        $owner = $this->owner();
        $vacancyA = $this->vacancy(null, 'Vaga A');
        $vacancyB = $this->vacancy(null, 'Vaga B');
        $stageB = $vacancyB->stages()->first();

        $candidate = TalentCandidate::query()->create(['name' => 'Pessoa B', 'email' => 'b@example.com']);
        $applicationB = app(HiringVacancyService::class)->addCandidate($vacancyB, $candidate, null, null);

        $this->actingAs($owner)
            ->patch(route('admin.contratacao.vagas.stages.update', [$vacancyA, $stageB]), ['name' => 'Invadida'])
            ->assertNotFound();
        $this->actingAs($owner)
            ->delete(route('admin.contratacao.vagas.stages.destroy', [$vacancyA, $stageB]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->patch(route('admin.contratacao.vagas.applications.move', [$vacancyA, $applicationB]), [
                'stage_id' => $vacancyA->stages()->first()->id,
            ])
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.notes.store', [$vacancyA, $applicationB]), ['note' => 'x'])
            ->assertNotFound();
        $this->actingAs($owner)
            ->delete(route('admin.contratacao.vagas.applications.destroy', [$vacancyA, $applicationB]))
            ->assertNotFound();

        // Etapa de outra vaga como destino do movimento: recusada.
        $this->actingAs($owner)
            ->patch(route('admin.contratacao.vagas.applications.move', [$vacancyB, $applicationB]), [
                'stage_id' => $vacancyA->stages()->first()->id,
            ])
            ->assertSessionHasErrors('stage_id');

        $this->assertSame('Triagem', $stageB->fresh()->name);
        $this->assertSame($stageB->id, $applicationB->fresh()->hiring_vacancy_stage_id);
    }

    public function test_available_candidates_returns_up_to_ten_active_outside_vacancy(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();

        for ($i = 1; $i <= 12; $i++) {
            TalentCandidate::query()->create(['name' => sprintf('Candidato %02d', $i)]);
        }
        $inVacancy = TalentCandidate::query()->where('name', 'Candidato 01')->firstOrFail();
        app(HiringVacancyService::class)->addCandidate($vacancy, $inVacancy, null, null);
        TalentCandidate::query()->where('name', 'Candidato 02')->update(['archived_at' => now()]);

        $response = $this->actingAs($owner)
            ->getJson(route('admin.contratacao.vagas.candidates.available', $vacancy))
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertCount(10, $names);
        $this->assertNotContains('Candidato 01', $names);
        $this->assertNotContains('Candidato 02', $names);

        $this->actingAs($owner)
            ->getJson(route('admin.contratacao.vagas.candidates.available', ['vacancy' => $vacancy, 'q' => 'Candidato 12']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_creates_vacancy_with_default_stages_and_refuses_process_in_contratacao(): void
    {
        $owner = $this->owner();
        $process = $this->process();

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.store'), [
                'hiring_process_id' => $process->id,
                'title' => 'Coordenador',
            ])
            ->assertRedirect();

        $vacancy = HiringVacancy::query()->firstOrFail();
        $this->assertSame($process->company_id, $vacancy->company_id);
        $this->assertSame(
            ['Triagem', 'Entrevista', 'Finalistas', 'Contratado'],
            $vacancy->stages->pluck('name')->all(),
        );
        $this->assertSame([1, 2, 3, 4], $vacancy->stages->pluck('position')->all());
        $this->assertSame([false, false, false, true], $vacancy->stages->pluck('is_hired')->all());

        $closed = $this->process(HiringProcessStage::Contratacao);
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.store'), [
                'hiring_process_id' => $closed->id,
                'title' => 'Tarde demais',
            ])
            ->assertSessionHasErrors('hiring_process_id');

        $this->assertSame(1, HiringVacancy::query()->count());
    }

    public function test_status_change_sets_and_clears_closed_at(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();

        $this->actingAs($owner)->patch(route('admin.contratacao.vagas.update', $vacancy), ['status' => 'filled']);
        $this->assertNotNull($vacancy->fresh()->closed_at);

        $this->actingAs($owner)->patch(route('admin.contratacao.vagas.update', $vacancy), ['status' => 'open']);
        $this->assertNull($vacancy->fresh()->closed_at);
    }
}
