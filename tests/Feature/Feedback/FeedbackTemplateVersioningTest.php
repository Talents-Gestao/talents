<?php

declare(strict_types=1);

namespace Tests\Feature\Feedback;

use App\Actions\Feedback\ReopenFeedbackSession;
use App\Enums\FeedbackSessionStatus;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackSessionSignature;
use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateQuestion;
use App\Models\FeedbackTemplateVersion;
use App\Models\User;
use App\Services\Feedback\Ai\FeedbackAiContextBuilder;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackSessionStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * Versões dos modelos de feedback: editar modelo usado cria versão; feedbacks antigos ficam na versão deles;
 * arquivar em vez de excluir; atualizar feedback aberto de versão; bloqueio após convite e «Reabrir para edição».
 */
class FeedbackTemplateVersioningTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    private Company $company;

    private User $rh;

    private CompanyEmployee $employee;

    private FeedbackTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->company = $this->createFeedbackCompany(['name' => 'Empresa A']);
        $this->rh = User::factory()->companyAdmin($this->company->id)->create();
        $this->employee = $this->createFeedbackEmployee($this->company, $this->rh);
        $this->template = $this->companyTemplate();
    }

    public function test_editing_a_used_template_creates_a_version_and_the_old_feedback_stays_intact(): void
    {
        $session = $this->sessionWithAnswers(['meta' => 'Entregar o projeto', 'clima' => 'Bom']);
        $v1 = $this->template->currentVersion;

        $this->actingAs($this->rh)
            ->put(route('client.feedbacks.templates.update', $this->template), $this->payload('Modelo B', [
                ['key' => 'meta', 'body' => 'Qual é a meta do trimestre?'],
                ['key' => null, 'body' => 'Pergunta nova'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Modelo atualizado: criada a versão 2. Os feedbacks já feitos continuam na versão 1.');

        $this->template->refresh();
        $this->assertSame(2, $this->template->currentVersion->number);
        $this->assertSame(1, (int) $session->fresh()->templateVersion->number);

        // Tela: estrutura, título e respostas da versão 1.
        $this->actingAs($this->rh)->get(route('client.feedbacks.sessions.show', $session))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('session.template.version_number', 1)
                ->where('session.template.title', 'Modelo da empresa')
                ->where('session.template.sections.0.questions.0.body', 'Qual é a meta?')
                ->where('session.template.sections.0.questions.1.body', 'Como está o clima?')
                ->where('session.version.latest_number', 2)
                ->where('session.version.can_update', false) // concluído: imutável
                ->where('session.out_of_version_answers', []));

        // PDF: impressão da versão 1.
        $html = $this->pdfHtml($session);
        $this->assertStringContainsString('Como está o clima?', $html);
        $this->assertStringContainsString('Modelo da empresa (versão 1)', $html);
        $this->assertStringNotContainsString('Pergunta nova', $html);
        $this->actingAs($this->rh)->get(route('client.feedbacks.sessions.pdf', $session))->assertOk();

        // Assinatura: título e versão usados.
        $signature = $this->signature($session, signed: true);
        $this->get(route('feedback.sign.show', $signature->token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cancelled', false)
                ->where('session.template.version_number', 1)
                ->where('session.template.title', 'Modelo da empresa'));

        // As linhas da versão 1 não mudaram e as respostas continuam nelas.
        $this->assertSame(['Qual é a meta?', 'Como está o clima?'], $v1->sections()->first()->questions()->pluck('body')->all());
        $this->assertSame(2, FeedbackSessionAnswer::query()->where('feedback_session_id', $session->id)->count());

        // Novo feedback usa a versão 2.
        $new = $this->createFeedbackSession($this->company, $this->rh, $this->employee, ['feedback_template_id' => $this->template->id]);
        $this->assertSame(2, $new->fresh()->templateVersion->number);
    }

    public function test_same_structure_or_only_name_does_not_create_a_version_and_unused_edits_in_place(): void
    {
        $this->sessionWithAnswers(['meta' => 'X']);

        $same = $this->payload('Novo nome', [
            ['key' => 'meta', 'body' => 'Qual é a meta?'],
            ['key' => 'clima', 'body' => 'Como está o clima?'],
        ]);
        $this->actingAs($this->rh)->put(route('client.feedbacks.templates.update', $this->template), $same)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Modelo de feedback atualizado.');
        $this->assertSame(1, $this->template->versions()->count());
        $this->assertSame('Novo nome', $this->template->fresh()->title);
        // Versão usada: o título dela continua o da criação do feedback.
        $this->assertSame('Modelo da empresa', $this->template->currentVersion->fresh()->title);

        // Modelo sem uso: a edição altera a versão atual no lugar.
        $unused = $this->companyTemplate('Sem uso');
        $this->actingAs($this->rh)->put(route('client.feedbacks.templates.update', $unused), $this->payload('Sem uso', [
            ['key' => 'meta', 'body' => 'Outra pergunta'],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, $unused->versions()->count());
        $this->assertSame('Outra pergunta', $unused->fresh()->sections()->first()->questions()->first()->body);
    }

    public function test_new_keys_never_reuse_a_key_from_any_version(): void
    {
        $this->sessionWithAnswers(['clima' => 'Bom']);

        // v2: remove «clima»; v3 tenta devolver «clima» como chave de uma pergunta nova de outro sentido.
        $this->actingAs($this->rh)->put(route('client.feedbacks.templates.update', $this->template), $this->payload('M', [
            ['key' => 'meta', 'body' => 'Qual é a meta?'],
        ]))->assertSessionHasNoErrors();
        $this->createFeedbackSession($this->company, $this->rh, $this->employee, ['feedback_template_id' => $this->template->id]);

        $this->actingAs($this->rh)->put(route('client.feedbacks.templates.update', $this->template->fresh()), $this->payload('M', [
            ['key' => 'meta', 'body' => 'Qual é a meta?'],
            ['key' => 'clima', 'body' => 'Quanto você vendeu?'],
            ['key' => null, 'body' => 'Sem chave'],
        ]))->assertSessionHasNoErrors();

        $template = $this->template->fresh();
        $this->assertSame(3, $template->currentVersion->number);
        $keys = $template->sections()->first()->questions()->pluck('key', 'body');
        $this->assertSame('meta', $keys['Qual é a meta?']);
        $this->assertNotSame('clima', $keys['Quanto você vendeu?']);

        $history = FeedbackTemplateQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('feedback_template_id', $template->id)->where('feedback_template_version_id', '!=', $template->current_version_id))
            ->pluck('key')->all();
        $this->assertNotContains($keys['Quanto você vendeu?'], $history);
        $this->assertNotContains($keys['Sem chave'], $history);
        $this->assertMatchesRegularExpression('/^q_[a-z0-9]{10}$/', $keys['Sem chave']);
    }

    public function test_updating_an_open_feedback_preserves_answers_and_marks_the_removed_ones(): void
    {
        $session = $this->sessionWithAnswers(['meta' => 'Entregar', 'clima' => 'Tenso'], FeedbackSessionStatus::InProgress);
        $v1Section = $this->template->currentVersion->sections()->first();
        $session->update(['section_extras' => [(string) $v1Section->id => ['question' => 'Extra', 'answer' => 'Resposta extra']]]);

        $this->actingAs($this->rh)->put(route('client.feedbacks.templates.update', $this->template), $this->payload('M', [
            ['key' => 'meta', 'body' => 'Qual é a meta do trimestre?'],
            ['key' => null, 'body' => 'Pergunta nova'],
        ]))->assertSessionHasNoErrors();
        $v2 = $this->template->fresh()->currentVersion;

        $this->actingAs($this->rh)->get(route('client.feedbacks.sessions.show', $session))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('session.version.number', 1)
                ->where('session.version.latest_number', 2)
                ->where('session.version.can_update', true));

        $this->actingAs($this->rh)->getJson(route('client.feedbacks.sessions.version.preview', $session))
            ->assertOk()
            ->assertJson([
                'from' => ['number' => 1],
                'to' => ['id' => $v2->id, 'number' => 2],
                'kept' => [['key' => 'meta', 'answered' => true]],
                'removed' => [['key' => 'clima', 'answered' => true]],
                'answers_moved' => 1,
                'answers_out_of_version' => 1,
            ])
            ->assertJsonCount(1, 'added');

        // Sem confirmação: recusa.
        $this->actingAs($this->rh)->patch(route('client.feedbacks.sessions.version.update', $session), ['version_id' => $v2->id])
            ->assertSessionHasErrors('confirmed');

        $this->actingAs($this->rh)
            ->patch(route('client.feedbacks.sessions.version.update', $session), ['confirmed' => true, 'version_id' => $v2->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Feedback atualizado para a versão 2 do modelo. 1 resposta ficou fora da versão atual e continua guardada.');

        $session->refresh();
        $this->assertSame($v2->id, (int) $session->feedback_template_version_id);
        $answers = FeedbackSessionAnswer::query()->where('feedback_session_id', $session->id)->with('question')->get()->keyBy(fn ($a) => $a->question->key);
        $this->assertSame('Entregar', $answers['meta']->value_text);
        $this->assertSame($v2->id, (int) $answers['meta']->question->section->feedback_template_version_id);
        $this->assertSame('Tenso', $answers['clima']->value_text, 'A resposta removida continua guardada.');

        // Perguntas extras remapeadas pela chave da seção.
        $v2Section = $v2->sections()->first();
        $this->assertSame(['question' => 'Extra', 'answer' => 'Resposta extra'], $session->section_extras[(string) $v2Section->id]);

        // «Fora da versão atual»: na tela, no PDF e no contexto da IA.
        $this->actingAs($this->rh)->get(route('client.feedbacks.sessions.show', $session))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('session.template.version_number', 2)
                ->where('session.out_of_version_answers.0.question_key', 'clima')
                ->where('session.out_of_version_answers.0.value', 'Tenso')
                ->where('session.version.can_update', false));
        $this->assertStringContainsString('Fora da versão atual', $this->pdfHtml($session));
        $context = app(FeedbackAiContextBuilder::class)->answers($session->fresh());
        $this->assertStringContainsString("Fora da versão atual do modelo (respostas de uma versão anterior):\n- Como está o clima?: Tenso", $context);

        // Salvar o preenchimento não apaga a resposta de fora da versão.
        $metaV2 = $answers['meta']->question;
        $this->actingAs($this->rh)->patch(route('client.feedbacks.sessions.update', $session), ['answers' => [$metaV2->id => 'Entregar v2']])
            ->assertSessionHasNoErrors();
        $this->assertSame('Tenso', FeedbackSessionAnswer::query()->whereKey($answers['clima']->id)->value('value_text'));

        $log = FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_SESSION_VERSION_UPDATED)->sole();
        $this->assertSame(['feedback_template_id' => $this->template->id, 'from_version' => 1, 'to_version' => 2, 'answers_moved' => 1, 'answers_out_of_version' => 1], $log->details);
        $this->assertStringNotContainsString('Tenso', json_encode($log->toArray()));
    }

    public function test_editing_is_blocked_after_the_signature_invite(): void
    {
        $session = $this->sessionWithAnswers(['meta' => 'X'], FeedbackSessionStatus::AwaitingSignatures);
        $this->signature($session);
        $this->actingAs($this->rh)->put(route('client.feedbacks.templates.update', $this->template), $this->payload('M', [
            ['key' => null, 'body' => 'Outra'],
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->rh)->get(route('client.feedbacks.sessions.edit', $session))->assertForbidden();
        $this->actingAs($this->rh)->patch(route('client.feedbacks.sessions.update', $session), ['answers' => []])->assertForbidden();
        $this->actingAs($this->rh)->getJson(route('client.feedbacks.sessions.version.preview', $session))->assertStatus(422);
        $this->actingAs($this->rh)
            ->patch(route('client.feedbacks.sessions.version.update', $session), ['confirmed' => true, 'version_id' => $this->template->fresh()->current_version_id])
            ->assertSessionHasErrors('version');

        $this->actingAs($this->rh)->get(route('client.feedbacks.sessions.show', $session))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('session.can_edit', false)
                ->where('session.version.can_update', false)
                ->where('session.reopen', ['invites' => 1, 'signed' => 0]));
    }

    public function test_reopening_discards_signatures_and_invalidates_the_links(): void
    {
        $session = $this->sessionWithAnswers(['meta' => 'X'], FeedbackSessionStatus::AwaitingSignatures);
        $signed = $this->signature($session, signed: true);
        $pending = $this->signature($session, role: 'leader');

        // Contagem desatualizada ou sem confirmação: recusa.
        $this->actingAs($this->rh)->patch(route('client.feedbacks.sessions.reopen', $session), ['signed_count' => 1])
            ->assertSessionHasErrors('confirmed');
        $this->actingAs($this->rh)->patch(route('client.feedbacks.sessions.reopen', $session), ['confirmed' => true, 'signed_count' => 0])
            ->assertSessionHasErrors('signed_count');
        $this->assertSame(FeedbackSessionStatus::AwaitingSignatures, $session->fresh()->status);

        $this->actingAs($this->rh)
            ->patch(route('client.feedbacks.sessions.reopen', $session), ['confirmed' => true, 'signed_count' => 1])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Feedback reaberto para edição. Os convites foram cancelados e 1 assinatura foi descartada.');

        $session->refresh();
        $this->assertSame(FeedbackSessionStatus::InProgress, $session->status);
        $this->assertSame(0, $session->signatures()->count());
        $this->assertSame(2, $session->allSignatures()->whereNotNull('cancelled_at')->count());
        $this->actingAs($this->rh)->get(route('client.feedbacks.sessions.edit', $session))->assertOk();

        foreach ([$signed, $pending] as $signature) {
            $this->get(route('feedback.sign.show', $signature->token))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('cancelled', true)
                    ->where('cancelledMessage', ReopenFeedbackSession::CANCELLED_LINK_MESSAGE)
                    ->where('session', null));
        }
        $this->post(route('feedback.sign.store', $pending->token), [
            'signature_data' => 'data:image/png;base64,'.base64_encode(str_repeat('x', 200)),
            'declaration_accepted' => true,
        ])->assertRedirect(route('feedback.sign.show', $pending->token))
            ->assertSessionHas('error', ReopenFeedbackSession::CANCELLED_LINK_MESSAGE);
        $this->assertNull($pending->fresh()->signed_at);
        $this->assertNull($pending->fresh()->signature_path);

        $log = FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_SESSION_REOPENED)->sole();
        $this->assertSame(2, $log->details['invites_cancelled']);
        $this->assertSame(1, $log->details['signatures_discarded']);
    }

    public function test_used_template_is_archived_not_deleted_and_universal_only_by_admin(): void
    {
        $this->sessionWithAnswers(['meta' => 'X']);
        $this->actingAs($this->rh)->put(route('client.feedbacks.templates.update', $this->template), $this->payload('M', [
            ['key' => null, 'body' => 'Outra'],
        ]));
        // Usado só na versão 1: a exclusão definitiva continua recusada.
        $this->actingAs($this->rh)->delete(route('client.feedbacks.templates.destroy', $this->template))->assertSessionHasErrors('template');
        $this->assertNotSoftDeleted($this->template);

        $universal = FeedbackTemplate::query()->create(['company_id' => null, 'title' => 'Universal']);
        $this->actingAs($this->rh)->patch(route('client.feedbacks.templates.archive', $universal))->assertNotFound();
        $this->assertFalse($universal->fresh()->isArchived());

        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->actingAs($owner)->withSession([FeedbackCompanyContext::SESSION_KEY => $this->company->id])
            ->patch(route('admin.feedbacks.templates.archive', $universal))
            ->assertSessionHasNoErrors();
        $this->assertTrue($universal->fresh()->isArchived());
    }

    public function test_other_company_cannot_touch_versions_reopen_or_archive(): void
    {
        $session = $this->sessionWithAnswers(['meta' => 'X'], FeedbackSessionStatus::AwaitingSignatures);
        $this->signature($session);

        $other = $this->createFeedbackCompany(['name' => 'Empresa B']);
        $rhB = User::factory()->companyAdmin($other->id)->create();

        $this->actingAs($rhB)->getJson(route('client.feedbacks.sessions.version.preview', $session))->assertForbidden();
        $this->actingAs($rhB)->patch(route('client.feedbacks.sessions.version.update', $session), ['confirmed' => true, 'version_id' => 1])->assertForbidden();
        $this->actingAs($rhB)->patch(route('client.feedbacks.sessions.reopen', $session), ['confirmed' => true, 'signed_count' => 0])->assertForbidden();
        $this->actingAs($rhB)->patch(route('client.feedbacks.templates.archive', $this->template))->assertNotFound();
        $this->actingAs($rhB)->patch(route('client.feedbacks.templates.unarchive', $this->template))->assertNotFound();

        $this->assertSame(FeedbackSessionStatus::AwaitingSignatures, $session->fresh()->status);
        $this->assertFalse($this->template->fresh()->isArchived());
    }

    public function test_migration_backfills_version_one_checks_counts_and_down_refuses_with_version_two(): void
    {
        $migration = require database_path('migrations/2026_11_27_200000_create_feedback_template_versions.php');

        // Modelo «antigo», gravado sem versão (como antes da migration), com seção, pergunta e feedback.
        $now = now();
        $legacyId = DB::table('feedback_templates')->insertGetId([
            'company_id' => $this->company->id, 'title' => 'Legado', 'is_default' => false, 'is_active' => false,
            'version' => 3, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sectionId = DB::table('feedback_template_sections')->insertGetId([
            'feedback_template_id' => $legacyId, 'key' => 's', 'title' => 'S', 'section_type' => 'questions',
            'audience' => 'both', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('feedback_template_questions')->insert([
            'feedback_template_section_id' => $sectionId, 'key' => 'q', 'body' => 'P', 'question_type' => 'text',
            'is_required' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sessionId = DB::table('feedback_sessions')->insertGetId([
            'company_id' => $this->company->id, 'feedback_template_id' => $legacyId, 'company_employee_id' => $this->employee->id,
            'leader_user_id' => $this->rh->id, 'status' => 'completed', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $migration->backfill();
        $migration->assertCounts();

        $legacy = FeedbackTemplate::query()->findOrFail($legacyId);
        $this->assertSame(1, $legacy->currentVersion->number);
        $this->assertTrue($legacy->isArchived(), 'Modelo inativo vira arquivado.');
        $this->assertSame($legacy->current_version_id, (int) DB::table('feedback_sessions')->where('id', $sessionId)->value('feedback_template_version_id'));
        $this->assertSame($legacy->current_version_id, (int) DB::table('feedback_template_sections')->where('id', $sectionId)->value('feedback_template_version_id'));
        $this->assertSame('q', $legacy->currentVersion->structure[0]['questions'][0]['key']);
        $this->assertSame(64, strlen((string) $legacy->currentVersion->structure_hash));

        // Feedback sem versão: a conferência falha (a migration desfaz tudo).
        DB::table('feedback_sessions')->where('id', $sessionId)->update(['feedback_template_version_id' => null]);
        try {
            $migration->assertCounts();
            $this->fail('A conferência deveria falhar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('feedbacks sem versão=1', $e->getMessage());
        }
        DB::table('feedback_sessions')->where('id', $sessionId)->update(['feedback_template_version_id' => $legacy->current_version_id]);

        // Só versão 1: reverter é permitido. Com versão 2: recusa.
        $migration->assertReversible();
        FeedbackTemplateVersion::query()->create(['feedback_template_id' => $legacyId, 'number' => 2, 'title' => 'Legado']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('já existem modelos com versão 2 ou maior');
        $migration->down();
    }

    /**
     * Modelo da empresa com uma seção e duas perguntas (meta, clima).
     */
    private function companyTemplate(string $title = 'Modelo da empresa'): FeedbackTemplate
    {
        $template = FeedbackTemplate::query()->create(['company_id' => $this->company->id, 'title' => $title, 'created_by' => $this->rh->id]);
        $section = $template->sections()->create(['key' => 'principal', 'title' => 'Principal', 'section_type' => 'questions', 'audience' => 'both', 'sort_order' => 1]);
        $section->questions()->create(['key' => 'meta', 'body' => 'Qual é a meta?', 'question_type' => 'textarea', 'sort_order' => 1]);
        $section->questions()->create(['key' => 'clima', 'body' => 'Como está o clima?', 'question_type' => 'textarea', 'sort_order' => 2]);

        return $template->fresh();
    }

    /**
     * @param  array<string, string>  $answers  por chave da pergunta
     */
    private function sessionWithAnswers(array $answers, FeedbackSessionStatus $status = FeedbackSessionStatus::Completed): FeedbackSession
    {
        $session = $this->createFeedbackSession($this->company, $this->rh, $this->employee, [
            'feedback_template_id' => $this->template->id,
            'status' => $status,
            'completed_at' => $status === FeedbackSessionStatus::Completed ? now() : null,
        ]);
        $questions = FeedbackSessionStructure::version($session)->sections->flatMap->questions->keyBy('key');
        foreach ($answers as $key => $value) {
            FeedbackSessionAnswer::query()->create([
                'feedback_session_id' => $session->id,
                'feedback_template_question_id' => $questions[$key]->id,
                'value_text' => $value,
            ]);
        }

        return $session->fresh();
    }

    private function signature(FeedbackSession $session, bool $signed = false, string $role = 'employee'): FeedbackSessionSignature
    {
        return FeedbackSessionSignature::query()->create([
            'feedback_session_id' => $session->id, 'role' => $role, 'signer_name' => 'Ana', 'signer_email' => 'ana@t.local',
            'token' => (string) Str::uuid(), 'sent_at' => now(), 'signed_at' => $signed ? now() : null,
        ]);
    }

    /**
     * @param  list<array{key: string|null, body: string}>  $questions
     * @return array<string, mixed>
     */
    private function payload(string $title, array $questions): array
    {
        return [
            'title' => $title,
            'description' => null,
            'sections' => [[
                'key' => 'principal',
                'title' => 'Principal',
                'section_type' => 'questions',
                'audience' => 'both',
                'description' => '',
                'questions' => array_map(fn ($q) => array_filter([
                    'key' => $q['key'],
                    'body' => $q['body'],
                    'question_type' => 'textarea',
                    'is_required' => false,
                    'options' => [],
                ], fn ($v) => $v !== null), $questions),
            ]],
        ];
    }

    private function pdfHtml(FeedbackSession $session): string
    {
        $session = FeedbackSession::query()->with(['employee.position', 'leader', 'templateVersion.sections.questions', 'answers.question', 'signatures'])->findOrFail($session->id);

        return view('reports.feedback-session', [
            'session' => $session,
            'version' => FeedbackSessionStructure::version($session),
            'sections' => FeedbackSessionStructure::sections($session, true),
            'outOfVersionAnswers' => FeedbackSessionStructure::outOfVersionAnswers($session, true),
            'outOfVersionExtras' => FeedbackSessionStructure::outOfVersionExtras($session, true),
            'answersByQuestion' => $session->answers->keyBy('feedback_template_question_id'),
            'sectionExtras' => $session->section_extras ?? [],
        ])->render();
    }
}
