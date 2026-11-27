<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Models\InterviewAnswer;
use App\Models\InterviewQuestionnaire;
use App\Models\InterviewQuestionnaireQuestion;

class QuestionnaireVersioningTest extends InterviewTestCase
{
    /**
     * @return list<array{title: string, questions: list<array{text: string, question_key: string}>}>
     */
    private function payloadSections(InterviewQuestionnaire $questionnaire): array
    {
        return $questionnaire->sections()->with('questions')->get()->map(fn ($s) => [
            'title' => $s->title,
            'questions' => $s->questions->map(fn ($q) => ['text' => $q->text, 'question_key' => $q->question_key])->all(),
        ])->all();
    }

    public function test_questionnaire_with_interviews_refuses_changing_or_removing_questions(): void
    {
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        $interview = $this->interview($questionnaire);
        $question = InterviewQuestionnaireQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('questionnaire_id', $questionnaire->id))
            ->firstOrFail();
        InterviewAnswer::query()->create(['interview_id' => $interview->id, 'question_id' => $question->id, 'answer' => 'Resposta']);
        // Entrevista excluída (recuperável) também trava o roteiro.
        $interview->delete();

        $sections = $this->payloadSections($questionnaire);

        $changed = $sections;
        $changed[0]['questions'][0]['text'] = 'Pergunta reescrita';
        $this->actingAs($owner)
            ->put(route('admin.entrevistas.roteiros.update', $questionnaire), ['name' => $questionnaire->name, 'sections' => $changed])
            ->assertSessionHasErrors('sections');

        $removed = $sections;
        array_shift($removed[0]['questions']);
        $this->actingAs($owner)
            ->put(route('admin.entrevistas.roteiros.update', $questionnaire), ['name' => $questionnaire->name, 'sections' => $removed])
            ->assertSessionHasErrors('sections');

        $this->assertNotNull($question->fresh());
        $this->assertSame(1, InterviewAnswer::query()->count());

        // Nome, descrição e «padrão» continuam editáveis, sem recriar perguntas.
        $this->actingAs($owner)
            ->put(route('admin.entrevistas.roteiros.update', $questionnaire), [
                'name' => 'Roteiro renomeado',
                'description' => 'Nova descrição',
                'is_default' => true,
                'sections' => $sections,
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('Roteiro renomeado', $questionnaire->fresh()->name);
        $this->assertNotNull($question->fresh());
        $this->assertSame(1, InterviewAnswer::query()->count());

        $this->actingAs($owner)->delete(route('admin.entrevistas.roteiros.destroy', $questionnaire));
        $this->assertNotNull($questionnaire->fresh());
    }

    public function test_duplicate_creates_a_new_editable_version(): void
    {
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        $this->interview($questionnaire);

        $this->actingAs($owner)->post(route('admin.entrevistas.roteiros.duplicate', $questionnaire))->assertRedirect();

        $copy = InterviewQuestionnaire::query()->whereKeyNot($questionnaire->id)->firstOrFail();
        $this->assertSame(2, $copy->version);
        $this->assertSame($questionnaire->id, $copy->parent_id);
        $this->assertFalse($copy->is_default);
        $this->assertFalse($copy->isLocked());
        $this->assertSame($questionnaire->sections()->count(), $copy->sections()->count());
        $this->assertStringEndsWith('(v2)', $copy->name);

        // A nova versão aceita mudanças nas perguntas.
        $sections = $this->payloadSections($copy);
        $sections[0]['questions'][0]['text'] = 'Pergunta da versão 2';
        $this->actingAs($owner)
            ->put(route('admin.entrevistas.roteiros.update', $copy), ['name' => $copy->name, 'sections' => $sections])
            ->assertSessionHasNoErrors();

        // Duplicar de novo (a partir da cópia) segue a mesma família: v3.
        $this->actingAs($owner)->post(route('admin.entrevistas.roteiros.duplicate', $copy));
        $this->assertSame(3, (int) InterviewQuestionnaire::query()->max('version'));
        $this->assertSame(2, InterviewQuestionnaire::query()->where('parent_id', $questionnaire->id)->count());
    }
}
