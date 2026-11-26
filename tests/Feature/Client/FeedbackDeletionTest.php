<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\FeedbackSessionStatus;
use App\Enums\PermissionAction;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackSessionSignature;
use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateQuestion;
use App\Models\User;
use App\Services\Feedback\FeedbackTeamAnalyticsService;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * Excluir feedback e excluir modelo (inclusive já usado, levando junto os feedbacks feitos com ele):
 * exclusão lógica em cascata numa transação, confirmação explícita, contagem conferida e trilha de auditoria.
 */
class FeedbackDeletionTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private CompanyEmployee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->company = $this->createFeedbackCompany();
        $this->subscribeCompanyToNr1($this->company);
        $this->admin = User::factory()->companyAdmin($this->company->id)->create(['name' => 'Admin Empresa']);
        $this->employee = $this->createFeedbackEmployee($this->company, $this->admin, ['name' => 'Ana Souza']);
        $this->seedFeedbackTemplate();
    }

    private function ownTemplate(string $title = 'Modelo da empresa'): FeedbackTemplate
    {
        $template = FeedbackTemplate::query()->create([
            'company_id' => $this->company->id, 'title' => $title, 'is_active' => true,
        ]);
        $section = $template->sections()->create([
            'key' => 'percepcoes', 'title' => 'Percepções', 'section_type' => 'questions', 'audience' => 'both', 'sort_order' => 1,
        ]);
        foreach (['termometro_nivel', 'perc_comportamento', 'perc_desempenho'] as $i => $key) {
            $section->questions()->create([
                'key' => $key, 'body' => $key, 'question_type' => 'single_choice', 'sort_order' => $i + 1,
            ]);
        }

        return $template;
    }

    /**
     * Feedback concluído com termômetro e percepções (entra no termômetro e no 9-box).
     */
    private function completedSession(FeedbackTemplate $template, ?User $leader = null): FeedbackSession
    {
        $session = $this->createFeedbackSession($this->company, $leader ?? $this->admin, $this->employee, [
            'feedback_template_id' => $template->id,
            'status' => FeedbackSessionStatus::Completed,
            'completed_at' => now(),
            'scheduled_at' => now()->subDay(),
        ]);

        $values = ['termometro_nivel' => 'muito_bom', 'perc_comportamento' => 'acima', 'perc_desempenho' => 'dentro'];
        $questions = FeedbackTemplateQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('feedback_template_id', $template->id))
            ->whereIn('key', array_keys($values))
            ->get();
        foreach ($questions as $question) {
            FeedbackSessionAnswer::query()->create([
                'feedback_session_id' => $session->id,
                'feedback_template_question_id' => $question->id,
                'value_text' => $values[$question->key],
            ]);
        }

        FeedbackSessionSignature::query()->create([
            'feedback_session_id' => $session->id, 'role' => 'employee', 'signer_name' => 'Ana', 'signer_email' => 'ana@t.local',
            'token' => (string) Str::uuid(), 'sent_at' => now(),
        ]);

        return $session;
    }

    private function viewOnlyTalentsAdmin(): User
    {
        $viewer = User::factory()->superAdmin()->create(['is_owner' => false]);
        app(SyncAdminUserPermissions::class)->execute($viewer->talentsWorkspace(), [
            ['module' => AdminPermissionModule::Feedbacks->value, 'action' => PermissionAction::View->value],
        ]);

        return $viewer;
    }

    // ── Excluir feedback ────────────────────────────────────────────────

    public function test_deleting_a_feedback_requires_explicit_confirmation(): void
    {
        $session = $this->completedSession($this->ownTemplate());

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.sessions.destroy', $session))
            ->assertSessionHasErrors(['confirmed' => 'Confirme que o feedback e todas as respostas serão excluídos.']);

        $this->assertNotSoftDeleted($session);
        $this->assertSame(3, FeedbackSessionAnswer::query()->where('feedback_session_id', $session->id)->count());
        $this->assertSame(0, FeedbackAuditLog::query()->count());
    }

    public function test_deleting_a_feedback_soft_deletes_it_with_its_answers_and_logs_it(): void
    {
        $template = $this->ownTemplate();
        $session = $this->completedSession($template);
        $other = $this->completedSession($template);

        $this->actingAs($this->admin)
            ->get(route('client.feedbacks.sessions.show', $session))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canDelete', true)
                ->where('session.answers_count', 3));

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.sessions.destroy', $session), ['confirmed' => true])
            ->assertRedirect(route('client.feedbacks.sessions.index'))
            ->assertSessionHas('success', 'Feedback excluído.');

        $this->assertSoftDeleted($session);
        $this->assertSame(0, FeedbackSessionAnswer::query()->where('feedback_session_id', $session->id)->count());
        $this->assertSame(3, FeedbackSessionAnswer::withTrashed()->where('feedback_session_id', $session->id)->count(), 'Recuperável pelo suporte.');
        $this->assertNotSoftDeleted($other);
        $this->assertNotSoftDeleted($template);

        $log = FeedbackAuditLog::query()->sole();
        $this->assertSame(FeedbackAuditLog::ACTION_SESSION_DELETED, $log->action);
        $this->assertSame($session->id, (int) $log->subject_id);
        $this->assertSame($this->admin->id, (int) $log->user_id);
        $this->assertSame($this->company->id, (int) $log->company_id);
        $this->assertSame(1, $log->sessions_count);
        $this->assertSame(3, $log->answers_count);
        $this->assertNotNull($log->created_at);

        // Some de todas as telas e o link direto deixa de existir.
        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.show', $session))->assertNotFound();
        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('sessions.data', 1)->where('sessions.data.0.id', $other->id));
    }

    public function test_company_leader_cannot_delete_feedbacks(): void
    {
        $leader = User::factory()->companyUser($this->company->id)->create();
        $own = $this->completedSession($this->ownTemplate(), $leader);
        $others = $this->completedSession(FeedbackTemplate::query()->whereNull('company_id')->firstOrFail());

        $this->actingAs($leader)->delete(route('client.feedbacks.sessions.destroy', $others), ['confirmed' => true])->assertForbidden();
        $this->actingAs($leader)->delete(route('client.feedbacks.sessions.destroy', $own), ['confirmed' => true])->assertForbidden();

        $this->assertNotSoftDeleted($own);
        $this->assertNotSoftDeleted($others);
    }

    public function test_talents_admin_permissions_for_deleting_feedbacks(): void
    {
        $session = $this->completedSession($this->ownTemplate());
        $viewer = $this->viewOnlyTalentsAdmin();
        $as = fn (User $user) => $this->actingAs($user)->withSession([FeedbackCompanyContext::SESSION_KEY => $this->company->id]);

        $as($viewer)->get(route('admin.feedbacks.sessions.show', $session))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canDelete', false));
        $as($viewer)->delete(route('admin.feedbacks.sessions.destroy', $session), ['confirmed' => true])->assertForbidden();
        $this->assertNotSoftDeleted($session);

        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $as($owner)->delete(route('admin.feedbacks.sessions.destroy', $session), ['confirmed' => true])
            ->assertRedirect(route('admin.feedbacks.sessions.index'));
        $this->assertSoftDeleted($session);
    }

    public function test_cannot_delete_another_company_feedback(): void
    {
        $other = $this->createFeedbackCompany(['name' => 'Outra']);
        $otherAdmin = User::factory()->companyAdmin($other->id)->create();
        $session = $this->completedSession($this->ownTemplate());

        $this->actingAs($otherAdmin)->delete(route('client.feedbacks.sessions.destroy', $session), ['confirmed' => true])->assertForbidden();
        $this->assertNotSoftDeleted($session);
    }

    // ── Excluir modelo ──────────────────────────────────────────────────

    public function test_unused_template_is_deleted_with_simple_confirmation(): void
    {
        $template = $this->ownTemplate();

        $this->actingAs($this->admin)->getJson(route('client.feedbacks.templates.deletion', $template))
            ->assertOk()
            ->assertExactJson([
                'id' => $template->id,
                'title' => 'Modelo da empresa',
                'sessions_count' => 0,
                'answers_count' => 0,
                'blocked' => false,
                'blocked_message' => null,
            ]);

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.templates.destroy', $template))
            ->assertRedirect(route('client.feedbacks.templates.index'))
            ->assertSessionHas('success', 'Modelo de feedback excluído.');

        $this->assertSoftDeleted($template);
        $log = FeedbackAuditLog::query()->sole();
        $this->assertSame(FeedbackAuditLog::ACTION_TEMPLATE_DELETED, $log->action);
        $this->assertSame(0, $log->sessions_count);
    }

    public function test_used_template_without_confirmation_is_refused(): void
    {
        $template = $this->ownTemplate();
        $session = $this->completedSession($template);

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.templates.destroy', $template), ['sessions_count' => 1])
            ->assertSessionHasErrors(['confirmed' => 'Este modelo já foi usado em 1 feedback. Confirme que ele também será excluído.']);

        $this->assertNotSoftDeleted($template);
        $this->assertNotSoftDeleted($session);
        $this->assertSame(0, FeedbackAuditLog::query()->count());
    }

    public function test_used_template_with_outdated_count_is_refused(): void
    {
        $template = $this->ownTemplate();
        $this->completedSession($template);

        // A tela abriu com 1 feedback; outro foi criado antes de confirmar.
        $this->completedSession($template);

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.templates.destroy', $template), ['confirmed' => true, 'sessions_count' => 1])
            ->assertSessionHasErrors([
                'sessions_count' => 'O número de feedbacks deste modelo mudou desde que você abriu o alerta (agora são 2). Confira o impacto e confirme de novo.',
            ]);

        $this->assertNotSoftDeleted($template);
        $this->assertSame(2, FeedbackSession::query()->where('feedback_template_id', $template->id)->count());
        $this->assertSame(0, FeedbackAuditLog::query()->count());
    }

    public function test_used_template_with_confirmation_deletes_template_feedbacks_and_answers(): void
    {
        $template = $this->ownTemplate();
        $a = $this->completedSession($template);
        $b = $this->completedSession($template);
        $kept = $this->completedSession(FeedbackTemplate::query()->whereNull('company_id')->firstOrFail());

        $this->actingAs($this->admin)->getJson(route('client.feedbacks.templates.deletion', $template))
            ->assertOk()
            ->assertJson(['sessions_count' => 2, 'answers_count' => 6]);

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.templates.destroy', $template), ['confirmed' => true, 'sessions_count' => 2])
            ->assertRedirect(route('client.feedbacks.templates.index'))
            ->assertSessionHas('success', 'Modelo excluído, junto com 2 feedbacks feitos com ele.');

        $this->assertSoftDeleted($template);
        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->assertSame(0, FeedbackSessionAnswer::query()->whereIn('feedback_session_id', [$a->id, $b->id])->count());
        $this->assertSame(6, FeedbackSessionAnswer::onlyTrashed()->whereIn('feedback_session_id', [$a->id, $b->id])->count());
        $this->assertNotSoftDeleted($kept);
        $this->assertSame(3, FeedbackSessionAnswer::query()->where('feedback_session_id', $kept->id)->count());

        $log = FeedbackAuditLog::query()->sole();
        $this->assertSame(FeedbackAuditLog::ACTION_TEMPLATE_DELETED, $log->action);
        $this->assertSame($template->id, (int) $log->subject_id);
        $this->assertSame('Modelo da empresa', $log->subject_label);
        $this->assertSame($this->admin->id, (int) $log->user_id);
        $this->assertSame(2, $log->sessions_count);
        $this->assertSame(6, $log->answers_count);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $log->details['feedback_session_ids']);
    }

    public function test_failure_in_the_middle_leaves_nothing_half_deleted(): void
    {
        $template = $this->ownTemplate();
        $session = $this->completedSession($template);

        // Respostas e feedbacks já marcados quando o modelo falha: a transação desfaz tudo.
        FeedbackTemplate::deleting(fn () => throw new RuntimeException('falha simulada'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin)
                ->delete(route('client.feedbacks.templates.destroy', $template), ['confirmed' => true, 'sessions_count' => 1]);
            $this->fail('A exclusão deveria ter falhado.');
        } catch (RuntimeException $e) {
            $this->assertSame('falha simulada', $e->getMessage());
        }

        $this->assertNotSoftDeleted($template);
        $this->assertNotSoftDeleted($session);
        $this->assertSame(3, FeedbackSessionAnswer::query()->where('feedback_session_id', $session->id)->count());
        $this->assertSame(0, FeedbackAuditLog::query()->count());
    }

    public function test_after_deleting_a_used_template_panel_and_other_screens_have_no_errors_nor_orphans(): void
    {
        $template = $this->ownTemplate();
        $session = $this->completedSession($template);
        $signature = FeedbackSessionSignature::query()->where('feedback_session_id', $session->id)->firstOrFail();

        $before = app(FeedbackTeamAnalyticsService::class)->forCompany($this->company, $this->admin);
        $this->assertSame(1, $before['completed_count']);
        $this->assertSame(1, $before['nine_box']['total']);

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.templates.destroy', $template), ['confirmed' => true, 'sessions_count' => 1])
            ->assertSessionHasNoErrors();

        // Painel: termômetro e 9-box sem o feedback excluído.
        $this->actingAs($this->admin)->get(route('client.feedbacks.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('analytics.completed_count', 0)
                ->where('analytics.nine_box.total', 0)
                ->where('analytics.thermometer.series', [0, 0, 0, 0, 0])
                ->has('recentSessions', 0));

        // Lista de modelos e «Novo feedback» não mostram o modelo excluído; requisição direta recusa.
        $this->actingAs($this->admin)->get(route('client.feedbacks.templates.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where(
                'templates',
                fn ($templates) => collect($templates)->doesntContain('id', $template->id),
            ));
        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where(
                'templates',
                fn ($templates) => collect($templates)->doesntContain('id', $template->id),
            ));
        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.store'), [
            'employee_name' => 'Bruno', 'feedback_template_id' => $template->id,
            'leader_user_id' => $this->admin->id, 'scheduled_at' => now()->toDateTimeString(),
        ])->assertSessionHasErrors('feedback_template_id');
        $this->assertSame(0, FeedbackSession::query()->where('feedback_template_id', $template->id)->count());

        foreach (['edit', 'deletion'] as $action) {
            $this->actingAs($this->admin)->get(route("client.feedbacks.templates.{$action}", $template))->assertNotFound();
        }
        $this->actingAs($this->admin)->post(route('client.feedbacks.templates.duplicate', $template))->assertNotFound();

        // Feedback excluído: telas, PDF e link de assinatura dão 404, nunca 500.
        foreach (['show', 'edit', 'pdf'] as $action) {
            $this->actingAs($this->admin)->get(route("client.feedbacks.sessions.{$action}", $session))->assertNotFound();
        }
        $this->get(route('feedback.sign.show', $signature->token))->assertNotFound();
        $this->post(route('feedback.sign.store', $signature->token), [])->assertNotFound();
    }

    public function test_template_deletion_permissions(): void
    {
        $template = $this->ownTemplate();
        $this->completedSession($template);
        $talents = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();

        // Modelo padrão da Talents: a empresa não exclui (nem vê o impacto).
        $this->actingAs($this->admin)->getJson(route('client.feedbacks.templates.deletion', $talents))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('client.feedbacks.templates.destroy', $talents), ['confirmed' => true, 'sessions_count' => 0])->assertNotFound();
        $this->assertNotSoftDeleted($talents);

        // Líder comum: 403.
        $leader = User::factory()->companyUser($this->company->id)->create();
        $this->actingAs($leader)->delete(route('client.feedbacks.templates.destroy', $template), ['confirmed' => true, 'sessions_count' => 1])->assertForbidden();

        // Admin da Talents só com «ver»: 403 no impacto e na exclusão.
        $viewer = $this->viewOnlyTalentsAdmin();
        $as = fn () => $this->actingAs($viewer)->withSession([FeedbackCompanyContext::SESSION_KEY => $this->company->id]);
        $as()->getJson(route('admin.feedbacks.templates.deletion', $template))->assertForbidden();
        $as()->delete(route('admin.feedbacks.templates.destroy', $template), ['confirmed' => true, 'sessions_count' => 1])->assertForbidden();

        $this->assertNotSoftDeleted($template);
        $this->assertSame(0, FeedbackAuditLog::query()->count());
    }

    public function test_clearing_an_answer_while_editing_still_allows_answering_again(): void
    {
        $template = $this->ownTemplate();
        $session = $this->createFeedbackSession($this->company, $this->admin, $this->employee, ['feedback_template_id' => $template->id]);
        $question = FeedbackTemplateQuestion::query()->where('key', 'termometro_nivel')
            ->whereHas('section', fn ($q) => $q->where('feedback_template_id', $template->id))->firstOrFail();

        foreach (['bom', '', 'regular'] as $value) {
            $this->actingAs($this->admin)
                ->patch(route('client.feedbacks.sessions.update', $session), ['answers' => [$question->id => $value]])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('regular', FeedbackSessionAnswer::query()->where('feedback_session_id', $session->id)->sole()->value_text);
    }
}
