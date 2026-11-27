<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\TalentCandidateAuditAction;
use App\Models\HiringApplication;
use App\Models\TalentCandidate;
use App\Models\TalentCandidateAuditLog;
use App\Services\Hiring\HiringVacancyService;
use App\Services\Hiring\TalentCandidateResolver;
use Inertia\Testing\AssertableInertia;

class CandidateLifecycleTest extends HiringVacancyTestCase
{
    public function test_create_with_repeated_email_does_not_duplicate_and_points_to_existing(): void
    {
        $owner = $this->owner();
        $existing = TalentCandidate::query()->create(['name' => 'Ana Souza', 'email' => 'ana@example.com']);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.store'), ['name' => 'Ana S.', 'email' => ' ANA@example.com '])
            ->assertSessionHasErrors([
                'email',
                'existing_candidate_url' => route('admin.contratacao.candidatos.show', $existing),
            ]);

        $this->assertSame(1, TalentCandidate::query()->count());

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.store'), ['name' => 'Bruno', 'email' => 'bruno@example.com'])
            ->assertSessionHasNoErrors();
        $created = TalentCandidate::query()->where('email_normalized', 'bruno@example.com')->firstOrFail();
        $this->assertSame(1, TalentCandidateAuditLog::query()
            ->where('talent_candidate_id', $created->id)
            ->where('action', TalentCandidateAuditAction::Created->value)
            ->count());
    }

