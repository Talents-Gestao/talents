<?php

declare(strict_types=1);

namespace App\Services\Interview;

use App\Enums\InterviewAnswerOrigin;
use App\Enums\InterviewStatus;
use App\Enums\InterviewTranscriptSource;
use App\Jobs\ProcessInterviewAudioJob;
use App\Models\AiSetting;
use App\Models\HiringApplication;
use App\Models\Interview;
use App\Models\InterviewAnswer;
use App\Models\InterviewQuestionnaireQuestion;
use App\Models\TalentCandidate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Regras das entrevistas de contratação:
 * - vínculo com candidato (e, opcionalmente, candidatura) sempre confirmado explicitamente; nunca por nome;
 * - fontes de texto pulam a transcrição; a IA só roda se estiver habilitada;
 * - respostas revisadas por pessoa (rascunho a qualquer momento, «Concluir revisão» grava quem e quando);
 * - exclusão recuperável e bloqueada durante o processamento.
 */
final class InterviewService
{
    public function __construct(
        private readonly InterviewTranscriptReader $reader,
    ) {}

    /**
     * @param  array{questionnaire_id: int, talent_candidate_id: int, hiring_application_id?: int|null, transcript_source: string, transcript_text?: string|null, confirm_candidate_id?: mixed, confirm_application_id?: mixed}  $data
     */
    public function create(array $data, ?UploadedFile $audio, ?UploadedFile $transcriptFile, ?int $userId): Interview
    {
        $source = InterviewTranscriptSource::from($data['transcript_source']);
        [$candidate, $application] = $this->resolveLink($data);

        $text = null;
        if ($source->isText()) {
            // Antes de gravar texto, o usuário confirma candidato e vaga mostrados na tela.
            $this->assertConfirmed($data, $candidate, $application);
            $text = $transcriptFile !== null
                ? $this->reader->fromFile($transcriptFile, $source)
                : $this->reader->fromText((string) ($data['transcript_text'] ?? ''));
        } elseif ($audio === null) {
            throw ValidationException::withMessages(['audio' => 'Envie o arquivo de áudio da entrevista.']);
        }

        $interview = DB::transaction(function () use ($data, $source, $candidate, $application, $text, $audio, $userId): Interview {
            $interview = Interview::query()->create([
                'questionnaire_id' => (int) $data['questionnaire_id'],
                ...$this->linkAttributes($candidate, $application),
                'transcript_source' => $source,
                'transcript_text' => $text,
                'status' => InterviewStatus::Queued,
                'audio_mime' => $audio?->getMimeType(),
                'audio_size' => $audio?->getSize(),
                'created_by' => $userId,
            ]);

            if ($audio !== null) {
                $extension = $audio->getClientOriginalExtension() ?: 'mp3';
                $path = $audio->storeAs('private/interviews/'.$interview->id, 'audio.'.$extension, 'local');
                $interview->update(['audio_path' => $path]);
            }

            return $interview;
        });

        $this->startProcessing($interview);

        return $interview->fresh();
    }

    /**
     * «Vincular a candidato» (entrevista antiga ou revínculo): exige a confirmação explícita
     * do candidato e da vaga. Bloqueado durante o processamento.
     *
     * @param  array{talent_candidate_id: int, hiring_application_id?: int|null, confirm_candidate_id?: mixed, confirm_application_id?: mixed}  $data
     */
    public function link(Interview $interview, array $data): Interview
    {
        $this->assertNotProcessing($interview, 'revinculada');
        [$candidate, $application] = $this->resolveLink($data);
        $this->assertConfirmed($data, $candidate, $application);

        $interview->update($this->linkAttributes($candidate, $application));

        return $interview;
    }

    /**
     * Rascunho das respostas: pode ser salvo a qualquer momento depois do processamento.
     * Resposta ou evidência alterada passa a ser «Revisão humana»; alterar depois de concluir a revisão
     * reabre a revisão.
     *
     * @param  list<array{question_id: int, answer?: string|null, raw_quote?: string|null, reviewer_note?: string|null}>  $answers
     */
    public function saveDraft(Interview $interview, array $answers): void
    {
        $this->assertNotProcessing($interview, 'editada');

        $validIds = InterviewQuestionnaireQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('questionnaire_id', $interview->questionnaire_id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($interview, $answers, $validIds): void {
            $existing = $interview->answers()->get()->keyBy('question_id');
            $changedContent = false;

            foreach ($answers as $row) {
                $questionId = (int) $row['question_id'];
                if (! in_array($questionId, $validIds, true)) {
                    throw ValidationException::withMessages(['answers' => 'Pergunta não pertence ao roteiro desta entrevista.']);
                }

                $answer = $this->clean($row['answer'] ?? null);
                $quote = $this->clean($row['raw_quote'] ?? null);
                $note = $this->clean($row['reviewer_note'] ?? null);

                /** @var InterviewAnswer|null $current */
                $current = $existing->get($questionId);
                if ($current === null) {
                    if ($answer === null && $quote === null && $note === null) {
                        continue;
                    }
                    InterviewAnswer::query()->create([
                        'interview_id' => $interview->id,
                        'question_id' => $questionId,
                        'answer' => $answer,
                        'raw_quote' => $quote,
                        'reviewer_note' => $note,
                        'origin' => InterviewAnswerOrigin::Human,
                        'evidence_missing' => $answer === null,
                    ]);
                    $changedContent = true;

                    continue;
                }

                $contentChanged = $current->answer !== $answer || $current->raw_quote !== $quote;
                $current->reviewer_note = $note;
                if ($contentChanged) {
                    $current->answer = $answer;
                    $current->raw_quote = $quote;
                    $current->origin = InterviewAnswerOrigin::Human;
                    $current->evidence_missing = $answer === null;
                    $changedContent = true;
                }
                if ($current->isDirty()) {
                    $current->save();
                    $changedContent = $changedContent || $current->wasChanged('reviewer_note');
                }
            }

            if ($changedContent && $interview->isReviewed()) {
                $interview->update(['reviewed_at' => null, 'reviewed_by' => null]);
            }
        });
    }

