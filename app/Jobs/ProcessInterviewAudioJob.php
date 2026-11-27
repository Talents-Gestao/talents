<?php

namespace App\Jobs;

use App\Enums\InterviewAnswerOrigin;
use App\Enums\InterviewStatus;
use App\Models\AiSetting;
use App\Models\Interview;
use App\Models\InterviewAnswer;
use App\Models\InterviewQuestionnaireQuestion;
use App\Services\Interview\AudioChunkerService;
use App\Services\Interview\InterviewAnswerExtractor;
use App\Services\Interview\OpenAiWhisperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Processa a entrevista:
 * - fonte audio_whisper: ffmpeg + Whisper (exige a chave de transcrição) e depois a extração;
 * - fontes de texto (manual_text, file_upload, plaud): pulam a transcrição, o texto já está gravado.
 * A extração das respostas só roda com a IA habilitada nas configurações; sem IA, a entrevista
 * fica concluída e pronta para preenchimento manual. Respostas da IA entram como «Sugestão da IA».
 */
class ProcessInterviewAudioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $interviewId
    ) {}

    public function handle(
        AudioChunkerService $chunker,
        OpenAiWhisperService $whisper,
        InterviewAnswerExtractor $extractor
    ): void {
        $interview = Interview::query()->find($this->interviewId);
        if (! $interview) {
            return;
        }

        $setting = AiSetting::current();
        $aiEnabled = $setting !== null && $setting->is_enabled && $setting->safeApiKey() !== null;
        $isText = $interview->source()->isText();

        $workDir = '';

        try {
            if ($isText) {
                $transcript = (string) $interview->transcript_text;
                if (trim($transcript) === '') {
                    $interview->markFailed('A entrevista não tem texto para processar.');

                    return;
                }
            } else {
                if (! $setting || $setting->safeTranscriptionApiKey() === null) {
                    $interview->markFailed('Configure a chave OpenAI para transcrição nas configurações de IA.');

                    return;
                }

                $audioPath = $interview->audioAbsolutePath();
                if (! $audioPath || ! is_file($audioPath)) {
                    $interview->markFailed('Arquivo de áudio não encontrado no storage.');

                    return;
                }

                $interview->markProcessing(InterviewStatus::Transcribing);

                $prepared = $chunker->prepareChunks($audioPath);
                $workDir = $prepared['work_dir'];
                $transcript = $whisper->transcribeChunks($prepared['chunks'], $setting);

                if (trim($transcript) === '') {
                    throw new \RuntimeException('Transcrição vazia. Verifique a qualidade do áudio.');
                }

                $interview->update(['transcript_text' => $transcript]);
            }

            if (! $aiEnabled) {
                // Sem IA: pronta para preenchimento manual.
                $interview->markCompleted();

                return;
            }

            $interview->markProcessing(InterviewStatus::Extracting);

            $questions = InterviewQuestionnaireQuestion::query()
                ->whereHas('section', fn ($q) => $q->where('questionnaire_id', $interview->questionnaire_id))
                ->with('section')
                ->orderBy('section_id')
                ->orderBy('position')
                ->get();

            $extracted = $extractor->extract($transcript, $questions, $setting);
            $questionsByKey = $questions->keyBy('question_key');

            DB::transaction(function () use ($interview, $extracted, $questionsByKey) {
                InterviewAnswer::query()->where('interview_id', $interview->id)->delete();

                foreach ($extracted as $row) {
                    $question = $questionsByKey->get($row['question_key']);
                    if (! $question) {
                        continue;
                    }

                    $missing = (bool) ($row['evidence_missing'] ?? false);

                    InterviewAnswer::query()->create([
                        'interview_id' => $interview->id,
                        'question_id' => $question->id,
                        'answer' => $missing ? null : $row['answer'],
                        'raw_quote' => $missing ? null : $row['raw_quote'],
                        'origin' => InterviewAnswerOrigin::AiSuggestion,
                        'evidence_missing' => $missing,
                    ]);
                }

                $interview->update(['reviewed_at' => null, 'reviewed_by' => null]);
            });

            $interview->markCompleted();

            if (! $isText && ! config('interview.keep_audio', true) && $interview->audio_path) {
                Storage::disk('local')->delete($interview->audio_path);
                $interview->update(['audio_path' => null]);
            }
        } catch (\Throwable $e) {
            Log::error('ProcessInterviewAudioJob failed', [
                'interview_id' => $this->interviewId,
                'message' => $e->getMessage(),
            ]);
            $interview->markFailed($e->getMessage());
            throw $e;
        } finally {
            if ($workDir !== '') {
                $chunker->cleanup($workDir);
            }
        }
    }
}
