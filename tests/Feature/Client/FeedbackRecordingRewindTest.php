<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\FeedbackSessionStatus;
use App\Enums\PermissionAction;
use App\Jobs\ProcessFeedbackRecordingJob;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\FeedbackRecordingReport;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionSignature;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackRecordingAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * Voltar etapas da barra «Gravação» (POST gravacao/voltar): cada volta permitida, bloqueios por assinatura,
 * processamento, permissão e trava da IA, versão anterior do relatório preservada e registro na trilha.
 */
class FeedbackRecordingRewindTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    private const SPEAKERS = ['Speaker 1' => 'leader', 'Speaker 2' => 'employee'];

    private Company $company;

    private User $admin;

    private User $leader;

    private CompanyEmployee $employee;

    private FeedbackSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'testing';
        $this->withoutVite();
        Storage::fake('local');
        Queue::fake();
        $this->company = $this->createFeedbackCompany();
        $this->admin = User::factory()->companyAdmin($this->company->id)->create();
        $this->leader = User::factory()->companyUser($this->company->id)->create();
        $this->employee = $this->createFeedbackEmployee($this->company, $this->leader, ['name' => 'Larissa']);
        $this->seedFeedbackTemplate();
        $this->session = $this->createFeedbackSession($this->company, $this->leader, $this->employee);
    }

    // ── Apoio ───────────────────────────────────────────────────────────

    private function aiSetting(?string $approved = 'anthropic'): void
    {
        AiSetting::query()->create([
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-5',
            'api_key' => 'chave-falsa',
            'is_enabled' => true,
            'max_tokens' => 500,
            'temperature' => 0.3,
            'feedback_approved_provider' => $approved,
            'feedback_approved_at' => $approved ? now() : null,
        ]);
    }

    private function uploadRecording(): FeedbackRecording
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'plaud');
        file_put_contents($path, (string) file_get_contents(base_path('tests/Fixtures/plaud/plaud-falantes-tempo.txt')));

        $this->actingAs($this->admin)
            ->from(route('client.feedbacks.sessions.show', $this->session))
            ->post(route('client.feedbacks.sessions.recording.store', $this->session), [
                'file' => new UploadedFile($path, 'plaud.txt', 'text/plain', null, true),
                'consent' => '1',
            ])->assertSessionHasNoErrors();

        return FeedbackRecording::query()->where('feedback_session_id', $this->session->id)->sole();
    }

    /** Gravação com falantes confirmados, transcrição limpa e relatório pronto (estado «Revisar relatório»). */
    private function readyRecording(): FeedbackRecording
    {
        $recording = $this->uploadRecording();
        $recording->forceFill([
            'speaker_map' => self::SPEAKERS,
            'speakers_mapped_by_user_id' => $this->leader->id,
            'speakers_mapped_at' => now(),
            'processing_status' => FeedbackRecording::STATUS_READY,
            'processing_mode' => FeedbackRecording::MODE_FULL,
            'processed_at' => now(),
            'clean_text' => "[00:00:01] Líder: Bom dia.\n\n[00:00:05] Colaborador: Bom dia.",
            'clean_generated_at' => now(),
            'clean_revision' => 1,
        ])->save();

        FeedbackRecordingReport::query()->create([
            'company_id' => $this->company->id,
            'feedback_session_id' => $this->session->id,
            'feedback_recording_id' => $recording->id,
            'report_json' => ['resumo' => 'Relatório anterior.'],
            'prompt_version' => 'teste',
            'schema_version' => 1,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-5',
            'clean_revision' => 1,
            'generated_by_user_id' => $this->leader->id,
            'is_current' => true,
        ]);

        return $recording->refresh();
    }

    private function rewind(User $user, string $step, array $extra = [])
    {
        return $this->actingAs($user)
            ->from(route('client.feedbacks.sessions.show', $this->session))
            ->post(route('client.feedbacks.sessions.recording.rewind', $this->session), ['step' => $step, 'confirmed' => true] + $extra);
    }

    private function rewindLog(): ?FeedbackAuditLog
    {
        return FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_STEP_REVERTED)->latest('id')->first();
    }

    // ── Voltas permitidas ───────────────────────────────────────────────

    public function test_back_to_speakers_archives_the_report_and_returns_to_process(): void
    {
        $this->aiSetting();
        $recording = $this->readyRecording();
        $old = FeedbackRecordingReport::query()->sole();

        $this->rewind($this->leader, 'speakers', ['speakers' => ['Speaker 1' => 'employee', 'Speaker 2' => 'leader']])
            ->assertSessionHasNoErrors();

        $recording->refresh();
        $this->assertSame(FeedbackRecording::STATUS_UPLOADED, $recording->processing_status, 'volta para «Processar»');
        $this->assertFalse($recording->hasCleanText(), 'a limpeza feita com os papéis anteriores é refeita');
        $this->assertSame(['Speaker 1' => 'employee', 'Speaker 2' => 'leader'], $recording->speaker_map);
        $this->assertNull($recording->currentReport());

        $old->refresh();
        $this->assertFalse($old->is_current, 'o relatório anterior vira versão antiga');
        $this->assertSame(['resumo' => 'Relatório anterior.'], $old->safeReport(), 'e não é apagado');
        $this->assertSame(1, FeedbackRecordingReport::query()->count());

        $log = $this->rewindLog();
        $this->assertSame($this->leader->id, $log->user_id);
        $this->assertSame(['from' => 'review', 'to' => 'speakers', 'reports_archived' => 1], array_intersect_key($log->details, array_flip(['from', 'to', 'reports_archived'])));

        // Processar de novo funciona e gera a versão 2 sem apagar a 1.
        $this->actingAs($this->leader)->from(route('client.feedbacks.sessions.show', $this->session))
            ->post(route('client.feedbacks.sessions.recording.process', $this->session))->assertSessionHasNoErrors();
        Queue::assertPushed(ProcessFeedbackRecordingJob::class);
    }

    public function test_back_to_speakers_with_an_invalid_mapping_changes_nothing(): void
    {
        $recording = $this->readyRecording();

        $this->rewind($this->leader, 'speakers', ['speakers' => ['Speaker 1' => 'leader', 'Speaker 2' => 'leader']])
            ->assertSessionHasErrors('speakers');

        $recording->refresh();
        $this->assertSame(FeedbackRecording::STATUS_READY, $recording->processing_status);
        $this->assertTrue($recording->hasCleanText());
        $this->assertNotNull($recording->currentReport());
        $this->assertNull($this->rewindLog());
    }

    public function test_back_to_upload_deletes_like_delete_recording_and_asks_consent_again(): void
    {
        $recording = $this->readyRecording();

        $this->rewind($this->admin, 'upload')->assertSessionHasNoErrors();

        $this->assertSoftDeleted($recording);
        $this->assertSame(0, FeedbackRecordingReport::query()->count(), 'relatórios vão junto, como em «Excluir gravação»');
        $this->assertTrue(FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_DELETED)->exists());
        $this->assertSame(['review', 'upload'], [$this->rewindLog()->details['from'], $this->rewindLog()->details['to']]);

        // Novo envio exige o consentimento de novo.
        $this->actingAs($this->admin)->from(route('client.feedbacks.sessions.show', $this->session))
            ->post(route('client.feedbacks.sessions.recording.store', $this->session), [
                'file' => UploadedFile::fake()->createWithContent('plaud.txt', "Speaker 1: oi\nSpeaker 2: oi"),
            ])->assertSessionHasErrors('consent');
        $this->uploadRecording();
    }

    public function test_back_to_process_regenerates_the_report_through_the_usual_action(): void
    {
        $this->aiSetting();
        $recording = $this->readyRecording();

        $this->rewind($this->leader, 'process')->assertSessionHasNoErrors();

        Queue::assertPushed(ProcessFeedbackRecordingJob::class);
        $recording->refresh();
        $this->assertSame(FeedbackRecording::STATUS_PROCESSING, $recording->processing_status);
        $this->assertSame(FeedbackRecording::MODE_REPORT, $recording->processing_mode);
        $this->assertTrue(FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_PROCESSING_REQUESTED)->exists());
        $this->assertSame(['review', 'process'], [$this->rewindLog()->details['from'], $this->rewindLog()->details['to']]);
        $this->assertTrue(FeedbackRecordingReport::query()->sole()->is_current, 'o relatório atual fica até o novo ficar pronto');
    }

    // ── Bloqueios ───────────────────────────────────────────────────────

    public function test_back_to_process_respects_the_ai_gate_and_the_monthly_limit(): void
    {
        $this->aiSetting(approved: null);
        $recording = $this->readyRecording();

        $this->rewind($this->leader, 'process')->assertSessionHasErrors('ai');
        Queue::assertNothingPushed();
        $this->assertNull($this->rewindLog());

        AiSetting::query()->update(['feedback_approved_provider' => 'anthropic', 'feedback_approved_at' => now()]);
        $this->company->update(['feedback_ai_monthly_limit_cents' => 1000]);
        AiUsageLog::query()->create([
            'company_id' => $this->company->id, 'purpose' => AiUsageLog::PURPOSE_FEEDBACK_REPORT, 'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-5', 'estimated_cost_cents' => 1000, 'success' => true,
        ]);

        $this->rewind($this->leader, 'process')->assertSessionHasErrors('ai');
        Queue::assertNothingPushed();
        $this->assertSame(FeedbackRecording::STATUS_READY, $recording->refresh()->processing_status);
    }

    public function test_no_rewind_while_processing(): void
    {
        $recording = $this->readyRecording();
        $recording->forceFill(['processing_status' => FeedbackRecording::STATUS_PROCESSING])->save();

        foreach (['upload', 'speakers', 'process'] as $step) {
            $this->rewind($this->admin, $step, ['speakers' => self::SPEAKERS])->assertSessionHasErrors('step');
        }

        $this->assertNotSoftDeleted($recording);
        $this->assertSame(FeedbackRecording::STATUS_PROCESSING, $recording->refresh()->processing_status);
        $this->assertNull($this->rewindLog());
    }

    public function test_no_rewind_once_signatures_were_requested_or_the_feedback_is_completed(): void
    {
        $recording = $this->readyRecording();

        // Assinatura solicitada (mesmo antes de mudar o status).
        $signature = FeedbackSessionSignature::query()->create([
            'feedback_session_id' => $this->session->id,
            'role' => 'employee',
            'signer_name' => 'Larissa',
            'signer_email' => 'larissa@example.test',
            'token' => (string) Str::uuid(),
        ]);
        foreach (['upload', 'speakers', 'process'] as $step) {
            $this->rewind($this->admin, $step, ['speakers' => self::SPEAKERS])->assertForbidden();
        }
        $signature->delete();

        $this->session->update(['status' => FeedbackSessionStatus::AwaitingSignatures]);
        $this->rewind($this->admin, 'upload')->assertForbidden();

        $this->session->update(['status' => FeedbackSessionStatus::Completed]);
        $this->rewind($this->admin, 'upload')->assertForbidden();

        $this->assertNotSoftDeleted($recording);
        $this->assertNull($this->rewindLog());
    }

    public function test_rewind_follows_the_permissions_of_the_reused_actions(): void
    {
        $this->aiSetting();
        $recording = $this->readyRecording();

        // Outro líder da empresa não vê a sessão.
        $otherLeader = User::factory()->companyUser($this->company->id)->create();
        $this->rewind($otherLeader, 'upload')->assertForbidden();

        // Talents: só «ver» não volta; «criar» volta falantes/processar, mas «Enviar» exige «excluir».
        $as = fn (User $user) => $this->actingAs($user)->withSession([FeedbackCompanyContext::SESSION_KEY => $this->company->id]);
        $url = route('admin.feedbacks.sessions.recording.rewind', $this->session);

        $viewer = $this->talentsAdmin([PermissionAction::View]);
        $as($viewer)->post($url, ['step' => 'process', 'confirmed' => true])->assertForbidden();

        $creator = $this->talentsAdmin([PermissionAction::View, PermissionAction::Create]);
        $as($creator)->post($url, ['step' => 'upload', 'confirmed' => true])->assertForbidden();
        $this->assertNotSoftDeleted($recording);

        $as($creator)->post($url, ['step' => 'process', 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame($creator->id, $this->rewindLog()->user_id);
    }

    public function test_rewind_requires_explicit_confirmation_and_a_valid_step(): void
    {
        $recording = $this->readyRecording();

        $this->actingAs($this->admin)->from(route('client.feedbacks.sessions.show', $this->session))
            ->post(route('client.feedbacks.sessions.recording.rewind', $this->session), ['step' => 'upload'])
            ->assertSessionHasErrors('confirmed');
        $this->rewind($this->admin, 'review')->assertSessionHasErrors('step');

        $this->assertNotSoftDeleted($recording);
    }

    public function test_show_page_sends_the_rewind_block_reason(): void
    {
        $this->readyRecording();

        $this->actingAs($this->leader)->get(route('client.feedbacks.sessions.show', $this->session))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('recordingAccess.rewindBlockedReason', null));

        $this->session->update(['status' => FeedbackSessionStatus::AwaitingSignatures]);
        $this->actingAs($this->leader)->get(route('client.feedbacks.sessions.show', $this->session))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('recordingAccess.rewindBlockedReason', FeedbackRecordingAccess::REWIND_SIGNATURE_MESSAGE));
    }

    private function talentsAdmin(array $actions): User
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        app(SyncAdminUserPermissions::class)->execute($user->talentsWorkspace(), array_map(
            fn (PermissionAction $a) => ['module' => AdminPermissionModule::Feedbacks->value, 'action' => $a->value],
            $actions,
        ));

        return $user;
    }
}
