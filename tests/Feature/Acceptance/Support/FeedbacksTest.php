<?php

// Cartão do quadro SUPORTE: «FEEDBACKS» (cliente realiza feedback a partir de modelo e cria o próprio modelo;
// assinatura e conclusão; visibilidade company_admin × company_user).
// Já cobertos: assinatura e conclusão (Client/FeedbackModuleTest::test_signature_completes_feedback_when_both_parties_sign,
// test_company_admin_can_send_signature_invites_from_show_action, test_cannot_sign_twice_with_same_token) e visibilidade
// (Client/FeedbackModuleTest::test_company_user_only_sees_own_feedback_sessions, Unit/Feedback/FeedbackVisibilityTest).

namespace Tests\Feature\Acceptance\Support;

use App\Models\Company;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\User;
use Database\Seeders\FeedbackTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FeedbacksTest extends TestCase
{
    use RefreshDatabase;

    private function companyWithFeedbacks(): Company
    {
        $company = Company::query()->create(['name' => 'Empresa Feedback', 'feedbacks_access' => true]);
        $this->subscribeCompanyToNr1($company);

        return $company;
    }

    public function test_client_starts_a_feedback_from_a_template_offered_in_the_new_feedback_screen(): void
    {
        $this->withoutVite();
        $this->seed(FeedbackTemplateSeeder::class);
        $company = $this->companyWithFeedbacks();
        $admin = User::factory()->companyAdmin($company->id)->create();
        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('client.feedbacks.sessions.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('templates.0.id', $template->id));

        $this->post(route('client.feedbacks.sessions.store'), [
            'employee_name' => 'Bruno Lima',
            'employee_email' => 'bruno@empresa.local',
            'feedback_template_id' => $template->id,
            'leader_user_id' => $admin->id,
            'scheduled_at' => now()->format('Y-m-d H:i'),
        ])->assertRedirect();

        $session = FeedbackSession::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame($template->id, (int) $session->feedback_template_id);

        $this->get(route('client.feedbacks.sessions.show', $session))->assertOk();
    }

    public function test_client_creates_its_own_feedback_template(): void
    {
        $company = $this->companyWithFeedbacks();
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->assertTrue(
            Route::has('client.feedbacks.templates.store'),
            'Não existe rota no portal para criar modelo de feedback.',
        );

        $this->actingAs($admin)
            ->post(route('client.feedbacks.templates.store'), ['title' => 'Modelo da empresa'])
            ->assertRedirect();

        $this->assertDatabaseHas('feedback_templates', ['company_id' => $company->id, 'title' => 'Modelo da empresa']);
    }

    public function test_company_template_with_questions_is_offered_only_to_its_company_and_managed_only_by_its_admin(): void
    {
        $this->withoutVite();
        $company = $this->companyWithFeedbacks();
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->post(route('client.feedbacks.templates.store'), [
                'title' => 'Alinhamento trimestral',
                'sections' => [[
                    'title' => 'Resultados',
                    'questions' => [
                        ['body' => 'O que foi entregue?', 'question_type' => 'textarea', 'is_required' => true],
                        ['body' => 'Como avalia o período?', 'question_type' => 'single_choice', 'options' => ['Bom', 'Regular']],
                    ],
                ]],
            ])
            ->assertRedirect(route('client.feedbacks.templates.index'))
            ->assertSessionHasNoErrors();

        $template = FeedbackTemplate::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame(2, $template->sections()->firstOrFail()->questions()->count());

        // Aparece em «Novo feedback» da própria empresa e serve para iniciar um feedback.
        $this->get(route('client.feedbacks.sessions.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('templates.0.id', $template->id));
        $this->post(route('client.feedbacks.sessions.store'), [
            'employee_name' => 'Bruno Lima',
            'feedback_template_id' => $template->id,
            'leader_user_id' => $admin->id,
            'scheduled_at' => now()->format('Y-m-d H:i'),
        ])->assertRedirect();
        $session = FeedbackSession::query()->where('feedback_template_id', $template->id)->firstOrFail();
        $this->get(route('client.feedbacks.sessions.edit', $session))->assertOk();

        // Usuário comum da empresa não gerencia modelos.
        $this->actingAs(User::factory()->companyUser($company->id)->create())
            ->post(route('client.feedbacks.templates.store'), ['title' => 'De usuário comum'])
            ->assertForbidden();

        // Outra empresa não vê, não usa e não edita.
        $other = Company::query()->create(['name' => 'Outra empresa', 'feedbacks_access' => true]);
        $this->subscribeCompanyToNr1($other);
        $otherAdmin = User::factory()->companyAdmin($other->id)->create();

        $this->actingAs($otherAdmin)
            ->get(route('client.feedbacks.templates.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('templates', []));
        $this->get(route('client.feedbacks.sessions.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('templates', []));
        $this->get(route('client.feedbacks.templates.edit', $template))->assertNotFound();
        $this->put(route('client.feedbacks.templates.update', $template), ['title' => 'Invadido'])->assertNotFound();
        $this->assertSame('Alinhamento trimestral', $template->fresh()->title);
    }
}
