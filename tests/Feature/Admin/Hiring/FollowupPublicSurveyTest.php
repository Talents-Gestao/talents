<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Actions\Feedback\MergeCompanyEmployees;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\HiringProcessStage;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyNotice;
use App\Models\EmploymentCycle;
use App\Models\FeedbackAuditLog;
use App\Models\HiringFollowupInvitation;
use App\Models\HiringFollowupResponse;
use App\Models\User;
use App\Services\Hiring\EmploymentCycleService;
use App\Services\Hiring\FollowupInvitationService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

class FollowupPublicSurveyTest extends FollowupTestCase
{
    /**
     * @return array{0: EmploymentCycle, 1: HiringFollowupInvitation, 2: HiringFollowupInvitation}
     */
    private function invitations(?EmploymentCycle $cycle = null): array
    {
        $cycle ??= $this->activeCycle();
        $milestone = $cycle->milestones()->first();
        $results = collect(app(FollowupInvitationService::class)->send($milestone, 'both', 'copy', null))->keyBy('audience');

        return [
            $cycle,
            HiringFollowupInvitation::query()->findOrFail($results['collaborator']['invitation_id']),
            HiringFollowupInvitation::query()->findOrFail($results['manager']['invitation_id']),
        ];
    }

    public function test_public_page_answers_once(): void
    {
        [$cycle, $collaborator] = $this->invitations();

        $this->get(route('hiring-followup.public', $collaborator->token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('HiringFollowup/Take')
                ->where('survey.audience', 'collaborator')
                ->where('survey.greeting_name', 'Ana')
                ->where('survey.moment_label', 'D15')
                ->where('survey.subject_name', null)
                ->missing('survey.recipient_email'));

        $this->post(route('hiring-followup.public.submit', $collaborator->token), ['rating' => 6])->assertSessionHasErrors('rating');
        $this->post(route('hiring-followup.public.submit', $collaborator->token), ['rating' => 4, 'comment' => str_repeat('a', 2001)])->assertSessionHasErrors('comment');

        $this->post(route('hiring-followup.public.submit', $collaborator->token), ['rating' => 4, 'comment' => 'Estou gostando.'])
            ->assertRedirect(route('hiring-followup.public.thanks', $collaborator->token));
        $this->assertNotNull($collaborator->fresh()->answered_at);
        $this->assertNotNull($cycle->milestones()->first()->completed_at);

        $this->post(route('hiring-followup.public.submit', $collaborator->token), ['rating' => 1])->assertSessionHasErrors('rating');
        $this->assertSame(1, HiringFollowupResponse::query()->count());

        $this->get(route('hiring-followup.public', $collaborator->token))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('HiringFollowup/Unavailable')
                ->where('message', 'Esta pesquisa já foi respondida. Obrigado pela participação!'));
    }

    public function test_expired_and_revoked_links_refuse(): void
    {
        [, $collaborator, $manager] = $this->invitations();
        $collaborator->update(['expires_at' => now()->subMinute()]);
        $manager->update(['revoked_at' => now(), 'revoke_reason' => 'Destinatário alterado']);

        $this->get(route('hiring-followup.public', $collaborator->token))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('HiringFollowup/Unavailable')->where('message', fn ($m) => str_contains($m, 'expirou')));
        $this->post(route('hiring-followup.public.submit', $collaborator->token), ['rating' => 5])->assertSessionHasErrors('rating');