    public function test_edit_to_an_email_of_another_candidate_is_refused(): void
    {
        $owner = $this->owner();
        TalentCandidate::query()->create(['name' => 'Carla', 'email' => 'carla@example.com']);
        $davi = TalentCandidate::query()->create(['name' => 'Davi', 'email' => 'davi@example.com']);

        $this->actingAs($owner)
            ->patch(route('admin.contratacao.candidatos.update', $davi), ['name' => 'Davi', 'email' => 'CARLA@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame('davi@example.com', $davi->fresh()->email);
    }

    public function test_changing_email_and_phone_resets_reviews_and_audits_only_field_names(): void
    {
        $owner = $this->owner();
        $candidate = TalentCandidate::query()->create([
            'name' => 'Eva',
            'email' => 'eva@example.com',
            'phone' => '+55 11 90000-0000',
            'email_reviewed' => true,
            'email_opt_in' => true,
            'phone_reviewed' => true,
        ]);

        // O formulário reenvia as caixas marcadas; o servidor zera porque o contato mudou.
        $this->actingAs($owner)
            ->patch(route('admin.contratacao.candidatos.update', $candidate), [
                'name' => 'Eva',
                'email' => 'eva.nova@example.com',
                'phone' => '+55 11 90000-0000',
                'email_reviewed' => true,
                'email_opt_in' => true,
                'phone_reviewed' => true,
            ])
            ->assertSessionHasNoErrors();

        $candidate->refresh();
        $this->assertFalse($candidate->email_reviewed);
        $this->assertFalse($candidate->email_opt_in);
        $this->assertTrue($candidate->phone_reviewed);

        $this->actingAs($owner)
            ->patch(route('admin.contratacao.candidatos.update', $candidate), [
                'name' => 'Eva',
                'email' => 'eva.nova@example.com',
                'phone' => '+351 912 345 678',
                'phone_reviewed' => true,
            ])
            ->assertSessionHasNoErrors();
        $this->assertFalse($candidate->fresh()->phone_reviewed);

        $log = TalentCandidateAuditLog::query()
            ->where('action', TalentCandidateAuditAction::Updated->value)
            ->orderBy('id')
            ->firstOrFail();
        $this->assertEqualsCanonicalizing(['email', 'email_reviewed', 'email_opt_in'], $log->meta['fields']);
        $this->assertStringNotContainsString('eva.nova', json_encode($log->meta));
    }

    public function test_archive_and_restore_preserve_applications(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();
        $candidate = TalentCandidate::query()->create(['name' => 'Fil', 'email' => 'fil@example.com']);
        $application = app(HiringVacancyService::class)->addCandidate($vacancy, $candidate, null, null);

        $this->actingAs($owner)->post(route('admin.contratacao.candidatos.archive', $candidate))->assertSessionHasNoErrors();
        $this->assertNotNull($candidate->fresh()->archived_at);
        $this->assertSame($owner->id, $candidate->fresh()->archived_by);
        $this->assertNotNull($application->fresh());
        $this->assertSame(1, $application->events()->count());

        $this->actingAs($owner)->post(route('admin.contratacao.candidatos.restore', $candidate))->assertSessionHasNoErrors();
        $this->assertNull($candidate->fresh()->archived_at);
        $this->assertNotNull($application->fresh());

        $actions = TalentCandidateAuditLog::query()->where('talent_candidate_id', $candidate->id)->pluck('action')
            ->map(fn ($a) => $a->value)->all();
        $this->assertSame(['archived', 'restored'], $actions);
    }

    public function test_archived_candidate_cannot_enter_vacancy_and_link_points_to_ficha(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();
        $candidate = TalentCandidate::query()->create(['name' => 'Gil', 'email' => 'gil@example.com']);
        $this->actingAs($owner)->post(route('admin.contratacao.candidatos.archive', $candidate));

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), ['talent_candidate_id' => $candidate->id])
            ->assertSessionHasErrors('talent_candidate_id');

        // Cadastro rápido com o e-mail do arquivado: erro com link para a ficha (restaurar).
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), ['name' => 'Gil', 'email' => 'gil@example.com'])
            ->assertSessionHasErrors([
                'email' => TalentCandidateResolver::ARCHIVED_MESSAGE,
                'existing_candidate_url' => route('admin.contratacao.candidatos.show', $candidate),
            ]);

        // Sem permissão de restaurar, a mensagem vem sem link.
        $noRestore = $this->adminWith(['contratacao_vagas' => ['view', 'create'], 'contratacao_candidatos' => ['create']]);
        $this->actingAs($noRestore)
            ->post(route('admin.contratacao.vagas.applications.store', $vacancy), ['name' => 'Gil', 'email' => 'gil@example.com'])
            ->assertSessionHasErrors('email')
            ->assertSessionDoesntHaveErrors('existing_candidate_url');

        $this->assertSame(0, HiringApplication::query()->count());
        $this->assertSame(1, TalentCandidate::query()->count());
    }

    public function test_restore_is_refused_when_another_active_record_has_the_email(): void
    {
        $owner = $this->owner();
        $archived = TalentCandidate::query()->create(['name' => 'Hugo', 'email' => 'hugo@example.com', 'archived_at' => now()]);
        TalentCandidate::query()->create(['name' => 'Hugo novo', 'email' => 'hugo@example.com']);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.restore', $archived))
            ->assertSessionHasErrors('candidate');

        $this->assertNotNull($archived->fresh()->archived_at);
    }

    public function test_solides_import_reuses_by_solides_id_without_overwriting(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.import-solides'), [
                'solides_id' => '987',
                'name' => 'Iara Sólides',
                'email' => 'iara@example.com',
                'phone' => '+55 11 95555-0000',
            ])
            ->assertRedirect();

        $candidate = TalentCandidate::query()->where('solides_id', '987')->firstOrFail();
        $this->assertSame('Sólides', $candidate->source);
        $this->assertSame('iara@example.com', $candidate->email);

        // Edição manual depois da importação.
        $candidate->update(['name' => 'Iara Editada', 'current_role' => 'Analista']);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.import-solides'), [
                'solides_id' => '987',
                'name' => 'Iara Sólides',
                'email' => 'outro@example.com',
            ])
            ->assertRedirect(route('admin.contratacao.candidatos.show', $candidate));

        $candidate->refresh();
        $this->assertSame(1, TalentCandidate::query()->count());
        $this->assertSame('Iara Editada', $candidate->name);
        $this->assertSame('iara@example.com', $candidate->email);
        $this->assertSame('Analista', $candidate->current_role);
        $this->assertSame(1, TalentCandidateAuditLog::query()
            ->where('action', TalentCandidateAuditAction::ImportedSolides->value)->count());
    }

    public function test_solides_import_links_existing_email_without_overwriting(): void
    {
        $owner = $this->owner();
        $manual = TalentCandidate::query()->create(['name' => 'João Manual', 'email' => 'joao@example.com', 'source' => 'Indicação']);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.import-solides'), [
                'solides_id' => '555',
                'name' => 'Joao da Solides',
                'email' => 'JOAO@example.com',
            ])
            ->assertRedirect(route('admin.contratacao.candidatos.show', $manual));

        $manual->refresh();
        $this->assertSame(1, TalentCandidate::query()->count());
        $this->assertSame('555', $manual->solides_id);
        $this->assertSame('João Manual', $manual->name);
        $this->assertSame('Indicação', $manual->source);
    }

    public function test_vacancy_board_links_candidate_name_only_with_permission(): void
    {
        $vacancy = $this->vacancy();
        $candidate = TalentCandidate::query()->create(['name' => 'Lia']);
        app(HiringVacancyService::class)->addCandidate($vacancy, $candidate, null, null);

        $withCandidates = $this->adminWith(['contratacao_vagas' => ['view'], 'contratacao_candidatos' => ['view']]);
        $this->actingAs($withCandidates)
            ->get(route('admin.contratacao.vagas.show', $vacancy))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('applications.0.candidate.url', route('admin.contratacao.candidatos.show', $candidate)));

        $vacanciesOnly = $this->adminWith(['contratacao_vagas' => ['view']]);
        $this->actingAs($vacanciesOnly)
            ->get(route('admin.contratacao.vagas.show', $vacancy))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('applications.0.candidate.url', null));
    }
}
