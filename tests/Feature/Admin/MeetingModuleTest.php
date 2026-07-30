<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\MeetingStatus;
use App\Jobs\ProcessMeetingAudioJob;
use App\Models\AiSetting;
use App\Models\Company;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Interview\AudioChunkerService;
use App\Services\Interview\OpenAiWhisperService;
use App\Services\Meeting\MeetingMinutesGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_meetings_index(): void
    {
        $this->get(route('admin.reunioes.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_access_meetings_index(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.reunioes.index'))
            ->assertOk();
    }

    public function test_admin_can_create_meeting(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create(['name' => 'Empresa Reunião', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.reunioes.store'), [
                'title' => 'Alinhamento semanal',
                'company_id' => $company->id,
                'participants_text' => 'Ana, Carlos',
            ])
            ->assertRedirect();

        $meeting = Meeting::query()->where('title', 'Alinhamento semanal')->first();
        $this->assertNotNull($meeting);
        $this->assertSame(MeetingStatus::Draft, $meeting->status);
        $this->assertSame('Ana, Carlos', $meeting->participants_text);
        $this->assertSame($admin->id, $meeting->created_by);
    }

    public function test_admin_can_upload_audio_and_dispatch_processing_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = User::factory()->superAdmin()->create();
        $meeting = Meeting::query()->create([
            'title' => 'Kickoff',
            'status' => MeetingStatus::Draft,
            'created_by' => $admin->id,
        ]);

        $file = UploadedFile::fake()->create('reuniao.webm', 120, 'audio/webm');

        $this->actingAs($admin)
            ->post(route('admin.reunioes.audio.store', $meeting), [
                'audio' => $file,
                'duration_seconds' => 42,
            ])
            ->assertRedirect(route('admin.reunioes.show', $meeting));

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Queued, $meeting->status);
        $this->assertSame(42, $meeting->duration_seconds);
        $this->assertNotNull($meeting->audio_path);
        Storage::disk('local')->assertExists($meeting->audio_path);

        Queue::assertPushed(ProcessMeetingAudioJob::class, fn (ProcessMeetingAudioJob $job) => $job->meetingId === $meeting->id);
    }

    public function test_process_meeting_audio_job_generates_minutes(): void
    {
        Storage::fake('local');

        $admin = User::factory()->superAdmin()->create();
        AiSetting::query()->create([
            'provider' => 'openai',
            'api_key' => 'sk-test-analysis',
            'model' => 'gpt-4o-mini',
            'is_enabled' => true,
            'max_tokens' => 2000,
            'temperature' => 0.2,
        ]);

        $meeting = Meeting::query()->create([
            'title' => 'Reunião de planejamento',
            'status' => MeetingStatus::Queued,
            'created_by' => $admin->id,
        ]);

        $path = 'private/meetings/'.$meeting->id.'/audio.webm';
        Storage::disk('local')->put($path, 'fake-audio-bytes');
        $meeting->update([
            'audio_path' => $path,
            'audio_mime' => 'audio/webm',
            'audio_size' => 16,
        ]);

        $absolute = Storage::disk('local')->path($path);

        $this->mock(AudioChunkerService::class, function ($mock) use ($absolute) {
            $mock->shouldReceive('prepareChunks')
                ->once()
                ->with($absolute)
                ->andReturn([
                    'chunks' => [$absolute],
                    'work_dir' => '',
                ]);
            $mock->shouldReceive('cleanup')->never();
        });

        $this->mock(OpenAiWhisperService::class, function ($mock) {
            $mock->shouldReceive('transcribeChunks')
                ->once()
                ->andReturn('Discutimos o prazo do projeto e Ana ficou responsável pelo follow-up.');
        });

        $this->mock(MeetingMinutesGenerator::class, function ($mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn("## Resumo\nAlinhamento do prazo.\n\n## Decisões\nManter entrega.");
        });

        (new ProcessMeetingAudioJob($meeting->id))->handle(
            app(AudioChunkerService::class),
            app(OpenAiWhisperService::class),
            app(MeetingMinutesGenerator::class),
        );

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Completed, $meeting->status);
        $this->assertStringContainsString('prazo do projeto', (string) $meeting->transcript_text);
        $this->assertStringContainsString('## Resumo', (string) $meeting->minutes_text);
        $this->assertNull($meeting->failure_reason);
    }

    public function test_admin_can_update_minutes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $meeting = Meeting::query()->create([
            'title' => 'Ata editável',
            'status' => MeetingStatus::Completed,
            'minutes_text' => 'Versão antiga',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.reunioes.minutes.update', $meeting), [
                'minutes_text' => "## Resumo\nTexto revisado pela diretora.",
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'minutes_text' => "## Resumo\nTexto revisado pela diretora.",
        ]);
    }

    public function test_admin_can_destroy_meeting(): void
    {
        Storage::fake('local');

        $admin = User::factory()->superAdmin()->create();
        $meeting = Meeting::query()->create([
            'title' => 'Para excluir',
            'status' => MeetingStatus::Draft,
            'created_by' => $admin->id,
        ]);

        $path = 'private/meetings/'.$meeting->id.'/audio.webm';
        Storage::disk('local')->put($path, 'x');
        $meeting->update(['audio_path' => $path]);

        $this->actingAs($admin)
            ->delete(route('admin.reunioes.destroy', $meeting))
            ->assertRedirect(route('admin.reunioes.index'));

        $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
    }
}
