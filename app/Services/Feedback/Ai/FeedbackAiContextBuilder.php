<?php

declare(strict_types=1);

namespace App\Services\Feedback\Ai;

use App\Models\FeedbackSession;
use App\Models\FeedbackTemplateSection;
use App\Support\Feedback\FeedbackVisibility;

/**
 * Respostas já registradas que podem ir à IA como contexto do relatório. O relatório é visto pelo administrador
 * e pelo líder da sessão, então só entram seções que o líder também vê: «Reflexão do líder» (leader_self) e as
 * perguntas extras dessas seções nunca entram, seja quem for que pediu o processamento. Seção de orientação
 * (sem perguntas) também não entra.
 */
class FeedbackAiContextBuilder
{
    public const MAX_CHARS = 6000;

    public function answers(FeedbackSession $session): string
    {
        $session->loadMissing(['template.sections.questions', 'answers']);

        $sections = ($session->template?->sections ?? collect())
            ->reject(fn (FeedbackTemplateSection $s) => $s->audience === FeedbackVisibility::AUDIENCE_LEADER_SELF || $s->section_type === 'intro');

        $answers = $session->answers->keyBy('feedback_template_question_id');
        $extras = $session->section_extras ?? [];
        $blocks = [];

        foreach ($sections as $section) {
            $lines = [];
            foreach ($section->questions as $question) {
                $answer = $answers->get($question->id);
                $value = $answer ? $this->flatten($answer->value_json ?? $answer->value_text) : '';
                if ($value !== '') {
                    $lines[] = '- '.trim((string) $question->body).': '.$value;
                }
            }

            $extra = $extras[(string) $section->id] ?? null;
            if (is_array($extra) && trim((string) ($extra['answer'] ?? '')) !== '') {
                $lines[] = '- '.trim((string) ($extra['question'] ?? 'Pergunta extra')).': '.trim((string) $extra['answer']);
            }

            if ($lines !== []) {
                $blocks[] = 'Seção: '.trim((string) $section->title)."\n".implode("\n", $lines);
            }
        }

        $text = implode("\n\n", $blocks);

        return mb_strlen($text) > self::MAX_CHARS ? mb_substr($text, 0, self::MAX_CHARS).'…' : $text;
    }

    private function flatten(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            array_walk_recursive($value, function ($v) use (&$parts) {
                if (is_scalar($v) && trim((string) $v) !== '') {
                    $parts[] = trim((string) $v);
                }
            });

            return implode('; ', $parts);
        }

        return trim((string) $value);
    }
}
