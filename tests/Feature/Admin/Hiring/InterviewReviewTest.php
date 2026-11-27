<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\InterviewAnswerOrigin;
use App\Enums\InterviewStatus;
use App\Jobs\ProcessInterviewAudioJob;
use App\Models\Interview;
use App\Models\InterviewAnswer;
use App\Models\InterviewQuestionnaireQuestion;
use App\Models\TalentCandidate;
use App\Services\Interview\AudioChunkerService;
use App\Services\Interview\InterviewAnswerExtractor;
use App\Services\Interview\InterviewReportRenderer;
use App\Services\Interview\OpenAiWhisperService;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

class InterviewReviewTest extends InterviewTestCase
{
    public function test_old_interview_is_never_linked_by_name(): void
    {
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        TalentCandidate::query()->create(['name' => 'Joana Candidata']);
        $old = $this->interview($questionnaire, ['candidate_name' => 'Joana Candidata', 'transcript_source' => null]);

        $this->actingAs($owner)->get(route('admin.entrevistas.index'))->assertOk();
        $this->actingAs($owner)
            ->get(route('admin.entrevistas.show', $old))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('interview.candidate', null)
                ->where('interview.application', null));

        $this->assertNull($old->fresh()->talent_candidate_id);
        $this->assertNull($old->fresh()->hiring_application_id);
    }

    public function test_link_requires_explicit_confirmation_and_is_blocked_while_processing(): void
    {
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate, $application, $vacancy] = $this->candidateWithApplication();
        $old = $this->interview($questionnaire);

        $this->actingAs($owner)
            ->post(route('admin.entrevistas.link', $old), [
                'talent_candidate_id' => $candidate->id,
                'hiring_application_id' => $application->id,
            ])
            ->assertSessionHasErrors('confirm');
        $this->assertNull($old->fresh()->talent_candidate_id);

        $this->actingAs($owner)
            ->post(route('admin.entrevistas.link', $old), [
                'talent_candidate_id' => $candidate->id,
                'hiring_application_id' => $application->id,
                'confirm_candidate_id' => (string) $candidate->id,
                'confirm_application_id' => (string) $application->id,
            ])
            ->assertSessionHasNoErrors();

        $old->refresh();
        $this->assertSame($candidate->id, $old->talent_candidate_id);
        $this->assertSame($application->id, $old->hiring_application_id);
        $this->assertSame('Ana Souza', $old->candidate_name);
        $this->assertSame($vacancy->company_id, $old->company_id);

        $processing = $this->interview($questionnaire, ['status' => InterviewStatus::Transcribing]);
        $this->actingAs($owner)
            ->post(route('admin.entrevistas.link', $processing), [
                'talent_candidate_id' => $candidate->id,
                'confirm_candidate_id' => (string) $candidate->id,
                'confirm_application_id' => 'none',
            ])
            ->assertSessionHasErrors('interview');
        $this->assertNull($processing->fresh()->talent_candidate_id);
    }

    public function test_extractor_never_keeps_text_without_evidence(): void
    {
        $setting = $this->enableAi();
        $questionnaire = $this->questionnaire();
        $questions = InterviewQuestionnaireQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('questionnaire_id', $questionnaire->id))
            ->orderBy('id')
            ->limit(3)
            ->get();
        [$q1, $q2, $q3] = $questions->all();

        $transcript = 'Entrevistadora: conte sua experiência. Candidata: trabalhei cinco anos com recrutamento.';

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['answers' => [
                    // Sustentada por trecho real: mantida.
                    ['question_key' => $q1->question_key, 'answer' => 'Cinco anos em recrutamento.', 'raw_quote' => 'trabalhei cinco anos com recrutamento', 'evidence_missing' => false],
                    // IA marcou falta de evidência, mas escreveu texto: descartado.
                    ['question_key' => $q2->question_key, 'answer' => 'Provavelmente gosta de trabalhar em equipe.', 'raw_quote' => null, 'evidence_missing' => true],
                    // Trecho que não existe na transcrição: descartado.
                    ['question_key' => $q3->question_key, 'answer' => 'Fala inglês fluente.', 'raw_quote' => 'sou fluente em inglês', 'evidence_missing' => false],
                ]])]]],
            ]),
        ]);

        $rows = collect(app(InterviewAnswerExtractor::class)->extract($transcript, $questions, $setting))->keyBy('question_key');

        $this->assertSame('Cinco anos em recrutamento.', $rows[$q1->question_key]['answer']);
        $this->assertFalse($rows[$q1->question_key]['evidence_missing']);
        foreach ([$q2, $q3] as $q) {
            $this->assertSame('', $rows[$q->question_key]['answer']);
            $this->assertNull($rows[$q->question_key]['raw_quote']);
            $this->assertTrue($rows[$q->question_key]['evidence_missing']);
        }
    }

    public function test_job_stores_ai_suggestions_with_evidence_missing_as_empty(): void
    {
        $this->enableAi();
        $questionnaire = $this->questionnaire();
        $interview = $this->interview($questionnaire, ['status' => InterviewStatus::Queued]);
        $question = InterviewQuestionnaireQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('questionnaire_id', $questionnaire->id))
            ->firstOrFail();

        $extractor = \Mockery::mock(InterviewAnswerExtractor::class);
        $extractor->shouldReceive('extract')->once()->andReturn([
            ['question_key' => $question->question_key, 'answer' => 'texto inventado', 'raw_quote' => null, 'evidence_missing' => true],
        ]);

        (new ProcessInterviewAudioJob($interview->id))->handle(
            app(AudioChunkerService::class),
            app(OpenAiWhisperService::class),
            $extractor,
        );

        $answer = InterviewAnswer::query()->where('interview_id', $interview->id)->firstOrFail();
        $this->assertNull($answer->answer);
        $this->assertTrue($answer->evidence_missing);
        $this->assertSame(InterviewAnswerOrigin::AiSuggestion, $answer->origin);
    }

    public function test_draft_and_conclude_review_and_report_marks_unreviewed_ai(): void
    {
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        $interview = $this->interview($questionnaire);
        [$q1, $q2] = InterviewQuestionnaireQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('questionnaire_id', $questionnaire->id))
            ->orderBy('id')
            ->limit(2)
            ->get()
            ->all();
        InterviewAnswer::query()->create([
            'interview_id' => $interview->id,
            'question_id' => $q1->id,
            'answer' => 'Sugestão da IA.',
            'raw_quote' => 'trabalhei cinco anos com recrutamento',
            'origin' => InterviewAnswerOrigin::AiSuggestion,
        ]);

        $renderer = app(InterviewReportRenderer::class);
        $html = view('admin.entrevistas.report-pdf', [
            'interview' => $renderer->loadInterview($interview->id),
            'sections' => $renderer->groupedSections($renderer->loadInterview($interview->id)),
            'reviewLine' => $renderer->reviewLine($interview),
            'unreviewedLabel' => InterviewReportRenderer::UNREVIEWED_LABEL,
        ])->render();
        $this->assertStringContainsString('Sugestão da IA não revisada', $html);

        // Rascunho: só a observação na pergunta 1; resposta humana na pergunta 2.
        $this->actingAs($owner)
            ->patch(route('admin.entrevistas.answers.update', $interview), ['answers' => [
                ['question_id' => $q1->id, 'answer' => 'Sugestão da IA.', 'raw_quote' => 'trabalhei cinco anos com recrutamento', 'reviewer_note' => 'Confere com o áudio.'],
                ['question_id' => $q2->id, 'answer' => 'Respondeu que prefere trabalho híbrido.', 'raw_quote' => null, 'reviewer_note' => null],
            ]])
            ->assertSessionHasNoErrors();

        $a1 = InterviewAnswer::query()->where('question_id', $q1->id)->firstOrFail();
        $a2 = InterviewAnswer::query()->where('question_id', $q2->id)->firstOrFail();
        $this->assertSame(InterviewAnswerOrigin::AiSuggestion, $a1->origin);
        $this->assertSame('Confere com o áudio.', $a1->reviewer_note);
        $this->assertSame(InterviewAnswerOrigin::Human, $a2->origin);
        $this->assertNull($interview->fresh()->reviewed_at);

        $this->actingAs($owner)->post(route('admin.entrevistas.review.conclude', $interview))->assertSessionHasNoErrors();
        $interview->refresh();
        $this->assertNotNull($interview->reviewed_at);
        $this->assertSame($owner->id, $interview->reviewed_by);

        $loaded = $renderer->loadInterview($interview->id);
        $this->assertFalse(collect($renderer->groupedSections($loaded))->flatMap(fn ($s) => $s['items'])->contains('unreviewed_ai', true));
        $this->actingAs($owner)->get(route('admin.entrevistas.report.pdf', $interview))->assertOk();
        $this->actingAs($owner)->get(route('admin.entrevistas.report.docx', $interview))->assertOk();

        // Mudar a resposta depois de concluir reabre a revisão.
        $this->actingAs($owner)->patch(route('admin.entrevistas.answers.update', $interview), ['answers' => [
            ['question_id' => $q2->id, 'answer' => 'Prefere trabalho presencial.'],
        ]]);
        $this->assertNull($interview->fresh()->reviewed_at);
    }

    public function test_answer_of_another_questionnaire_is_refused(): void
    {
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        $interview = $this->interview($questionnaire);
        $copy = $this->actingAs($owner)->post(route('admin.entrevistas.roteiros.duplicate', $questionnaire));
        $otherQuestion = InterviewQuestionnaireQuestion::query()
            ->whereHas('section', fn ($q) => $q->where('questionnaire_id', '!=', $questionnaire->id))
            ->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('admin.entrevistas.answers.update', $interview), ['answers' => [
                ['question_id' => $otherQuestion->id, 'answer' => 'X'],
            ]])
            ->assertSessionHasErrors('answers');
        $this->assertSame(0, InterviewAnswer::query()->count());
        $copy->assertRedirect();
    }

    public function test_soft_delete_restore_and_processing_block(): void
    {
        $owner = $this->owner();
        $questionnaire = $this->questionnaire();
        [$candidate] = $this->candidateWithApplication();
        $interview = $this->interview($questionnaire, ['talent_candidate_id' => $candidate->id, 'audio_path' => 'private/interviews/9/audio.mp3']);

        $this->actingAs($owner)->delete(route('admin.entrevistas.destroy', $interview))->assertRedirect(route('admin.entrevistas.index'));
        $this->assertSoftDeleted($interview);
        $this->assertSame('private/interviews/9/audio.mp3', Interview::withTrashed()->find($interview->id)->audio_path);
        $this->assertNotNull($candidate->fresh());

        $this->actingAs($owner)
            ->get(route('admin.entrevistas.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('interviews.data', 0));
        $this->actingAs($owner)
            ->get(route('admin.entrevistas.index', ['status' => 'deleted']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('interviews.data', 1)
                ->where('interviews.data.0.deleted', true));
        $this->actingAs($owner)->get(route('admin.entrevistas.show', $interview))->assertOk();
        // Excluída não aceita edição.
        $this->actingAs($owner)->post(route('admin.entrevistas.review.conclude', $interview))->assertNotFound();

        $this->actingAs($owner)->post(route('admin.entrevistas.restore', $interview))->assertRedirect();
        $this->assertNotSoftDeleted($interview);

        foreach ([InterviewStatus::Queued, InterviewStatus::Transcribing, InterviewStatus::Extracting] as $status) {
            $processing = $this->interview($questionnaire, ['status' => $status]);
            $this->actingAs($owner)->delete(route('admin.entrevistas.destroy', $processing))->assertSessionHasErrors('interview');
            $this->assertNotSoftDeleted($processing);
        }
    }

    public function test_interview_routes_use_explicit_actions(): void
    {
        $questionnaire = $this->questionnaire();
        $interview = $this->interview($questionnaire);
        $viewer = $this->adminWith(['entrevistas_ia' => ['view']]);

        $this->actingAs($viewer)->get(route('admin.entrevistas.show', $interview))->assertOk();
        $this->actingAs($viewer)->get(route('admin.entrevistas.create'))->assertForbidden();
        $this->actingAs($viewer)->patch(route('admin.entrevistas.answers.update', $interview), ['answers' => []])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.entrevistas.review.conclude', $interview))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.entrevistas.destroy', $interview))->assertForbidden();
        $this->assertNotSoftDeleted($interview);
    }
}
