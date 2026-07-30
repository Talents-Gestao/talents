<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MeetingStatus;
use App\Models\AiSetting;
use App\Models\Meeting;
use App\Services\Interview\AudioChunkerService;
use App\Services\Interview\OpenAiWhisperService;
use App\Services\Meeting\MeetingMinutesGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessMeetingAudioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $meetingId
    ) {}

    public function handle(
        AudioChunkerService $chunker,
        OpenAiWhisperService $whisper,
        MeetingMinutesGenerator $minutesGenerator
    ): void {
        $meeting = Meeting::query()->with('company')->find($this->meetingId);
        if (! $meeting) {
            return;
        }

        $setting = AiSetting::current();
        if (! $setting || $setting->safeTranscriptionApiKey() === null) {
            $meeting->markFailed('Configure a chave OpenAI para transcrição nas configurações de IA.');

            return;
        }

        if (! $setting->is_enabled || $setting->safeApiKey() === null) {
            $meeting->markFailed('IA desabilitada ou sem chave para geração da ata.');

            return;
        }

        $audioPath = $meeting->audioAbsolutePath();
        if (! $audioPath || ! is_file($audioPath)) {
            $meeting->markFailed('Arquivo de áudio não encontrado no storage.');

            return;
        }

        $workDir = '';

        try {
            $meeting->markProcessing(MeetingStatus::Transcribing);

            $prepared = $chunker->prepareChunks($audioPath);
            $workDir = $prepared['work_dir'];
            $transcript = $whisper->transcribeChunks($prepared['chunks'], $setting);

            if (trim($transcript) === '') {
                throw new \RuntimeException('Transcrição vazia. Verifique a qualidade do áudio.');
            }

            $meeting->update(['transcript_text' => $transcript]);
            $meeting->markProcessing(MeetingStatus::GeneratingMinutes);

            $minutes = $minutesGenerator->generate($transcript, $meeting, $setting);
            $meeting->update(['minutes_text' => $minutes]);
            $meeting->markCompleted();

            if (! config('meeting.keep_audio', true) && $meeting->audio_path) {
                Storage::disk('local')->delete($meeting->audio_path);
                $meeting->update(['audio_path' => null]);
            }
        } catch (\Throwable $e) {
            Log::error('ProcessMeetingAudioJob failed', [
                'meeting_id' => $this->meetingId,
                'message' => $e->getMessage(),
            ]);
            $meeting->markFailed($e->getMessage());
            throw $e;
        } finally {
            if ($workDir !== '') {
                $chunker->cleanup($workDir);
            }
        }
    }
}
