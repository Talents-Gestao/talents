<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\AiSetting;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackRecording;
use App\Models\FeedbackTemplateSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * «Confirmar falantes»: o detalhe do feedback traz a sugestão sem IA (com o nível de confiança), o número de falas e
 * as frases representativas de cada falante; a regra não muda (mapa obrigatório antes de processar, na trilha).
 * Também garante que as seções do modelo saem uma vez cada (sem duplicação na página).
 */
class FeedbackSpeakerConfirmTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    public function test_session_page_brings_suggestion_counts_and_samples_and_sections_once(): void
    {
        $this->app['env'] = 'testing';
        $this->withoutVite();
        Queue::fake();
        $company = $this->createFeedbackCompany();
        $admin = User::factory()->companyAdmin($company->id)->create();
        $leader = User::factory()->companyUser($company->id)->create(['name' => 'Marcos Lima']);
        $employee = $this->createFeedbackEmployee($company, $leader, ['name' => 'Larissa Souza']);
        $this->seedFeedbackTemplate();
        $session = $this->createFeedbackSession($company, $leader, $employee, ['employee_name' => 'Larissa Souza']);
        AiSetting::query()->create([
            'provider' => 'anthropic', 'model' => 'claude-sonnet-4-5', 'api_key' => 'chave-falsa', 'is_enabled' => true,
            'max_tokens' => 500, 'temperature' => 0.3, 'feedback_approved_provider' => 'anthropic', 'feedback_approved_at' => now(),
        ]);

        // Fixture: «Speaker 1: Bom dia, Larissa…» → Speaker 1 é o líder.
        $path = (string) tempnam(sys_get_temp_dir(), 'plaud');
        copy(base_path('tests/Fixtures/plaud/plaud-falantes-tempo.txt'), $path);
        $this->actingAs($admin)->post(route('client.feedbacks.sessions.recording.store', $session), [
            'file' => new UploadedFile($path, 'plaud.txt', 'text/plain', null, true),
            'consent' => '1',
        ])->assertSessionHasNoErrors();

        $sections = FeedbackTemplateSection::query()->where('feedback_template_id', $session->feedback_template_id)->count();

        $this->actingAs($admin)->get(route('client.feedbacks.sessions.show', $session))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recordingAi.speaker_suggestion.map', ['Speaker 1' => 'leader', 'Speaker 2' => 'employee'])
                // O líder cita «Larissa» e nada contradiz: confiança alta.
                ->where('recordingAi.speaker_suggestion.confidence', 'high')
                ->where('recordingAi.speakers.0.count', 2)
                ->missing('recordingAi.speakers.0.samples') // as frases não vão no HTML da página
                ->has('session.template.sections', $sections)
                ->where('session.template.sections', fn ($list) => collect($list)->pluck('id')->unique()->count() === $sections));

        // «Ver trechos» sob demanda: número de falas e até 3 frases mais longas, sem cache, com trilha.
        $this->actingAs($admin)->getJson(route('client.feedbacks.sessions.recording.samples', $session))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('speakers.0.label', 'Speaker 1')
            ->assertJsonPath('speakers.0.count', 2)
            ->assertJsonPath('speakers.0.samples.1', 'Ótimo. Combinamos de revisar os prazos até sexta-feira.')
            ->assertJsonCount(2, 'speakers.1.samples');
        $this->assertSame(1, FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_SAMPLES_VIEWED)->count());

        $otherLeader = User::factory()->companyUser($company->id)->create();
        $this->actingAs($otherLeader)->getJson(route('client.feedbacks.sessions.recording.samples', $session))->assertForbidden();
        $other = $this->createFeedbackCompany(['name' => 'Outra']);
        $this->actingAs(User::factory()->companyAdmin($other->id)->create())->getJson(route('client.feedbacks.sessions.recording.samples', $session))->assertForbidden();
        $this->actingAs($leader)->getJson(route('client.feedbacks.sessions.recording.samples', $session))->assertOk();

        // A regra não muda: sem confirmar, não processa; confirmar fica na trilha.
        $this->actingAs($admin)->post(route('client.feedbacks.sessions.recording.process', $session))
            ->assertSessionHasErrors(['ai' => 'Confirme quem é o líder e quem é o colaborador antes de processar.']);
        $this->actingAs($admin)->post(route('client.feedbacks.sessions.recording.speakers', $session), [
            'speakers' => ['Speaker 1' => 'leader', 'Speaker 2' => 'employee'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, FeedbackAuditLog::query()->where('action', FeedbackAuditLog::ACTION_RECORDING_SPEAKERS_MAPPED)->count());
        $this->assertSame(['Speaker 1' => 'leader', 'Speaker 2' => 'employee'], FeedbackRecording::query()->sole()->speaker_map);
    }
}
