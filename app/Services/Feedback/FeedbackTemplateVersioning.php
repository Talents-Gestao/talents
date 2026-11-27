<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackAuditLog;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateQuestion;
use App\Models\FeedbackTemplateSection;
use App\Models\FeedbackTemplateVersion;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Versões de modelos de feedback.
 *
 * - Editar a estrutura de um modelo cuja versão atual ainda não foi usada: altera a versão atual no lugar.
 * - Versão atual já usada (por qualquer feedback, inclusive excluído): cria a versão N+1; os feedbacks antigos
 *   continuam na versão deles, com as mesmas linhas de seção e pergunta (respostas intactas).
 * - Estrutura igual (mesmo hash) não gera versão; título, descrição e arquivamento também não.
 *
 * Chaves (seção e pergunta) são estáveis entre versões: o que continua mantém a chave; o que é novo recebe uma
 * chave nunca usada em nenhuma versão do modelo (nem nas removidas), para que respostas antigas não casem por
 * engano com uma pergunta nova de outro sentido.
 */
class FeedbackTemplateVersioning
{
    public const KEY_PATTERN = '/^[a-z0-9_]{1,64}$/';

    /**
     * Grava a estrutura enviada pelo editor.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{version: FeedbackTemplateVersion, outcome: 'unchanged'|'in_place'|'new_version', previous: int}
     */
    public function saveStructure(FeedbackTemplate $template, array $sections, User $actor): array
    {
        return DB::transaction(function () use ($template, $sections, $actor) {
            $current = $this->currentVersion($template);
            $canonical = $this->prepare($template, $current, $sections);

            if (hash_equals($this->hash($this->canonical($current)), $this->hash($canonical))) {
                return ['version' => $current, 'outcome' => 'unchanged', 'previous' => $current->number];
            }

            if (! $current->isUsed()) {
                $current->sections()->get()->each->delete();
                $this->writeRows($current, $canonical);
                $this->refreshSnapshot($current);
                $template->increment('version');

                return ['version' => $current, 'outcome' => 'in_place', 'previous' => $current->number];
            }

            $next = $template->versions()->create([
                'number' => (int) $template->versions()->max('number') + 1,
                'title' => $template->title,
                'description' => $template->description,
                'created_by' => $actor->id,
            ]);
            $this->writeRows($next, $canonical);
            $this->refreshSnapshot($next);
            $template->forceFill(['current_version_id' => $next->id])->save();
            $template->increment('version');

            FeedbackAuditLog::query()->create([
                'company_id' => $template->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_TEMPLATE_VERSION_CREATED,
                'subject_type' => FeedbackAuditLog::SUBJECT_TEMPLATE,
                'subject_id' => $template->id,
                'subject_label' => $template->title,
                'sessions_count' => $this->sessionsCount($current),
                'details' => ['from_version' => $current->number, 'to_version' => $next->number],
            ]);

            return ['version' => $next, 'outcome' => 'new_version', 'previous' => $current->number];
        });
    }

    /**
     * Título e descrição: ficam no modelo; a versão atual só acompanha enquanto nenhum feedback a usa
     * (feedbacks já feitos continuam mostrando o título e a descrição da versão deles).
     */
    public function syncMetadata(FeedbackTemplate $template): void
    {
        $current = $this->currentVersion($template);
        if (! $current->isUsed()) {
            $current->update(['title' => $template->title, 'description' => $template->description]);
        }
    }

    public function currentVersion(FeedbackTemplate $template): FeedbackTemplateVersion
    {
        $version = $template->current_version_id
            ? FeedbackTemplateVersion::query()->find($template->current_version_id)
            : null;

        if ($version === null) {
            $version = $template->versions()->create([
                'number' => (int) $template->versions()->max('number') + 1,
                'title' => $template->title,
                'description' => $template->description,
                'created_by' => $template->created_by,
            ]);
            $template->forceFill(['current_version_id' => $version->id])->saveQuietly();
        }

        return $version;
    }

    /** Feedbacks (não excluídos) feitos com a versão. */
    public function sessionsCount(FeedbackTemplateVersion $version): int
    {
        return FeedbackSession::query()->where('feedback_template_version_id', $version->id)->count();
    }

