<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Actions\Feedback\DeleteFeedbackTemplate;
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

    public function test_unused_template_is_deleted_definitively(): void
    {
        $template = $this->ownTemplate();

        $this->actingAs($this->admin)->getJson(route('client.feedbacks.templates.deletion', $template))
            ->assertOk()
            ->assertExactJson([
                'id' => $template->id,
                'title' => 'Modelo da empresa',
                'sessions_count' => 0,
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

    public function test_used_template_is_never_deleted_and_keeps_its_feedbacks(): void
    {
        $template = $this->ownTemplate();
        $a = $this->completedSession($template);
        $b = $this->completedSession($template);

        $this->actingAs($this->admin)->getJson(route('client.feedbacks.templates.deletion', $template))
            ->assertOk()
            ->assertJson([
                'sessions_count' => 2,
                'blocked' => true,
                'blocked_message' => DeleteFeedbackTemplate::IN_USE_MESSAGE,
            ]);

        // Nem com confirmação: o caminho que apagava feedbacks junto com o modelo não existe mais.
        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.templates.destroy', $template), ['confirmed' => true, 'sessions_count' => 2])
            ->assertSessionHasErrors(['template' => DeleteFeedbackTemplate::IN_USE_MESSAGE]);

        $this->assertNotSoftDeleted($template);
        $this->assertNotSoftDeleted($a);
        $this->assertNotSoftDeleted($b);
        $this->assertSame(6, FeedbackSessionAnswer::query()->whereIn('feedback_session_id', [$a->id, $b->id])->count());
        $this->assertSame(0, FeedbackAuditLog::query()->count());
    }

    public function test_archived_template_leaves_new_feedback_and_its_feedbacks_stay_normal(): void
    {
        $template = $this->ownTemplate();
        $session = $this->completedSession($template);
        $before = app(FeedbackTeamAnalyticsService::class)->forCompany($this->company, $this->admin);

        $this->actingAs($this->admin)
            ->patch(route('client.feedbacks.templates.archive', $template))
            ->assertRedirect(route('client.feedbacks.templates.index'))
            ->assertSessionHas('success', 'Modelo arquivado. Ele não aparece mais em «Novo feedback»; os feedbacks já feitos continuam normais.');

        $template->refresh();
        $this->assertTrue($template->isArchived());
        $this->assertSame($this->admin->id, (int) $template->archived_by_user_id);
        $this->assertSame(FeedbackAuditLog::ACTION_TEMPLATE_ARCHIVED, FeedbackAuditLog::query()->sole()->action);

        // Some de «Novo feedback» e a criação direta é recusada.
        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where(
                'templates',
                fn ($templates) => collect($templates)->doesntContain('id', $template->id),
            ));
        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.store'), [
            'employee_name' => 'Bruno', 'feedback_template_id' => $template->id,
            'leader_user_id' => $this->admin->id, 'scheduled_at' => now()->toDateTimeString(),
        ])->assertSessionHasErrors('feedback_template_id');

        // O feedback existente segue normal: tela, PDF, painel e respostas.
        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.show', $session))->assertOk();
        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.pdf', $session))->assertOk();
        $this->assertSame($before, app(FeedbackTeamAnalyticsService::class)->forCompany($this->company, $this->admin));
        $this->assertSame(3, FeedbackSessionAnswer::query()->where('feedback_session_id', $session->id)->count());

        // Reativar devolve o modelo à lista.
        $this->actingAs($this->admin)->patch(route('client.feedbacks.templates.unarchive', $template))->assertSessionHasNoErrors();
        $this->assertFalse($template->fresh()->isArchived());
        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where(
                'templates',
                fn ($templates) => collect($templates)->contains('id', $template->id),
            ));
    }

    public function test_deleted_feedback_screens_pdf_and_sign_link_return_404(): void
    {
        $session = $this->completedSession($this->ownTemplate());
        $signature = FeedbackSessionSignature::query()->where('feedback_session_id', $session->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.sessions.destroy', $session), ['confirmed' => true])
            ->assertSessionHasNoErrors();

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
