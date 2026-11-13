<?php

// Cartões do quadro SUPORTE: «ENTREVISTA IA» e «TRANSCRIÇÃO DE ENTREVISTAS» (rota, menu e permissão no admin; processa e
// exibe transcrição e relatório). Sem chamada externa: a fila é falsa e o resultado do processamento é gravado no teste.
// Já cobertos: acesso do super_admin e bloqueio de visitante (Admin/InterviewModuleTest).

namespace Tests\Feature\Acceptance\Support;

use App\Enums\InterviewStatus;
use App\Jobs\ProcessInterviewAudioJob;
use App\Models\Interview;
use App\Models\InterviewAnswer;
use App\Models\InterviewQuestionnaire;
use App\Models\User;
use Database\Seeders\InterviewQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AiInterviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_uploads_an_interview_audio_and_later_sees_transcript_answers_and_report(): void
    {
        $this->withoutVite();
        Queue::fake();
        Storage::fake('local');
        $this->seed(InterviewQuestionnaireSeeder::class);
        $questionnaire = InterviewQuestionnaire::query()->where('is_default', true)->firstOrFail();
        $admin = User::factory()->talentsOwner()->create();

        $this->actingAs($admin)
            ->post(route('admin.entrevistas.store'), [
                'candidate_name' => 'Joana Candidata',
                'position_title' => 'Analista de RH',
                'questionnaire_id' => $questionnaire->id,
                'audio' => UploadedFile::fake()->create('entrevista.mp3', 200, 'audio/mpeg'),
            ])
            ->assertRedirect();

        $interview = Interview::query()->where('candidate_name', 'Joana Candidata')->firstOrFail();
        $this->assertSame(InterviewStatus::Queued, $interview->status);
        Storage::disk('local')->assertExists($interview->audio_path);
        Queue::assertPushed(ProcessInterviewAudioJob::class);

        // Resultado do processamento (o que o job grava após Whisper + IA).
        $question = $questionnaire->sections()->first()->questions()->first();
        $interview->update([
            'status' => InterviewStatus::Completed,
            'transcript_text' => 'Entrevistadora: conte sua experiência. Joana: trabalhei cinco anos com recrutamento.',
            'finished_at' => now(),
        ]);
        InterviewAnswer::query()->create([
            'interview_id' => $interview->id,
            'question_id' => $question->id,
            'answer' => 'Cinco anos em recrutamento.',
            'raw_quote' => 'trabalhei cinco anos com recrutamento',
        ]);

        $this->get(route('admin.entrevistas.show', $interview))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($question) {
                $props = $page->toArray()['props'];
                $this->assertSame('completed', $props['interview']['status']);
                $this->assertStringContainsString('cinco anos com recrutamento', $props['interview']['transcript_text']);
                $answered = collect($props['sections'] ?? $props['interview']['sections'] ?? [])
                    ->flatMap(fn ($section) => $section['questions'])
                    ->firstWhere('id', $question->id);
                $this->assertSame('Cinco anos em recrutamento.', $answered['answer']);
            });

        $this->get(route('admin.entrevistas.report.pdf', $interview))->assertOk();
        $this->get(route('admin.entrevistas.report.docx', $interview))->assertOk();
    }

    public function test_interviews_are_in_the_admin_menu_and_need_the_interviews_permission(): void
    {
        $menu = file_get_contents(base_path('resources/js/Layouts/AdminLayout.vue'));
        $this->assertMatchesRegularExpression("/route\\('admin\\.entrevistas\\.index'\\)[\\s\\S]{0,300}label=\"Entrevistas IA\"/", $menu);

        $withoutGrant = User::factory()->superAdmin()->create();
        $this->actingAs($withoutGrant)->get(route('admin.entrevistas.index'))->assertForbidden();
    }
}
