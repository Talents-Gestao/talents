<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\InterviewStatus;
use App\Enums\InterviewTranscriptSource;
use App\Jobs\ProcessInterviewAudioJob;
use App\Models\Interview;
use App\Services\Interview\AudioChunkerService;
use App\Services\Interview\InterviewAnswerExtractor;
use App\Services\Interview\OpenAiWhisperService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Mockery;

class InterviewSourcesTest extends InterviewTestCase
{
    public function test_text_source_with_ai_queues_extraction_and_skips_whisper(): void
    {
        Queue::fake();
        $this->enableAi();
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate, $application, $vacancy] = $this->candidateWithApplication();

        $this->actingAs($owner)
            ->post(route('admin.entrevistas.store'), [
                'questionnaire_id' => $questionnaire->id,
                'talent_candidate_id' => $candidate->id,
                'hiring_application_id' => $application->id,
                'transcript_source' => 'manual_text',
                'transcript_text' => "Entrevistadora: fale de você.\r\nAna: trabalho com RH há seis anos.",
                'confirm_candidate_id' => (string) $candidate->id,
                'confirm_application_id' => (string) $application->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $interview = Interview::query()->firstOrFail();
        $this->assertSame(InterviewStatus::Queued, $interview->status);
        $this->assertSame(InterviewTranscriptSource::ManualText, $interview->transcript_source);
        $this->assertSame("Entrevistadora: fale de você.\nAna: trabalho com RH há seis anos.", $interview->transcript_text);
        $this->assertNull($interview->audio_path);
        // Nome, vaga e empresa vêm do vínculo.
        $this->assertSame('Ana Souza', $interview->candidate_name);
        $this->assertSame($vacancy->title, $interview->position_title);
        $this->assertSame($vacancy->company_id, $interview->company_id);
        Queue::assertPushed(ProcessInterviewAudioJob::class);

        // O job de uma fonte de texto nunca chama o ffmpeg nem o Whisper.
        $whisper = Mockery::mock(OpenAiWhisperService::class);
        $whisper->shouldNotReceive('transcribeChunks');
        $chunker = Mockery::mock(AudioChunkerService::class);
        $chunker->shouldNotReceive('prepareChunks');
        $extractor = Mockery::mock(InterviewAnswerExtractor::class);
        $extractor->shouldReceive('extract')->once()->andReturn([]);

        (new ProcessInterviewAudioJob($interview->id))->handle($chunker, $whisper, $extractor);

        $this->assertSame(InterviewStatus::Completed, $interview->fresh()->status);
    }

    public function test_text_source_without_ai_is_ready_for_manual_filling(): void
    {
        Queue::fake();
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate] = $this->candidateWithApplication();

        $this->actingAs($owner)
            ->post(route('admin.entrevistas.store'), [
                'questionnaire_id' => $questionnaire->id,
                'talent_candidate_id' => $candidate->id,
                'transcript_source' => 'plaud',
                'transcript_text' => 'Speaker 1: olá. Speaker 2: olá.',
                'confirm_candidate_id' => (string) $candidate->id,
                'confirm_application_id' => 'none',
            ])
            ->assertSessionHasNoErrors();

        $interview = Interview::query()->firstOrFail();
        $this->assertSame(InterviewStatus::Completed, $interview->status);
        $this->assertSame(0, $interview->answers()->count());
        Queue::assertNotPushed(ProcessInterviewAudioJob::class);
    }

    public function test_vtt_file_is_converted_to_text_without_timestamps(): void
    {
        Queue::fake();
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate] = $this->candidateWithApplication();

        $vtt = "WEBVTT\n\nNOTE gravado no Meet\n\n1\n00:00:01.000 --> 00:00:04.000\n<v Entrevistadora>Conte sua experiência.</v>\n\n2\n00:00:04.500 --> 00:00:09.000 align:start\n<v Ana>Trabalhei <b>cinco anos</b> com recrutamento &amp; seleção.</v>\n";

        $this->actingAs($owner)
            ->post(route('admin.entrevistas.store'), [
                'questionnaire_id' => $questionnaire->id,
                'talent_candidate_id' => $candidate->id,
                'transcript_source' => 'file_upload',
                'transcript_file' => UploadedFile::fake()->createWithContent('reuniao.vtt', $vtt),
                'confirm_candidate_id' => (string) $candidate->id,
                'confirm_application_id' => 'none',
            ])
            ->assertSessionHasNoErrors();