        $this->get(route('hiring-followup.public', $manager->token))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('HiringFollowup/Unavailable')->where('message', fn ($m) => str_contains($m, 'cancelado')));
        $this->post(route('hiring-followup.public.submit', $manager->token), ['rating' => 5])->assertSessionHasErrors('rating');

        $this->assertSame(0, HiringFollowupResponse::query()->count());
        $this->get('/pos-contratacao/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_collaborator_comment_never_reaches_manager_or_portal(): void
    {
        $company = Company::query()->create(['name' => 'Empresa Portal', 'is_active' => true, 'acompanhamento_access' => true]);
        $this->subscribeCompanyToNr1($company);
        $vacancy = $this->vacancy($this->process(HiringProcessStage::EntrevistaGestor, $company));
        [$application] = $this->hiredApplication('Ana Souza', $vacancy);
        $manager = $this->manager($company->id);
        $cycle = app(EmploymentCycleService::class)->registerAdmission($application, [
            'manager_contact_id' => $manager->id,
            'admission_date' => now()->subDays(16)->toDateString(),
            'collaborator_email_opt_in' => true,
        ], null);
        [, $collaborator, $managerInvitation] = $this->invitations($cycle);

        $secret = 'Meu gestor me trata mal e penso em sair';
        $this->post(route('hiring-followup.public.submit', $collaborator->token), ['rating' => 2, 'comment' => $secret]);

        $managerPage = $this->get(route('hiring-followup.public', $managerInvitation->token))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('HiringFollowup/Take')->where('survey.subject_name', 'Ana Souza'));
        $this->assertStringNotContainsString($secret, $managerPage->getContent());
        $this->assertStringNotContainsString('rating', json_encode($managerPage->viewData('page')['props']['survey']));

        $companyAdmin = User::factory()->companyAdmin($company->id)->create();
        $portal = $this->actingAs($companyAdmin)
            ->get(route('client.acompanhamento.index', ['stage' => HiringProcessStage::EntrevistaGestor->value]))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString($secret, $portal);
        $this->actingAs($companyAdmin)->get(route('admin.contratacao.pos.show', $cycle))->assertForbidden();

        // A Talents vê a resposta no ciclo.
        $this->actingAs($this->owner())->get(route('admin.contratacao.pos.show', $cycle))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('milestones.0.invitations', fn ($invitations) => collect($invitations)->contains(fn ($i) => ($i['response']['comment'] ?? null) === $secret)));
    }

    public function test_support_request_creates_internal_notice_without_comment(): void
    {
        [$cycle, $collaborator] = $this->invitations();

        $this->post(route('hiring-followup.public.submit', $collaborator->token), [
            'rating' => 2,
            'comment' => 'Comentário sigiloso',
            'support_requested' => true,
            'support_note' => 'Texto do pedido sigiloso',
        ]);

        $notice = CompanyNotice::query()->where('event_kind', CompanyNoticeEventKind::HiringFollowupSupportRequested->value)->firstOrFail();
        $this->assertNull($notice->company_id);
        $this->assertSame('talents', $notice->audience->value ?? $notice->audience);
        $this->assertStringContainsString('Ana Souza', $notice->body);
        $this->assertStringContainsString('D15', $notice->body);
        $this->assertStringNotContainsString('sigiloso', $notice->title.$notice->body);
        $this->assertSame($cycle->id, (int) $notice->source_id);
    }

    public function test_merge_company_employees_moves_cycles(): void
    {
        $cycle = $this->activeCycle();
        $absorbed = $cycle->employee;
        $primary = CompanyEmployee::query()->create(['company_id' => $cycle->company_id, 'name' => 'Ana Souza (cadastro antigo)', 'email' => 'ana.antiga@example.com', 'is_active' => true]);
        $actor = $this->owner();
        $merge = app(MergeCompanyEmployees::class);

        $counts = $merge->preview($primary, $absorbed)['counts'];
        $this->assertArrayNotHasKey('employment_cycles', $counts);

        $result = $merge->execute($primary, $absorbed, $actor, $counts);
        $this->assertSame(1, $result['moved']['employment_cycles']);
        $this->assertSame($primary->id, $cycle->fresh()->company_employee_id);
        $this->assertSame($absorbed->talent_candidate_id, $primary->fresh()->talent_candidate_id);

        $log = FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_EMPLOYEES_MERGED)->firstOrFail();
        $this->assertSame(1, $log->details['moved']['employment_cycles']);

        // Idempotente.
        $this->assertTrue($merge->execute($primary->fresh(), $absorbed->fresh(), $actor, $counts)['already_merged']);
        $this->assertSame(1, EmploymentCycle::query()->where('company_employee_id', $primary->id)->count());
    }

    public function test_merge_refuses_two_open_cycles(): void
    {
        $cycle = $this->activeCycle();
        $other = CompanyEmployee::query()->create(['company_id' => $cycle->company_id, 'name' => 'Ana duplicada', 'email' => 'ana2@example.com', 'is_active' => true]);
        EmploymentCycle::query()->create(['company_id' => $cycle->company_id, 'company_employee_id' => $other->id, 'status' => 'active', 'admission_date' => now()->toDateString()]);
        $merge = app(MergeCompanyEmployees::class);

        $this->expectException(ValidationException::class);
        $merge->preview($other, $cycle->employee);
    }
}
