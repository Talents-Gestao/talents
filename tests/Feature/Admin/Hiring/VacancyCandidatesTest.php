<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\HiringApplicationEventType;
use App\Models\HiringApplication;
use App\Models\HiringApplicationEvent;
use App\Models\TalentCandidate;
use App\Services\Hiring\HiringVacancyService;
use App\Services\Hiring\TalentCandidateResolver;
use Illuminate\Validation\ValidationException;
use LogicException;

class VacancyCandidatesTest extends HiringVacancyTestCase
{
    public function test_candidate_is_not_duplicated_in_vacancy(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();
        $candidate = TalentCandidate::query()->create(['name' => 'Ana', 'email' => 'ana@example.com']);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), ['talent_candidate_id' => $candidate->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), ['talent_candidate_id' => $candidate->id])
            ->assertSessionHasErrors('talent_candidate_id');

        // Cadastro rápido com o mesmo e-mail (outra caixa) reaproveita a pessoa e também não duplica.
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), [
                'name' => 'Ana Outra',
                'email' => '  ANA@Example.com ',
            ])
            ->assertSessionHasErrors('talent_candidate_id');

        $this->assertSame(1, HiringApplication::query()->count());
        $this->assertSame(1, TalentCandidate::query()->count());
    }

    public function test_resolver_matches_by_solides_id_then_email_never_by_name(): void
    {
        $resolver = app(TalentCandidateResolver::class);

        $first = $resolver->findOrCreate(['name' => 'João Silva', 'email' => 'Joao@Example.com']);
        $this->assertSame('joao@example.com', $first->email_normalized);

        $sameEmail = $resolver->findOrCreate(['name' => 'Outro Nome', 'email' => 'joao@example.com', 'solides_id' => 'S-1']);
        $this->assertSame($first->id, $sameEmail->id);
        $this->assertSame('S-1', $sameEmail->fresh()->solides_id);

        $bySolides = $resolver->findOrCreate(['name' => 'Qualquer', 'solides_id' => 'S-1']);
        $this->assertSame($first->id, $bySolides->id);

        $homonym = $resolver->findOrCreate(['name' => 'João Silva']);
        $this->assertNotSame($first->id, $homonym->id);
        $this->assertSame(2, TalentCandidate::query()->count());
    }

    public function test_archived_candidate_is_refused_without_duplicating(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();
        $archived = TalentCandidate::query()->create([
            'name' => 'Bia',
            'email' => 'bia@example.com',
            'archived_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), ['talent_candidate_id' => $archived->id])
            ->assertSessionHasErrors('talent_candidate_id');

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), [
                'name' => 'Bia Nova',
                'email' => 'BIA@example.com',
            ])
            ->assertSessionHasErrors(['email' => TalentCandidateResolver::ARCHIVED_MESSAGE]);

        $this->assertSame(0, HiringApplication::query()->count());
        $this->assertSame(1, TalentCandidate::query()->count());
    }

    public function test_email_change_resets_review_and_opt_in(): void
    {
        $candidate = TalentCandidate::query()->create([
            'name' => 'Caio',
            'email' => 'caio@example.com',
            'email_reviewed' => true,
            'email_opt_in' => true,
            'phone' => '11 9999-0000',
            'phone_reviewed' => true,
        ]);

        $candidate->update(['email' => 'caio.novo@example.com']);
        $candidate->refresh();

        $this->assertFalse($candidate->email_reviewed);
        $this->assertFalse($candidate->email_opt_in);
        $this->assertTrue($candidate->phone_reviewed);
    }

    public function test_move_records_event_and_hired_at(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();
        [$triagem, , , $contratado] = $vacancy->stages->all();
        $candidate = TalentCandidate::query()->create(['name' => 'Davi']);
        $application = app(HiringVacancyService::class)->addCandidate($vacancy, $candidate, null, $owner->id);

        $this->assertSame($triagem->id, $application->hiring_vacancy_stage_id);
        $this->assertNull($application->hired_at);

        $this->actingAs($owner)
            ->patch(route('admin.contratacao.vagas.applications.move', [$vacancy, $application]), [
                'stage_id' => $contratado->id,
            ])
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame($contratado->id, $application->hiring_vacancy_stage_id);
        $this->assertNotNull($application->hired_at);

        $moved = HiringApplicationEvent::query()
            ->where('hiring_application_id', $application->id)
            ->where('type', HiringApplicationEventType::Moved->value)
            ->firstOrFail();
        $this->assertSame($triagem->id, $moved->from_stage_id);
        $this->assertSame($contratado->id, $moved->to_stage_id);
        $this->assertSame($owner->id, $moved->user_id);

        $this->actingAs($owner)
            ->patch(route('admin.contratacao.vagas.applications.move', [$vacancy, $application]), [
                'stage_id' => $triagem->id,
            ]);
        $this->assertNull($application->fresh()->hired_at);
        $this->assertSame(3, $application->events()->count());
    }

    public function test_move_respects_position_inside_stage(): void
    {
        $service = app(HiringVacancyService::class);
        $vacancy = $this->vacancy();
        $triagem = $vacancy->stages->first();

        $a = $service->addCandidate($vacancy, TalentCandidate::query()->create(['name' => 'A']), null, null);
        $b = $service->addCandidate($vacancy, TalentCandidate::query()->create(['name' => 'B']), null, null);
        $c = $service->addCandidate($vacancy, TalentCandidate::query()->create(['name' => 'C']), null, null);

        $service->moveApplication($c, $triagem, 0, null);

        $order = HiringApplication::query()
            ->where('hiring_vacancy_stage_id', $triagem->id)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();
        $this->assertSame([$c->id, $a->id, $b->id], $order);
        // Reordenar na mesma etapa não gera evento de movimento.
        $this->assertSame(0, $c->events()->where('type', HiringApplicationEventType::Moved->value)->count());
    }

    public function test_marking_another_hired_stage_recalculates_hired_at(): void
    {
        $service = app(HiringVacancyService::class);
        $vacancy = $this->vacancy();
        [, , $finalistas, $contratado] = $vacancy->stages->all();

        $hired = $service->addCandidate($vacancy, TalentCandidate::query()->create(['name' => 'H']), $contratado, null);
        $finalist = $service->addCandidate($vacancy, TalentCandidate::query()->create(['name' => 'F']), $finalistas, null);
        $this->assertNotNull($hired->fresh()->hired_at);

        $service->markHiredStage($finalistas, null);

        $this->assertFalse($contratado->fresh()->is_hired);
        $this->assertTrue($finalistas->fresh()->is_hired);
        $this->assertNull($hired->fresh()->hired_at);
        $this->assertNotNull($finalist->fresh()->hired_at);
        $this->assertSame(1, $vacancy->stages()->where('is_hired', true)->count());
    }

    public function test_note_and_removal_keep_candidate(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();
        $candidate = TalentCandidate::query()->create(['name' => 'Eva']);
        $application = app(HiringVacancyService::class)->addCandidate($vacancy, $candidate, null, null);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.notes.store', [$vacancy, $application]), ['note' => 'Retornar sexta'])
            ->assertSessionHasNoErrors();
        $this->assertSame(
            'Retornar sexta',
            $application->events()->where('type', HiringApplicationEventType::Note->value)->value('note'),
        );

        $this->actingAs($owner)
            ->delete(route('admin.contratacao.vagas.applications.destroy', [$vacancy, $application]))
            ->assertSessionHasNoErrors();

        $this->assertNull($application->fresh());
        $this->assertNotNull($candidate->fresh());
    }

    public function test_events_are_insert_only(): void
    {
        $vacancy = $this->vacancy();
        $application = app(HiringVacancyService::class)
            ->addCandidate($vacancy, TalentCandidate::query()->create(['name' => 'Fil']), null, null);
        $event = $application->events()->firstOrFail();

        $this->expectException(LogicException::class);
        $event->update(['note' => 'alterado']);
    }

    public function test_service_refuses_archived_candidate_directly(): void
    {
        $vacancy = $this->vacancy();
        $candidate = TalentCandidate::query()->create(['name' => 'Gil', 'archived_at' => now()]);

        $this->expectException(ValidationException::class);
        app(HiringVacancyService::class)->addCandidate($vacancy, $candidate, null, null);
    }
}
