<?php

declare(strict_types=1);

namespace App\Support\Feedback;

use App\Models\FeedbackSession;
use App\Models\FeedbackSessionAnswer;
use App\Models\FeedbackTemplateSection;
use App\Models\FeedbackTemplateVersion;
use App\Services\Feedback\FeedbackTemplateVersioning;
use BackedEnum;
use Illuminate\Support\Collection;

/**
 * Estrutura que um feedback exibe: a da versão do modelo usada por ele (tela, PDF, assinatura e IA).
 * Respostas e perguntas extras que não pertencem a essa versão (ficaram de uma versão anterior quando o
 * feedback foi atualizado) são preservadas e mostradas à parte, como «fora da versão atual».
 */
final class FeedbackSessionStructure
{
    public static function version(FeedbackSession $session): FeedbackTemplateVersion
    {
        $session->loadMissing('templateVersion.sections.questions');
        $version = $session->templateVersion;

        if ($version === null) {
            $template = $session->template()->withTrashed()->firstOrFail();
            $version = app(FeedbackTemplateVersioning::class)->currentVersion($template);
            $session->forceFill(['feedback_template_version_id' => $version->id])->saveQuietly();
            $version->load('sections.questions');
            $session->setRelation('templateVersion', $version);
        }

        return $version;
    }

    /**
     * Seções da versão, sem a «Reflexão do líder» para quem não pode vê-la.
     *
     * @return Collection<int, FeedbackTemplateSection>
     */
    public static function sections(FeedbackSession $session, bool $canViewLeaderSelf): Collection
    {
        return self::version($session)->sections
            ->reject(fn (FeedbackTemplateSection $s) => ! $canViewLeaderSelf && self::raw($s->audience) === FeedbackVisibility::AUDIENCE_LEADER_SELF)
            ->values();
    }

    /** @return list<int> ids das perguntas da versão do feedback */
    public static function questionIds(FeedbackSession $session): array
    {
        return self::version($session)->sections->flatMap->questions->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Respostas de perguntas que não estão na versão do feedback.
     *
     * @return list<array{question_id: int, question_key: string, body: string, question_type: string, options: array<int, mixed>|null, section_title: string, value: mixed}>
     */
    public static function outOfVersionAnswers(FeedbackSession $session, bool $canViewLeaderSelf): array
    {
        $inVersion = array_flip(self::questionIds($session));
        $session->loadMissing('answers.question.section');

        return $session->answers
            ->reject(fn (FeedbackSessionAnswer $a) => isset($inVersion[(int) $a->feedback_template_question_id]))
            ->filter(fn (FeedbackSessionAnswer $a) => $a->question !== null)
            ->reject(fn (FeedbackSessionAnswer $a) => ! $canViewLeaderSelf
                && self::raw($a->question->section?->audience) === FeedbackVisibility::AUDIENCE_LEADER_SELF)
            ->map(fn (FeedbackSessionAnswer $a) => [
                'question_id' => (int) $a->feedback_template_question_id,
                'question_key' => (string) $a->question->key,
                'body' => (string) $a->question->body,
                'question_type' => self::raw($a->question->question_type),
                'options' => $a->question->options,
                'section_title' => (string) ($a->question->section?->title ?? ''),
                'value' => $a->value_json ?? $a->value_text,
            ])
            ->values()
            ->all();
    }

    /**
     * Perguntas extras de seções que não estão na versão do feedback.
     *
     * @return list<array{section_id: string, section_title: string, question: string, answer: string}>
     */
    public static function outOfVersionExtras(FeedbackSession $session, bool $canViewLeaderSelf): array
    {
        $extras = $session->section_extras ?? [];
        if ($extras === []) {
            return [];
        }

        $inVersion = self::version($session)->sections->pluck('id')->map(fn ($id) => (string) $id)->flip()->all();
        $foreignIds = array_values(array_filter(array_keys($extras), fn ($id) => ! isset($inVersion[(string) $id])));
        if ($foreignIds === []) {
            return [];
        }

        $sections = FeedbackTemplateSection::query()->whereIn('id', $foreignIds)->get()->keyBy(fn ($s) => (string) $s->id);
        $out = [];
        foreach ($foreignIds as $id) {
            $extra = $extras[$id] ?? null;
            $section = $sections->get((string) $id);
            if (! is_array($extra) || ! $section) {
                continue;
            }
            if (! $canViewLeaderSelf && self::raw($section->audience) === FeedbackVisibility::AUDIENCE_LEADER_SELF) {
                continue;
            }
            $out[] = [
                'section_id' => (string) $id,
                'section_title' => (string) $section->title,
                'question' => (string) ($extra['question'] ?? ''),
                'answer' => (string) ($extra['answer'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Payload no formato que as telas já usam («template» com seções e perguntas), vindo da versão.
     *
     * @return array<string, mixed>
     */
    public static function templatePayload(FeedbackSession $session, bool $canViewLeaderSelf): array
    {
        $version = self::version($session);

        return [
            'id' => $session->feedback_template_id,
            'version_id' => $version->id,
            'version_number' => $version->number,
            'title' => $version->title,
            'description' => $version->description,
            'sections' => self::sections($session, $canViewLeaderSelf)->map(fn (FeedbackTemplateSection $s) => [
                'id' => $s->id,
                'key' => $s->key,
                'title' => $s->title,
                'description' => $s->description,
                'section_type' => self::raw($s->section_type),
                'audience' => self::raw($s->audience),
                'sort_order' => $s->sort_order,
                'questions' => $s->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'key' => $q->key,
                    'body' => $q->body,
                    'question_type' => self::raw($q->question_type),
                    'options' => $q->options,
                    'is_required' => (bool) $q->is_required,
                    'sort_order' => $q->sort_order,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private static function raw(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