        $text = Interview::query()->value('transcript_text');
        $this->assertSame("Entrevistadora: Conte sua experiência.\nAna: Trabalhei cinco anos com recrutamento & seleção.", $text);
        $this->assertStringNotContainsString('-->', $text);
        $this->assertStringNotContainsString('WEBVTT', $text);
    }

    public function test_plaud_txt_uses_the_plaud_parser(): void
    {
        Queue::fake();
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate] = $this->candidateWithApplication();

        // UTF-8 com BOM, como o Plaud exporta.
        $txt = "\xEF\xBB\xBFSpeaker 1: Bom dia.\r\nSpeaker 2: Bom dia, obrigada.\r\n";

        $this->actingAs($owner)
            ->post(route('admin.entrevistas.store'), [
                'questionnaire_id' => $questionnaire->id,
                'talent_candidate_id' => $candidate->id,
                'transcript_source' => 'plaud',
                'transcript_file' => UploadedFile::fake()->createWithContent('plaud.txt', $txt),
                'confirm_candidate_id' => (string) $candidate->id,
                'confirm_application_id' => 'none',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame("Speaker 1: Bom dia.\nSpeaker 2: Bom dia, obrigada.", Interview::query()->value('transcript_text'));
    }

    public function test_txt_over_two_megabytes_is_refused(): void
    {
        Queue::fake();
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate] = $this->candidateWithApplication();

        $this->actingAs($owner)
            ->post(route('admin.entrevistas.store'), [
                'questionnaire_id' => $questionnaire->id,
                'talent_candidate_id' => $candidate->id,
                'transcript_source' => 'file_upload',
                'transcript_file' => UploadedFile::fake()->createWithContent('grande.txt', str_repeat('a', 2 * 1024 * 1024 + 10)),
                'confirm_candidate_id' => (string) $candidate->id,
                'confirm_application_id' => 'none',
            ])
            ->assertSessionHasErrors(['transcript_file' => 'O arquivo de transcrição pode ter até 2 MB.']);

        $this->assertSame(0, Interview::query()->count());
    }

    public function test_text_needs_explicit_confirmation_of_candidate_and_vacancy(): void
    {
        Queue::fake();
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate, $application] = $this->candidateWithApplication();
        [$other] = $this->candidateWithApplication('Bruno Lima');

        $base = [
            'questionnaire_id' => $questionnaire->id,
            'talent_candidate_id' => $candidate->id,
            'hiring_application_id' => $application->id,
            'transcript_source' => 'manual_text',
            'transcript_text' => 'Texto da entrevista.',
        ];

        // Sem confirmação.
        $this->actingAs($owner)->post(route('admin.entrevistas.store'), $base)->assertSessionHasErrors('confirm');
        // Confirmação de outro candidato.
        $this->actingAs($owner)->post(route('admin.entrevistas.store'), [
            ...$base,
            'confirm_candidate_id' => (string) $other->id,
            'confirm_application_id' => (string) $application->id,
        ])->assertSessionHasErrors('confirm');
        // Confirmação sem a vaga escolhida.
        $this->actingAs($owner)->post(route('admin.entrevistas.store'), [
            ...$base,
            'confirm_candidate_id' => (string) $candidate->id,
            'confirm_application_id' => 'none',
        ])->assertSessionHasErrors('confirm');

        $this->assertSame(0, Interview::query()->count());
    }

    public function test_application_of_another_candidate_is_refused(): void
    {
        Queue::fake();
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate] = $this->candidateWithApplication();
        [, $otherApplication] = $this->candidateWithApplication('Bruno Lima');

        $this->actingAs($owner)
            ->post(route('admin.entrevistas.store'), [
                'questionnaire_id' => $questionnaire->id,
                'talent_candidate_id' => $candidate->id,
                'hiring_application_id' => $otherApplication->id,
                'transcript_source' => 'manual_text',
                'transcript_text' => 'Texto.',
                'confirm_candidate_id' => (string) $candidate->id,
                'confirm_application_id' => (string) $otherApplication->id,
            ])
            ->assertSessionHasErrors('hiring_application_id');

        $this->assertSame(0, Interview::query()->count());
    }

    public function test_audio_source_still_requires_the_audio_file(): void
    {
        Queue::fake();
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate] = $this->candidateWithApplication();

        $this->actingAs($owner)
            ->post(route('admin.entrevistas.store'), [
                'questionnaire_id' => $questionnaire->id,
                'talent_candidate_id' => $candidate->id,
                'transcript_source' => 'audio_whisper',
            ])
            ->assertSessionHasErrors('audio');

        Queue::assertNothingPushed();
    }
}