    public function concludeReview(Interview $interview, ?int $userId): Interview
    {
        $this->assertNotProcessing($interview, 'revisada');
        if ($interview->status !== InterviewStatus::Completed) {
            throw ValidationException::withMessages(['review' => 'A revisão só pode ser concluída depois do processamento.']);
        }

        $interview->update(['reviewed_at' => now(), 'reviewed_by' => $userId]);

        return $interview;
    }

    /** Exclusão recuperável: o áudio e as respostas ficam; o candidato nunca é excluído. */
    public function delete(Interview $interview): void
    {
        $this->assertNotProcessing($interview, 'excluída');
        $interview->delete();
    }

    public function restore(Interview $interview): void
    {
        $interview->restore();
    }

    public function reprocess(Interview $interview): void
    {
        $this->assertNotProcessing($interview, 'reprocessada');

        if ($interview->source()->isText()) {
            if (trim((string) $interview->transcript_text) === '') {
                throw ValidationException::withMessages(['reprocess' => 'A entrevista não tem texto para reprocessar.']);
            }
        } elseif (! $interview->audio_path || ! Storage::disk('local')->exists($interview->audio_path)) {
            throw ValidationException::withMessages(['reprocess' => 'Arquivo de áudio não disponível para reprocessamento.']);
        }

        DB::transaction(function () use ($interview): void {
            $interview->update([
                'status' => InterviewStatus::Queued,
                'failure_reason' => null,
                'transcript_text' => $interview->source()->isText() ? $interview->transcript_text : null,
                'started_at' => null,
                'finished_at' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);
            $interview->answers()->delete();
        });

        $this->startProcessing($interview);
    }

    /** IA habilitada e com chave: a extração roda; sem IA, entrevistas de texto ficam prontas para preenchimento manual. */
    public static function aiEnabled(): bool
    {
        $setting = AiSetting::current();

        return $setting !== null && $setting->is_enabled && $setting->safeApiKey() !== null;
    }

    private function startProcessing(Interview $interview): void
    {
        if ($interview->source()->isText() && ! self::aiEnabled()) {
            $interview->markCompleted();

            return;
        }

        ProcessInterviewAudioJob::dispatch($interview->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: TalentCandidate, 1: HiringApplication|null}
     */
    private function resolveLink(array $data): array
    {
        $candidate = TalentCandidate::query()->find((int) ($data['talent_candidate_id'] ?? 0));
        if ($candidate === null) {
            throw ValidationException::withMessages(['talent_candidate_id' => 'Escolha um candidato cadastrado.']);
        }
        if ($candidate->isArchived()) {
            throw ValidationException::withMessages(['talent_candidate_id' => 'O cadastro deste candidato está arquivado. Restaure-o antes de vincular uma entrevista.']);
        }

        $application = null;
        if (! empty($data['hiring_application_id'])) {
            $application = HiringApplication::query()
                ->with(['vacancy:id,title,company_id'])
                ->find((int) $data['hiring_application_id']);
            if ($application === null || (int) $application->talent_candidate_id !== (int) $candidate->id) {
                throw ValidationException::withMessages(['hiring_application_id' => 'Esta vaga não é uma candidatura do candidato escolhido.']);
            }
        }

        return [$candidate, $application];
    }

    /**
     * O formulário devolve o candidato e a vaga que mostrou para confirmação; precisam ser os mesmos do vínculo.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertConfirmed(array $data, TalentCandidate $candidate, ?HiringApplication $application): void
    {
        $confirmedCandidate = (string) ($data['confirm_candidate_id'] ?? '');
        $confirmedApplication = (string) ($data['confirm_application_id'] ?? '');
        $expectedApplication = $application !== null ? (string) $application->id : 'none';

        if ($confirmedCandidate !== (string) $candidate->id || $confirmedApplication !== $expectedApplication) {
            throw ValidationException::withMessages([
                'confirm' => 'Confirme o candidato e a vaga desta entrevista antes de gravar.',
            ]);
        }
    }

    /**
     * candidate_name, position_title e empresa vêm do vínculo.
     *
     * @return array<string, mixed>
     */
    private function linkAttributes(TalentCandidate $candidate, ?HiringApplication $application): array
    {
        return [
            'talent_candidate_id' => $candidate->id,
            'hiring_application_id' => $application?->id,
            'candidate_name' => $candidate->name,
            'position_title' => $application?->vacancy?->title ?? $candidate->current_role,
            'company_id' => $application?->vacancy?->company_id,
        ];
    }

    private function assertNotProcessing(Interview $interview, string $verb): void
    {
        if ($interview->status->isProcessing()) {
            throw ValidationException::withMessages([
                'interview' => 'A entrevista está em processamento e não pode ser '.$verb.' agora.',
            ]);
        }
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
