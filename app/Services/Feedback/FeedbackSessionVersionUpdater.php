<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackTemplateSection;
use App\Models\FeedbackTemplateVersion;
use App\Models\User;
use App\Support\Feedback\FeedbackSessionStructure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «Atualizar para a versão X»: leva um feedback aberto (rascunho ou em preenchimento, sem convite de assinatura)
 * para a versão atual do modelo.
 *
 * - Resposta cuja pergunta tem a mesma chave na versão nova: passa a apontar para a pergunta nova.
 * - Resposta sem par: fica na linha antiga, preservada, e aparece como «fora da versão atual».
 * - Perguntas extras: remapeadas pela chave da seção; sem par, ficam como estão (fora da versão atual).
 * A trilha (feedback_audit_logs) guarda versão de/para e contagens, nunca o conteúdo.
 */
class FeedbackSessionVersionUpdater
{
    public const NOT_OPEN_MESSAGE = 'Só é possível atualizar a versão de um feedback em rascunho ou em preenchimento, antes do envio para assinatura.';

    /**
     * @return array{
     *   from: array{id: int, number: int}, to: array{id: int, number: int},
     *   kept: list<array{key: string, body: string, answered: bool}>,
     *   added: list<array{key: string, body: string}>,
     *   removed: list<array{key: string, body: string, answered: bool}>,
     *   answers_moved: int, answers_out_of_version: int
     * }
     */
    public function preview(FeedbackSession $session, FeedbackTemplateVersion $target): array
    {
        $from = FeedbackSessionStructure::version($session);
        $target->loadMissing('sections.questions');

        $fromQuestions = $from->sections->flatMap->questions->keyBy('key');
        $toQuestions = $target->sections->flatMap->questions->keyBy('key');
        $answered = $session->answers()->pluck('feedback_template_question_id')->map(fn ($id) => (int) $id)->flip()->all();

        $kept = [];
        $removed = [];
        foreach ($fromQuestions as $key => $question) {
            $row = ['key' => (string) $key, 'body' => (string) $question->body, 'answered' => isset($answered[(int) $question->id])];
            if ($toQuestions->has($key)) {
                $kept[] = $row;
            } else {
                $removed[] = $row;
            }
        }

        $added = $toQuestions
            ->reject(fn ($q, $key) => $fromQuestions->has($key))
            ->map(fn ($q, $key) => ['key' => (string) $key, 'body' => (string) $q->body])
            ->values()
            ->all();

        return [
            'from' => ['id' => $from->id, 'number' => $from->number],
            'to' => ['id' => $target->id, 'number' => $target->number],
            'kept' => $kept,
            'added' => $added,
            'removed' => $removed,
            'answers_moved' => count(array_filter($kept, fn ($q) => $q['answered'])),
            'answers_out_of_version' => count(array_filter($removed, fn ($q) => $q['answered'])),
        ];
    }

    /**
     * @return array{from: int, to: int, answers_moved: int, answers_out_of_version: int}
     */
    public function apply(FeedbackSession $session, FeedbackTemplateVersion $target, User $actor): array
    {
        return DB::transaction(function () use ($session, $target, $actor) {
            $locked = FeedbackSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpenForEditing()) {
                throw ValidationException::withMessages(['version' => self::NOT_OPEN_MESSAGE]);
            }
            if ((int) $target->feedback_template_id !== (int) $locked->feedback_template_id) {
                throw ValidationException::withMessages(['version' => 'A versão escolhida não é deste modelo.']);
            }

            $from = FeedbackSessionStructure::version($locked);
            if ((int) $from->id === (int) $target->id) {
                throw ValidationException::withMessages(['version' => 'O feedback já está nesta versão.']);
            }

            $target->load('sections.questions');
            $fromQuestionKeys = $from->sections->flatMap->questions->mapWithKeys(fn ($q) => [(int) $q->id => (string) $q->key]);
            $targetQuestionIds = $target->sections->flatMap->questions->mapWithKeys(fn ($q) => [(string) $q->key => (int) $q->id]);

            $moved = 0;
            $outOfVersion = 0;
            foreach (FeedbackSessionAnswer::query()->where('feedback_session_id', $locked->id)->get() as $answer) {
                $key = $fromQuestionKeys[(int) $answer->feedback_template_question_id] ?? null;
                if ($key !== null && isset($targetQuestionIds[$key])) {
                    $answer->update(['feedback_template_question_id' => $targetQuestionIds[$key]]);
                    $moved++;
                } elseif ($key !== null) {
                    $outOfVersion++;
                }
            }

            $locked->forceFill([
                'feedback_template_version_id' => $target->id,
                'section_extras' => $this->remapExtras($locked->section_extras ?? [], $from, $target),
            ])->save();

            FeedbackAuditLog::query()->create([
                'company_id' => $locked->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_SESSION_VERSION_UPDATED,
                'subject_type' => FeedbackAuditLog::SUBJECT_SESSION,
                'subject_id' => $locked->id,
                'subject_label' => $locked->title,
                'answers_count' => $moved,
                'details' => [
                    'feedback_template_id' => $locked->feedback_template_id,
                    'from_version' => $from->number,
                    'to_version' => $target->number,
                    'answers_moved' => $moved,
                    'answers_out_of_version' => $outOfVersion,
                ],
            ]);

            return ['from' => $from->number, 'to' => $target->number, 'answers_moved' => $moved, 'answers_out_of_version' => $outOfVersion];
        });
    }

    /**
     * @param  array<string, mixed>  $extras  perguntas extras por id da seção
     * @return array<string, mixed>|null
     */
    private function remapExtras(array $extras, FeedbackTemplateVersion $from, FeedbackTemplateVersion $target): ?array
    {
        if ($extras === []) {
            return null;
        }

        $fromKeys = $from->sections->mapWithKeys(fn (FeedbackTemplateSection $s) => [(string) $s->id => (string) $s->key]);
        $targetIds = $target->sections->mapWithKeys(fn (FeedbackTemplateSection $s) => [(string) $s->key => (string) $s->id]);

        $out = [];
        foreach ($extras as $sectionId => $extra) {
            $key = $fromKeys[(string) $sectionId] ?? null;
            $newId = $key !== null ? ($targetIds[$key] ?? null) : null;
            $out[$newId ?? (string) $sectionId] = $extra;
        }

        return $out;
    }
}
