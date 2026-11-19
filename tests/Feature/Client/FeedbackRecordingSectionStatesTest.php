<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Jobs\ProcessFeedbackRecordingJob;
use App\Models\AiSetting;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\FeedbackSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * SeÃ§Ã£o Â«GravaÃ§Ã£o e relatÃ³rioÂ», um estado por vez (a tela escolhe a etapa em recordingState, no JS): cada teste
 * confere que o servidor manda o necessÃ¡rio para o estado e que a regra nÃ£o mudou (mapa obrigatÃ³rio, falantes sÃ³
 * antes de processar, original fora da pÃ¡gina e aberto sÃ³ sob demanda, com registro).
 */
class FeedbackRecordingSectionStatesTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    /** Segunda fala do Speaker 1 na fixture: nÃ£o pode aparecer no HTML antes de abrir o original. */
    private const PRIVATE_LINE = 'Combinamos de revisar os prazos';

    private User $admin;

    private User $leader;

    private FeedbackSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'testing';
        $this->withoutVite();
        Queue::fake();
        Http::fake();
        $company = $this->createFeedbackCompany();
        $this->admin = User::factory()->companyAdmin($company->id)->create();
        $this->leader = User::factory()->companyUser($company->id)->create(['name' => 'Rafael Mendes']);
        $employee = $this->createFeedbackEmployee($company, $this->leader, ['name' => 'Larissa Souza']);
        $this->seedFeedbackTemplate();
        $this->session = $this->createFeedbackSession($company, $this->leader, $employee, ['employee_name' => 'Larissa Souza']);
    }

    private function gate(?string $approved): void
    {
        AiSetting::query()->create([
            'provider' => 'anthropic', 'model' => 'claude-sonnet-4-5', 'api_key' => 'chave-falsa', 'is_enabled' => true,
            'max_tokens' => 500, 'temperature' => 0.3,
            'feedback_approved_provider' => $approved, 'feedback_approved_at' => $approved ? now() : null,
        ]);
    }

    private function upload(): FeedbackRecording
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'plaud');
        copy(base_path('tests/Fixtures/plaud/plaud-falantes-tempo.txt'), $path);
        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.recording.store', $this->session), [
            'file' => new UploadedFile($path, 'plaud.txt', 'text/plain', null, true),
            'consent' => '1',
        ])->assertSessionHasNoErrors();

        return FeedbackRecording::query()->sole();
    }

    private function mapSpeakers(): void
    {
        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.recording.speakers', $this->session), [
            'speakers' => ['Speaker 1' => 'leader', 'Speaker 2' => 'employee'],
        ])->assertSessionHasNoErrors();
    }

    private function show(?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->get(route('client.feedbacks.sessions.show', $this->session))->assertOk();
    }

    public function test_sem_gravacao_mostra_so_o_envio(): void
    {
        $this->gate('anthropic');

        $this->show()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recording', null)
            ->where('recordingAi', null)
            ->where('recordingAccess.canUpload', true));
    }

    public function test_confirmar_falantes_traz_a_sugestao_sem_o_texto_da_conversa(): void
    {
        $this->gate('anthropic');
        $this->upload();

        $response = $this->show()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recordingAi.status', FeedbackRecording::STATUS_UPLOADED)
            ->where('recordingAi.speakers_mapped', false)
            ->has('recordingAi.speakers', 2)
            ->where('recordingAi.speaker_suggestion.confidence', 'high')
            ->where('recording.has_speaker_labels', true)
            ->missing('recordingAi.original'));
        $this->assertStringNotContainsString(self::PRIVATE_LINE, $response->getContent());
    }

    public function test_falantes_confirmados_trazem_os_papeis_e_podem_ser_alterados_antes_de_processar(): void
    {
        $this->gate(null);
        $this->upload();
        $this->mapSpeakers();

        $this->show()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recordingAi.speakers_mapped', true)
            ->where('recordingAi.speakers.0.role', 'leader')
            ->where('recordingAi.speakers.1.role', 'employee')
            ->where('recordingAi.speakers_mapped_by', $this->admin->name)
            ->where('recordingAi.clean', null));

        // Â«AlterarÂ»: refazer o mapa continua permitido e registrado enquanto nÃ£o processa.
        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.recording.speakers', $this->session), [
            'speakers' => ['Speaker 1' => 'employee', 'Speaker 2' => 'leader'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_SPEAKERS_MAPPED)->count());
    }

    public function test_aguardando_liberacao_da_ia_guarda_a_transcricao_e_abre_o_original_so_sob_demanda(): void
    {
        $this->gate(null);
        $this->upload();
        $this->mapSpeakers();

        $response = $this->show()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recordingAi.gate.allowed', false)
            ->where('recordingAi.status', FeedbackRecording::STATUS_UPLOADED)
            ->where('recordingAi.report', null)
            ->where('recordingAi.clean', null)
            ->where('recordingAccess.canViewOriginal', true));
        $this->assertStringNotContainsString(self::PRIVATE_LINE, $response->getContent());

        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.recording.process', $this->session))->assertSessionHasErrors('ai');
        Queue::assertNothingPushed();

        // Â«Ver transcriÃ§Ã£oÂ»: o original vem sob demanda e cada abertura fica registrada; o lÃ­der nÃ£o vÃª.
        $this->actingAs($this->admin)->getJson(route('client.feedbacks.sessions.recording.original', $this->session))
            ->assertOk()->assertJsonFragment(['filename' => 'plaud.txt']);
        $this->assertSame(1, FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_ORIGINAL_VIEWED)->count());
        $this->actingAs($this->leader)->getJson(route('client.feedbacks.sessions.recording.original', $this->session))->assertForbidden();
        $this->show($this->leader)->assertInertia(fn (AssertableInertia $page) => $page->where('recordingAccess.canViewOriginal', false));
    }

    public function test_processando_mantem_o_resumo_dos_falantes_e_nao_deixa_alterar(): void
    {
        $this->gate('anthropic');
        $this->upload();
        $this->mapSpeakers();
        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.recording.process', $this->session))->assertSessionHasNoErrors();
        Queue::assertPushed(ProcessFeedbackRecordingJob::class);

        $this->show()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recordingAi.status', FeedbackRecording::STATUS_PROCESSING)
            ->where('recordingAi.speakers_mapped', true)
            ->where('recordingAi.speakers.0.role', 'leader'));

        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.recording.speakers', $this->session), [
            'speakers' => ['Speaker 1' => 'employee', 'Speaker 2' => 'leader'],
        ])->assertSessionHasErrors('speakers');
    }

    public function test_relatorio_pronto_traz_relatorio_e_transcricao_limpa_e_o_original_so_para_administrador(): void
    {
        $this->gate('demo');
        $this->upload();
        $this->mapSpeakers();
        $this->app['env'] = 'local';
        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.recording.process', $this->session))->assertSessionHasNoErrors();
        $job = collect(Queue::pushedJobs()[ProcessFeedbackRecordingJob::class])->last()['job'];
        app()->call([$job, 'handle']);
        Http::assertNothingSent();

        $this->show()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recordingAi.status', FeedbackRecording::STATUS_READY)
            ->whereNot('recordingAi.report', null)
            ->whereNot('recordingAi.clean', null)
            ->where('recordingAccess.canViewOriginal', true)
            ->missing('recordingAi.original'));
        $this->show($this->leader)->assertInertia(fn (AssertableInertia $page) => $page
            ->whereNot('recordingAi.report', null)
            ->where('recordingAccess.canViewOriginal', false));
    }

    public function test_erro_traz_a_mensagem_e_permite_tentar_de_novo(): void
    {
        $this->gate('anthropic');
        $recording = $this->upload();
        $this->mapSpeakers();
        $recording->forceFill([
            'processing_status' => FeedbackRecording::STATUS_FAILED,
            'processing_error' => 'O provedor de IA nÃ£o respondeu. Tente de novo em alguns minutos.',
        ])->save();

        $this->show()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recordingAi.status', FeedbackRecording::STATUS_FAILED)
            ->where('recordingAi.error', 'O provedor de IA nÃ£o respondeu. Tente de novo em alguns minutos.')
            ->where('recordingAi.gate.allowed', true));

        $this->actingAs($this->admin)->post(route('client.feedbacks.sessions.recording.retry', $this->session))->assertSessionHasNoErrors();
        Queue::assertPushed(ProcessFeedbackRecordingJob::class);
    }
}
