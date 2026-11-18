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
use App\Models\FeedbackRecording;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * Gravação do feedback (fatia 1): envio da transcrição exportada do Plaud com consentimento, texto original
 * criptografado e imutável, arquivo descartado, permissões por perfil, trava em sessão concluída, exclusão
 * (da gravação, do feedback e do modelo) e trilha de auditoria.
 */
class FeedbackRecordingTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    private const SAMPLE = 'Ótimo. Combinamos de revisar os prazos até sexta-feira.';

    private Company $company;

    private User $admin;

    private User $leader;

    private CompanyEmployee $employee;

    private FeedbackSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->company = $this->createFeedbackCompany();
        $this->subscribeCompanyToNr1($this->company);
        $this->admin = User::factory()->companyAdmin($this->company->id)->create();
        $this->leader = User::factory()->companyUser($this->company->id)->create();
        $this->employee = $this->createFeedbackEmployee($this->company, $this->leader, ['name' => 'Larissa']);
        $this->seedFeedbackTemplate();
        $this->session = $this->createFeedbackSession($this->company, $this->leader, $this->employee);
    }

    /** Cópia temporária (o upload apaga o arquivo depois da extração). */
    private function upload(string $fixture = 'plaud-falantes-tempo.txt', ?string $name = null): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'plaud');
        copy(base_path('tests/Fixtures/plaud/'.$fixture), $path);

        return new UploadedFile($path, $name ?? $fixture, 'text/plain', null, true);
    }

    private function store(User $user, array $data, ?FeedbackSession $session = null)
    {
        return $this->actingAs($user)
            ->from(route('client.feedbacks.sessions.show', $session ?? $this->session))
            ->post(route('client.feedbacks.sessions.recording.store', $session ?? $this->session), $data);
    }

    private function uploadAsAdmin(?FeedbackSession $session = null): FeedbackRecording
    {
        $this->store($this->admin, ['file' => $this->upload(), 'consent' => '1'], $session)->assertSessionHasNoErrors();

        return FeedbackRecording::query()->where('feedback_session_id', ($session ?? $this->session)->id)->sole();
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

    // ── Envio ───────────────────────────────────────────────────────────

    public function test_admin_uploads_plaud_txt_text_is_encrypted_file_is_discarded_and_logged(): void
    {
        $file = $this->upload('plaud-falantes-tempo.txt', 'Feedback Larissa.txt');
        $tmpPath = $file->getRealPath();
        $sha = hash_file('sha256', $tmpPath);

        $this->store($this->admin, ['file' => $file, 'consent' => '1'])
            ->assertRedirect(route('client.feedbacks.sessions.show', $this->session))
            ->assertSessionHas('success');

        $recording = FeedbackRecording::query()->sole();
        $this->assertSame('txt', $recording->source_format);
        $this->assertSame($sha, $recording->file_sha256);
        $this->assertSame($this->company->id, (int) $recording->company_id);
        $this->assertSame($this->admin->id, (int) $recording->uploaded_by_user_id);
        $this->assertSame($this->admin->id, (int) $recording->consent_by_user_id);
        $this->assertNotNull($recording->consent_confirmed_at);
        $this->assertStringContainsString(self::SAMPLE, $recording->original_text);
        $this->assertSame('Feedback Larissa.txt', $recording->original_filename);

        // Criptografado no banco: o texto e o nome do arquivo não aparecem em claro.
        $raw = DB::table('feedback_recordings')->where('id', $recording->id)->first();
        $this->assertStringNotContainsString('Larissa', (string) $raw->original_text);
        $this->assertStringNotContainsString('Larissa', (string) $raw->original_filename);
        $this->assertStringContainsString(self::SAMPLE, Crypt::decryptString($raw->original_text));

        // O arquivo enviado não fica em lugar nenhum.
        $this->assertFileDoesNotExist($tmpPath);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $log = FeedbackAuditLog::query()->sole();
        $this->assertSame(FeedbackAuditLog::ACTION_RECORDING_UPLOADED, $log->action);
        $this->assertSame(FeedbackAuditLog::SUBJECT_RECORDING, $log->subject_type);
        $this->assertSame($recording->id, (int) $log->subject_id);
        $this->assertSame($this->session->id, $log->details['feedback_session_id']);
        $this->assertSame($sha, $log->details['sha256']);
        $this->assertStringNotContainsString('Combinamos', json_encode($log->details));
    }

    public function test_srt_upload_stores_timestamped_text(): void
    {
        $this->store($this->admin, ['file' => $this->upload('plaud-falantes.srt'), 'consent' => '1'])->assertSessionHasNoErrors();

        $recording = FeedbackRecording::query()->sole();
        $this->assertSame('srt', $recording->source_format);
        $this->assertStringStartsWith('[00:00:01] Speaker 1: Bom dia, Larissa.', $recording->original_text);
    }

    public function test_file_without_speaker_labels_is_accepted_with_a_warning(): void
    {
        $warning = 'Arquivo sem identificação de quem fala; o relatório ficará menos preciso. Exporte novamente com essa opção ligada.';

        $this->store($this->admin, ['file' => $this->upload('plaud-sem-falantes-tempo.txt'), 'consent' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, $warning));

        $recording = FeedbackRecording::query()->sole();
        $this->assertFalse($recording->has_speaker_labels);
        $this->assertTrue($recording->has_timestamps);

        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.show', $this->session))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recording.has_speaker_labels', false)
                ->where('recording.has_timestamps', true));
    }

    public function test_plaud_docx_with_speakers_has_no_warning(): void
    {
        $file = $this->upload('plaud-falantes-tempo.docx');

        $this->store($this->admin, ['file' => $file, 'consent' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => ! str_contains($message, 'sem identificação de quem fala'));

        $recording = FeedbackRecording::query()->sole();
        $this->assertSame('docx', $recording->source_format);
        $this->assertTrue($recording->has_speaker_labels);
        $this->assertTrue($recording->has_timestamps);
        $this->assertStringContainsString('Speaker 2 00:00:06', $recording->original_text);

        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.edit', $this->session))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('recording.has_speaker_labels', true));
    }

    public function test_consent_is_required(): void
    {
        $this->store($this->admin, ['file' => $this->upload()])
            ->assertSessionHasErrors(['consent' => 'Confirme que o colaborador foi informado e concordou com a gravação e o processamento por IA.']);

        $this->assertSame(0, FeedbackRecording::query()->count());
        $this->assertSame(0, FeedbackAuditLog::query()->count());
    }

    public function test_invalid_files_show_clear_errors(): void
    {
        $this->store($this->admin, ['consent' => '1'])
            ->assertSessionHasErrors(['file' => 'Escolha o arquivo da transcrição exportada do Plaud.']);

        $this->store($this->admin, ['file' => UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg'), 'consent' => '1'])
            ->assertSessionHasErrors(['file' => 'Formato não aceito. Envie TXT, SRT, DOCX ou PDF.']);

        $this->store($this->admin, ['file' => UploadedFile::fake()->create('grande.txt', 10241, 'text/plain'), 'consent' => '1'])
            ->assertSessionHasErrors(['file' => 'O arquivo deve ter no máximo 10 MB.']);

        $this->store($this->admin, ['file' => UploadedFile::fake()->createWithContent('vazio.txt', ''), 'consent' => '1'])
            ->assertSessionHasErrors(['file' => 'O arquivo está vazio.']);

        $this->store($this->admin, ['file' => UploadedFile::fake()->createWithContent('falso.docx', 'texto'), 'consent' => '1'])
            ->assertSessionHasErrors(['file' => 'O arquivo DOCX está corrompido ou não é um documento do Word.']);

        $this->assertSame(0, FeedbackRecording::query()->count());
    }

    public function test_only_one_recording_per_feedback_until_it_is_deleted(): void
    {
        $this->uploadAsAdmin();

        $this->store($this->admin, ['file' => $this->upload(), 'consent' => '1'])
            ->assertSessionHasErrors(['file' => 'Este feedback já tem uma gravação. Exclua a atual para enviar outra.']);

        $this->assertSame(1, FeedbackRecording::query()->count());
    }

    // ── Visualização e permissões ───────────────────────────────────────

    public function test_show_and_edit_expose_metadata_but_never_the_text(): void
    {
        $recording = $this->uploadAsAdmin();

        foreach (['client.feedbacks.sessions.show', 'client.feedbacks.sessions.edit'] as $route) {
            $response = $this->actingAs($this->admin)->get(route($route, $this->session))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('recording.id', $recording->id)
                    ->where('recording.format', 'txt')
                    ->where('recording.sha256', $recording->file_sha256)
                    ->where('recordingAccess.canViewOriginal', true)
                    ->where('recordingAccess.canUpload', true)
                    ->where('recordingAccess.canDelete', true)
                    ->where('recordingAccess.locked', false));

            $this->assertStringNotContainsString('Combinamos', $response->getContent());
            $this->assertStringNotContainsString('Feedback Larissa.txt', $response->getContent());
        }
    }

    public function test_admin_views_original_and_each_view_is_logged(): void
    {
        $recording = $this->uploadAsAdmin();

        $this->actingAs($this->admin)
            ->getJson(route('client.feedbacks.sessions.recording.original', $this->session))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('sha256', $recording->file_sha256)
            ->assertJsonPath('filename', 'plaud-falantes-tempo.txt');

        $this->assertStringContainsString(
            self::SAMPLE,
            $this->actingAs($this->admin)->getJson(route('client.feedbacks.sessions.recording.original', $this->session))->json('text'),
        );

        $views = FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_ORIGINAL_VIEWED)->get();
        $this->assertCount(2, $views);
        $this->assertSame($this->admin->id, (int) $views->first()->user_id);
        $this->assertSame($recording->id, (int) $views->first()->subject_id);
    }

    public function test_leader_uploads_on_own_feedback_but_never_sees_the_original(): void
    {
        $this->store($this->leader, ['file' => $this->upload(), 'consent' => '1'])->assertSessionHasNoErrors();
        $recording = FeedbackRecording::query()->sole();
        $this->assertSame($this->leader->id, (int) $recording->uploaded_by_user_id);

        $this->actingAs($this->leader)->get(route('client.feedbacks.sessions.show', $this->session))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recording.id', $recording->id)
                ->where('recordingAccess.canViewOriginal', false)
                ->where('recordingAccess.canDelete', false));

        $this->actingAs($this->leader)
            ->getJson(route('client.feedbacks.sessions.recording.original', $this->session))
            ->assertForbidden();

        // Sem a permissão de excluir do módulo, o líder não exclui.
        $this->actingAs($this->leader)
            ->delete(route('client.feedbacks.sessions.recording.destroy', $this->session), ['confirmed' => true])
            ->assertForbidden();
        $this->assertNotSoftDeleted($recording);
        $this->assertSame(0, FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_ORIGINAL_VIEWED)->count());
    }

    public function test_leader_cannot_touch_another_leaders_feedback(): void
    {
        $otherLeader = User::factory()->companyUser($this->company->id)->create();
        $this->uploadAsAdmin();

        $this->store($otherLeader, ['file' => $this->upload(), 'consent' => '1'])->assertForbidden();
        $this->actingAs($otherLeader)->get(route('client.feedbacks.sessions.recording.original', $this->session))->assertForbidden();
        $this->actingAs($otherLeader)
            ->delete(route('client.feedbacks.sessions.recording.destroy', $this->session), ['confirmed' => true])
            ->assertForbidden();

        $this->assertSame(1, FeedbackRecording::query()->count());
    }

    public function test_other_company_cannot_reach_the_recording(): void
    {
        $this->uploadAsAdmin();
        $otherCompany = $this->createFeedbackCompany(['name' => 'Outra empresa']);
        $this->subscribeCompanyToNr1($otherCompany);
        $otherAdmin = User::factory()->companyAdmin($otherCompany->id)->create();

        $this->store($otherAdmin, ['file' => $this->upload(), 'consent' => '1'])->assertForbidden();
        $this->actingAs($otherAdmin)->get(route('client.feedbacks.sessions.recording.original', $this->session))->assertForbidden();
        $this->actingAs($otherAdmin)
            ->delete(route('client.feedbacks.sessions.recording.destroy', $this->session), ['confirmed' => true])
            ->assertForbidden();

        $this->assertSame(1, FeedbackRecording::query()->count());
        $this->assertSame(0, FeedbackAuditLog::query()->where('action', '!=', FeedbackAuditLog::ACTION_RECORDING_UPLOADED)->count());
    }

    public function test_talents_admin_with_selected_company_follows_the_permission_matrix(): void
    {
        $as = fn (User $user) => $this->actingAs($user)->withSession([FeedbackCompanyContext::SESSION_KEY => $this->company->id]);

        $viewer = $this->talentsAdmin([PermissionAction::View]);
        $as($viewer)->post(route('admin.feedbacks.sessions.recording.store', $this->session), ['file' => $this->upload(), 'consent' => '1'])
            ->assertForbidden();

        $editor = $this->talentsAdmin([PermissionAction::View, PermissionAction::Create]);
        $as($editor)->post(route('admin.feedbacks.sessions.recording.store', $this->session), ['file' => $this->upload(), 'consent' => '1'])
            ->assertSessionHasNoErrors();
        $recording = FeedbackRecording::query()->sole();

        $as($viewer)->get(route('admin.feedbacks.sessions.show', $this->session))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recording.id', $recording->id)
                ->where('recordingAccess.canViewOriginal', true)
                ->where('recordingAccess.canUpload', false)
                ->where('recordingAccess.canDelete', false));
        $as($viewer)->getJson(route('admin.feedbacks.sessions.recording.original', $this->session))->assertOk();
        $as($viewer)->delete(route('admin.feedbacks.sessions.recording.destroy', $this->session), ['confirmed' => true])->assertForbidden();

        // Empresa selecionada diferente da do feedback: recusado.
        $otherCompany = $this->createFeedbackCompany(['name' => 'Outra empresa']);
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->actingAs($owner)->withSession([FeedbackCompanyContext::SESSION_KEY => $otherCompany->id])
            ->getJson(route('admin.feedbacks.sessions.recording.original', $this->session))
            ->assertForbidden();

        $as($owner)->delete(route('admin.feedbacks.sessions.recording.destroy', $this->session), ['confirmed' => true])
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted($recording);
    }

    // ── Trava e exclusão ────────────────────────────────────────────────

    public function test_completed_or_cancelled_feedback_keeps_the_recording_locked(): void
    {
        $recording = $this->uploadAsAdmin();

        foreach ([FeedbackSessionStatus::Completed, FeedbackSessionStatus::Cancelled] as $status) {
            $this->session->update(['status' => $status]);

            $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.show', $this->session))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('recordingAccess.locked', true)
                    ->where('recordingAccess.canUpload', false)
                    ->where('recordingAccess.canDelete', false)
                    ->where('recordingAccess.canViewOriginal', true));

            $this->actingAs($this->admin)
                ->delete(route('client.feedbacks.sessions.recording.destroy', $this->session), ['confirmed' => true])
                ->assertForbidden();
        }
        $this->assertNotSoftDeleted($recording);

        $completed = $this->createFeedbackSession($this->company, $this->leader, $this->employee, ['status' => FeedbackSessionStatus::Completed]);
        $this->store($this->admin, ['file' => $this->upload(), 'consent' => '1'], $completed)->assertForbidden();
        $this->assertSame(0, $completed->recordings()->count());
    }

    public function test_deleting_the_recording_requires_confirmation_is_logged_and_allows_a_new_upload(): void
    {
        $recording = $this->uploadAsAdmin();

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.sessions.recording.destroy', $this->session))
            ->assertSessionHasErrors(['confirmed' => 'Confirme que a gravação será excluída.']);
        $this->assertNotSoftDeleted($recording);

        $this->actingAs($this->admin)
            ->from(route('client.feedbacks.sessions.show', $this->session))
            ->delete(route('client.feedbacks.sessions.recording.destroy', $this->session), ['confirmed' => true])
            ->assertRedirect(route('client.feedbacks.sessions.show', $this->session))
            ->assertSessionHas('success', 'Gravação excluída.');

        $this->assertSoftDeleted($recording);
        $log = FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_DELETED)->sole();
        $this->assertSame($recording->id, (int) $log->subject_id);
        $this->assertSame($this->admin->id, (int) $log->user_id);

        $this->actingAs($this->admin)->get(route('client.feedbacks.sessions.show', $this->session))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('recording', null));
        $this->actingAs($this->admin)->getJson(route('client.feedbacks.sessions.recording.original', $this->session))->assertNotFound();

        $this->store($this->admin, ['file' => $this->upload(), 'consent' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, FeedbackRecording::query()->count());
        $this->assertSame(2, FeedbackRecording::withTrashed()->count());
    }

    public function test_deleting_the_feedback_deletes_its_recording(): void
    {
        $recording = $this->uploadAsAdmin();

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.sessions.destroy', $this->session), ['confirmed' => true])
            ->assertRedirect(route('client.feedbacks.sessions.index'));

        $this->assertSoftDeleted($recording);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $log = FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_SESSION_DELETED)->sole();
        $this->assertSame(1, $log->details['recordings_count']);

        $this->actingAs($this->admin)->getJson(route('client.feedbacks.sessions.recording.original', $this->session))->assertNotFound();
    }

    public function test_deleting_the_template_deletes_the_recordings_of_its_feedbacks(): void
    {
        $template = FeedbackTemplate::query()->create(['company_id' => $this->company->id, 'title' => 'Modelo próprio', 'is_active' => true]);
        $section = $template->sections()->create([
            'key' => 's', 'title' => 'Seção', 'section_type' => 'questions', 'audience' => 'both', 'sort_order' => 1,
        ]);
        $section->questions()->create(['key' => 'q', 'body' => 'Pergunta', 'question_type' => 'text', 'sort_order' => 1]);

        $session = $this->createFeedbackSession($this->company, $this->leader, $this->employee, ['feedback_template_id' => $template->id]);
        $recording = $this->uploadAsAdmin($session);
        $untouched = $this->uploadAsAdmin();

        $this->actingAs($this->admin)
            ->delete(route('client.feedbacks.templates.destroy', $template), ['confirmed' => true, 'sessions_count' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($recording);
        $this->assertNotSoftDeleted($untouched);
        $log = FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_TEMPLATE_DELETED)->sole();
        $this->assertSame(1, $log->details['recordings_count']);
    }
}