    /**
     * Estrutura canônica de uma versão, no formato do editor.
     *
     * @return list<array<string, mixed>>
     */
    public function canonical(FeedbackTemplateVersion $version): array
    {
        $version->loadMissing('sections.questions');

        return $version->sections->map(fn (FeedbackTemplateSection $section) => [
            'key' => (string) $section->key,
            'section_type' => $this->raw($section->section_type) ?: 'questions',
            'audience' => $this->raw($section->audience) ?: 'both',
            'title' => (string) $section->title,
            'description' => (string) ($section->description ?? ''),
            'questions' => $section->questions->map(fn (FeedbackTemplateQuestion $question) => [
                'key' => (string) $question->key,
                'body' => (string) $question->body,
                'question_type' => $this->raw($question->question_type),
                'is_required' => (bool) $question->is_required,
                'options' => collect($question->options ?? [])
                    ->map(fn ($o) => ['value' => (string) ($o['value'] ?? ''), 'label' => (string) ($o['label'] ?? '')])
                    ->values()
                    ->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    /** @param list<array<string, mixed>> $canonical */
    public function hash(array $canonical): string
    {
        return hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function refreshSnapshot(FeedbackTemplateVersion $version): void
    {
        $version->unsetRelation('sections');
        $canonical = $this->canonical($version);
        $version->update(['structure' => $canonical, 'structure_hash' => $this->hash($canonical)]);
    }

    /**
     * Grava as linhas de uma estrutura canônica (chaves já resolvidas) numa versão.
     *
     * @param  list<array<string, mixed>>  $canonical
     */
    public function writeRows(FeedbackTemplateVersion $version, array $canonical): void
    {
        foreach (array_values($canonical) as $sectionIndex => $sectionData) {
            $section = FeedbackTemplateSection::query()->create([
                'feedback_template_id' => $version->feedback_template_id,
                'feedback_template_version_id' => $version->id,
                'key' => $sectionData['key'],
                'title' => $sectionData['title'],
                'description' => ($sectionData['description'] ?? '') !== '' ? $sectionData['description'] : null,
                'section_type' => $sectionData['section_type'],
                'audience' => $sectionData['audience'],
                'sort_order' => $sectionIndex + 1,
            ]);

            foreach (array_values($sectionData['questions'] ?? []) as $questionIndex => $questionData) {
                $section->questions()->create([
                    'key' => $questionData['key'],
                    'body' => $questionData['body'],
                    'question_type' => $questionData['question_type'],
                    'options' => $questionData['question_type'] === 'single_choice' && $questionData['options'] !== []
                        ? $questionData['options']
                        : null,
                    'is_required' => (bool) $questionData['is_required'],
                    'sort_order' => $questionIndex + 1,
                ]);
            }
        }
    }

    /**
     * Normaliza a estrutura do editor e resolve as chaves: mantém a chave do que já está na versão atual;
     * chave nova enviada só vale se nunca foi usada no modelo; o resto recebe chave gerada e inédita.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public function prepare(FeedbackTemplate $template, FeedbackTemplateVersion $current, array $sections): array
    {
        $current->loadMissing('sections.questions');
        $currentSectionKeys = $current->sections->pluck('key')->flip()->all();
        $currentQuestionKeys = $current->sections->flatMap->questions->pluck('key')->flip()->all();

        $history = $this->usedKeys($template);
        $usedSections = [];
        $usedQuestions = [];
        $result = [];

        foreach (array_values($sections) as $sectionData) {
            $sectionType = ($sectionData['section_type'] ?? 'questions') === 'intro' ? 'intro' : 'questions';
            $sectionKey = $this->resolveKey($sectionData['key'] ?? null, $currentSectionKeys, $history['sections'], $usedSections, 's_');

            $questions = [];
            if ($sectionType !== 'intro') {
                foreach (array_values($sectionData['questions'] ?? []) as $questionData) {
                    $type = (string) $questionData['question_type'];
                    $questions[] = [
                        'key' => $this->resolveKey($questionData['key'] ?? null, $currentQuestionKeys, $history['questions'], $usedQuestions, 'q_'),
                        'body' => (string) $questionData['body'],
                        'question_type' => $type,
                        'is_required' => (bool) ($questionData['is_required'] ?? false),
                        'options' => $type === 'single_choice' ? $this->normalizeOptions($questionData['options'] ?? []) : [],
                    ];
                }
            }

            $result[] = [
                'key' => $sectionKey,
                'section_type' => $sectionType,
                'audience' => (string) ($sectionData['audience'] ?? 'both') ?: 'both',
                'title' => (string) $sectionData['title'],
                'description' => (string) ($sectionData['description'] ?? ''),
                'questions' => $questions,
            ];
        }

        return $result;
    }

    /**
     * Chaves já usadas em qualquer versão do modelo (inclusive perguntas e seções removidas).
     *
     * @return array{sections: array<string, true>, questions: array<string, true>}
     */
    public function usedKeys(FeedbackTemplate $template): array
    {
        $sectionIds = FeedbackTemplateSection::query()->where('feedback_template_id', $template->id)->pluck('id');

        return [
            'sections' => FeedbackTemplateSection::query()
                ->where('feedback_template_id', $template->id)
                ->pluck('key')->mapWithKeys(fn ($k) => [(string) $k => true])->all(),
            'questions' => FeedbackTemplateQuestion::query()
                ->whereIn('feedback_template_section_id', $sectionIds)
                ->pluck('key')->mapWithKeys(fn ($k) => [(string) $k => true])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $current  chaves da versão atual (podem continuar)
     * @param  array<string, true>  $history  chaves de todas as versões (não podem ser reaproveitadas)
     * @param  array<string, true>  $used  chaves já dadas nesta estrutura (atualizado por referência)
     */
    private function resolveKey(mixed $preferred, array $current, array $history, array &$used, string $prefix): string
    {
        if (is_string($preferred) && preg_match(self::KEY_PATTERN, $preferred) && ! isset($used[$preferred])) {
            if (isset($current[$preferred]) || ! isset($history[$preferred])) {
                return $used[$preferred] = $preferred;
            }
        }

        do {
            $key = $prefix.Str::lower(Str::random(10));
        } while (isset($history[$key]) || isset($used[$key]) || ! preg_match(self::KEY_PATTERN, $key));

        $used[$key] = true;

        return $key;
    }

    /**
     * Mantém o valor de cada opção (o painel da equipe lê por valor); opção nova recebe option_N livre.
     *
     * @param  array<int, mixed>  $options
     * @return list<array{value: string, label: string}>
     */
    private function normalizeOptions(array $options): array
    {
        $used = [];
        $result = [];

        foreach ($options as $option) {
            $label = trim((string) (is_array($option) ? ($option['label'] ?? '') : $option));
            if ($label === '') {
                continue;
            }

            $value = is_array($option) ? ($option['value'] ?? null) : null;
            if (! is_string($value) || ! preg_match(self::KEY_PATTERN, $value) || isset($used[$value])) {
                $n = count($used) + 1;
                while (isset($used['option_'.$n])) {
                    $n++;
                }
                $value = 'option_'.$n;
            }
            $used[$value] = true;
            $result[] = ['value' => $value, 'label' => $label];
        }

        return $result;
    }

    private function raw(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
