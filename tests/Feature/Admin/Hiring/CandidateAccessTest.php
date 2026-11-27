<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Models\TalentCandidate;
use Inertia\Testing\AssertableInertia;

class CandidateAccessTest extends HiringVacancyTestCase
{
    public function test_admin_without_candidates_permission_gets_403(): void
    {
        $admin = $this->adminWith(['contratacao_vagas' => ['view', 'create', 'edit', 'delete']]);
        $candidate = TalentCandidate::query()->create(['name' => 'Ana']);

        $this->actingAs($admin)->get(route('admin.contratacao.candidatos.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.contratacao.candidatos.show', $candidate))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.contratacao.candidatos.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.contratacao.candidatos.store'), ['name' => 'Nova'])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.contratacao.candidatos.update', $candidate), ['name' => 'X'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.contratacao.candidatos.archive', $candidate))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.contratacao.candidatos.import-solides'), ['solides_id' => '1', 'name' => 'S'])->assertForbidden();

        $this->assertSame(1, TalentCandidate::query()->count());
        $this->assertNull($candidate->fresh()->archived_at);
    }

    public function test_view_permission_opens_list_and_ficha_but_not_changes(): void
    {
        $admin = $this->adminWith(['contratacao_candidatos' => ['view']]);
        $candidate = TalentCandidate::query()->create(['name' => 'Bruno', 'phone' => '+55 11 98888-7777']);

        $this->actingAs($admin)
            ->get(route('admin.contratacao.candidatos.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Hiring/Candidates/Index')
                ->has('candidates.data', 1)
                ->where('can.create', false)
                ->where('can.view_attachments', false));

        $this->actingAs($admin)
            ->get(route('admin.contratacao.candidatos.show', $candidate))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Hiring/Candidates/Show')
                ->where('candidate.name', 'Bruno')
                ->has('interviews', 0)
                ->has('audit'));

        $this->actingAs($admin)->patch(route('admin.contratacao.candidatos.update', $candidate), ['name' => 'X'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.contratacao.candidatos.archive', $candidate))->assertForbidden();
    }

    public function test_search_by_name_email_or_phone_digits_and_status_filter(): void
    {
        $owner = $this->owner();
        TalentCandidate::query()->create(['name' => 'Carla Souza', 'email' => 'carla@example.com', 'phone' => '+55 (11) 91234-5678']);
        TalentCandidate::query()->create(['name' => 'Davi Lima', 'email' => 'davi@example.com']);
        TalentCandidate::query()->create(['name' => 'Arquivada', 'archived_at' => now()]);

        $names = fn (array $query) => collect(
            $this->actingAs($owner)->get(route('admin.contratacao.candidatos.index', $query))
                ->viewData('page')['props']['candidates']['data']
        )->pluck('name')->all();

        $this->assertSame(['Carla Souza'], $names(['q' => '912345678']));
        $this->assertSame(['Carla Souza'], $names(['q' => '11 91234']));
        $this->assertSame(['Davi Lima'], $names(['q' => 'DAVI@']));
        $this->assertSame(['Carla Souza', 'Davi Lima'], $names([]));
        $this->assertSame(['Arquivada'], $names(['status' => 'archived']));
        $this->assertCount(3, $names(['status' => 'all']));
    }

    public function test_international_phone_is_required_format(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.store'), ['name' => 'Eva', 'phone' => '11 91234-5678'])
            ->assertSessionHasErrors('phone');

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.store'), ['name' => 'Eva', 'phone' => '+55 11 91234-5678'])
            ->assertSessionHasNoErrors();

        $this->assertSame('5511912345678', TalentCandidate::query()->value('phone_digits'));
    }
}
