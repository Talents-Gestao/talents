<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\FeedbackSessionStatus;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\FeedbackTemplateQuestion;
use App\Models\FeedbackTemplateSection;
use App\Models\User;
use Database\Seeders\FeedbackTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FeedbackModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_list_feedback_dashboard(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.feedbacks.index'))
            ->assertOk();
    }

    public function test_company_admin_employee_crud_redirects_to_feedbacks_index(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->post(route('client.feedbacks.employees.store'), [
                'name' => 'Maria Silva',
                'email' => 'maria@empresa.local',
                'is_active' => true,
            ])
            ->assertRedirect(route('client.feedbacks.index'));
    }

    public function test_cannot_access_other_company_employee(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $other = Company::query()->create(['name' => 'Outra']);
        $employee = CompanyEmployee::create([
            'company_id' => $other->id,
            'name' => 'Outro',
            'email' => 'outro@test.local',
        ]);

        $this->actingAs($admin)
            ->get(route('client.feedbacks.employees.show', $employee))
            ->assertRedirect(route('client.feedbacks.index'));
    }

    public function test_company_user_can_list_feedback_dashboard(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $leader = User::factory()->companyUser($company->id)->create();

        $this->withoutVite();

        $this->actingAs($leader)
            ->get(route('client.feedbacks.index'))
            ->assertOk();
    }

    public function test_company_user_only_sees_own_feedback_sessions(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $admin = User::factory()->companyAdmin($company->id)->create();
        $leader = User::factory()->companyUser($company->id)->create();
        $otherLeader = User::factory()->companyUser($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'Ana',
            'email' => 'ana@empresa.local',
            'leader_user_id' => $leader->id,
        ]);

        $ownSession = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $leader->id,
            'title' => 'Feedback Ana',
            'status' => FeedbackSessionStatus::InProgress,
        ]);

        FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $otherLeader->id,
            'title' => 'Feedback outro líder',
            'status' => FeedbackSessionStatus::InProgress,
        ]);

        $this->actingAs($leader)
            ->get(route('client.feedbacks.sessions.show', $ownSession))
            ->assertOk();

        $this->actingAs($leader)
            ->get(route('client.feedbacks.sessions.show', FeedbackSession::where('title', 'Feedback outro líder')->first()))
            ->assertForbidden();
    }

    public function test_public_signature_page_loads(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'João',
            'email' => 'joao@empresa.local',
            'leader_user_id' => $admin->id,
        ]);

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $admin->id,
            'title' => 'Teste',
            'status' => FeedbackSessionStatus::AwaitingSignatures,
        ]);

        $signature = $session->signatures()->create([
            'role' => 'employee',
            'signer_name' => $employee->name,
            'signer_email' => $employee->email,
            'token' => '11111111-1111-1111-1111-111111111111',
        ]);

        $this->withoutVite();

        $this->get(route('feedback.sign.show', $signature->token))
            ->assertOk();
    }

    public function test_signature_completes_feedback_when_both_parties_sign(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'João',
            'email' => 'joao@empresa.local',
            'leader_user_id' => $admin->id,
        ]);

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $admin->id,
            'title' => 'Teste assinatura',
            'status' => FeedbackSessionStatus::AwaitingSignatures,
        ]);

        $employeeSignature = $session->signatures()->create([
            'role' => 'employee',
            'signer_name' => $employee->name,
            'signer_email' => $employee->email,
            'token' => '22222222-2222-2222-2222-222222222222',
        ]);

        $leaderSignature = $session->signatures()->create([
            'role' => 'leader',
            'signer_name' => $admin->name,
            'signer_email' => $admin->email,
            'token' => '33333333-3333-3333-3333-333333333333',
        ]);

        $png = 'data:image/png;base64,'.base64_encode(str_repeat('x', 120));

        $this->post(route('feedback.sign.store', $employeeSignature->token), [
            'signature_data' => $png,
            'declaration_accepted' => true,
        ])->assertRedirect();

        $session->refresh();
        $this->assertSame(FeedbackSessionStatus::AwaitingSignatures, $session->status);

        $this->post(route('feedback.sign.store', $leaderSignature->token), [
            'signature_data' => $png,
            'declaration_accepted' => true,
        ])->assertRedirect();

        $session->refresh();
        $this->assertSame(FeedbackSessionStatus::Completed, $session->status);
        $this->assertNotNull($session->completed_at);
    }

    public function test_company_admin_can_send_signature_invites_from_show_action(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'Maria',
            'email' => 'maria@empresa.local',
            'leader_user_id' => $admin->id,
        ]);

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $admin->id,
            'title' => 'Feedback Maria',
            'status' => FeedbackSessionStatus::InProgress,
        ]);

        $this->actingAs($admin)
            ->post(route('client.feedbacks.sessions.signatures', $session))
            ->assertRedirect(route('client.feedbacks.sessions.show', $session));

        $session->refresh();
        $this->assertSame(FeedbackSessionStatus::AwaitingSignatures, $session->status);
        $this->assertCount(2, $session->signatures);
    }

    public function test_cannot_sign_twice_with_same_token(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'João',
            'email' => 'joao@empresa.local',
            'leader_user_id' => $admin->id,
        ]);

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $admin->id,
            'title' => 'Teste',
            'status' => FeedbackSessionStatus::AwaitingSignatures,
        ]);

        $signature = $session->signatures()->create([
            'role' => 'employee',
            'signer_name' => $employee->name,
            'signer_email' => $employee->email,
            'token' => '44444444-4444-4444-4444-444444444444',
            'signed_at' => now(),
        ]);

        $png = 'data:image/png;base64,'.base64_encode(str_repeat('x', 120));

        $this->post(route('feedback.sign.store', $signature->token), [
            'signature_data' => $png,
            'declaration_accepted' => true,
        ])->assertForbidden();
    }

    public function test_rejects_invalid_signature_payload(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'Ana',
            'email' => 'ana@empresa.local',
            'leader_user_id' => $admin->id,
        ]);

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $admin->id,
            'title' => 'Teste',
            'status' => FeedbackSessionStatus::AwaitingSignatures,
        ]);

        $signature = $session->signatures()->create([
            'role' => 'employee',
            'signer_name' => $employee->name,
            'signer_email' => $employee->email,
            'token' => '55555555-5555-5555-5555-555555555555',
        ]);

        $this->post(route('feedback.sign.store', $signature->token), [
            'signature_data' => 'not-an-image',
            'declaration_accepted' => true,
        ])->assertSessionHasErrors('signature_data');
    }

    public function test_session_store_requires_scheduled_at(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();

        $payload = [
            'employee_name' => 'Bruno Lima',
            'employee_email' => 'bruno@empresa.local',
            'feedback_template_id' => $template->id,
            'leader_user_id' => $admin->id,
        ];

        $this->actingAs($admin)
            ->from(route('client.feedbacks.sessions.create'))
            ->post(route('client.feedbacks.sessions.store'), $payload)
            ->assertSessionHasErrors('scheduled_at');

        $this->actingAs($admin)
            ->post(route('client.feedbacks.sessions.store'), array_merge($payload, [
                'scheduled_at' => now()->format('Y-m-d H:i'),
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('feedback_sessions', [
            'company_id' => $company->id,
            'rhid_person_id' => null,
            'employee_name' => 'Bruno Lima',
            'employee_email' => 'bruno@empresa.local',
            'leader_user_id' => $admin->id,
        ]);
    }

    public function test_session_update_persists_section_extra_question(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $section = FeedbackTemplateSection::query()
            ->where('feedback_template_id', $template->id)
            ->where('key', 'termometro')
            ->firstOrFail();

        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'Maria',
            'email' => 'maria@empresa.local',
            'leader_user_id' => $admin->id,
        ]);

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $admin->id,
            'title' => 'Feedback com extra',
            'status' => FeedbackSessionStatus::InProgress,
            'scheduled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('client.feedbacks.sessions.update', $session), [
                'section_extras' => [
                    (string) $section->id => [
                        'question' => 'O que mais te motivou?',
                        'answer' => 'O reconhecimento da equipe.',
                    ],
                ],
            ])
            ->assertRedirect();

        $session->refresh();

        $this->assertSame([
            (string) $section->id => [
                'question' => 'O que mais te motivou?',
                'answer' => 'O reconhecimento da equipe.',
            ],
        ], $session->section_extras);
    }

    public function test_company_admin_can_export_session_pdf(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $session = $this->createFeedbackSession($company, $admin);

        $this->actingAs($admin)
            ->get(route('client.feedbacks.sessions.pdf', $session))
            ->assertOk();
    }

    public function test_company_user_cannot_export_session_pdf(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $leader = User::factory()->companyUser($company->id)->create();

        $session = $this->createFeedbackSession($company, $leader);

        $this->actingAs($leader)
            ->get(route('client.feedbacks.sessions.pdf', $session))
            ->assertForbidden();
    }

    public function test_company_admin_sees_leader_self_section_but_company_user_does_not(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $admin = User::factory()->companyAdmin($company->id)->create();
        $leader = User::factory()->companyUser($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'Maria',
            'email' => 'maria@empresa.local',
            'leader_user_id' => $leader->id,
        ]);

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $leader->id,
            'title' => 'Feedback Maria',
            'status' => FeedbackSessionStatus::InProgress,
            'scheduled_at' => now(),
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('client.feedbacks.sessions.edit', $session))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Feedbacks/Sessions/Edit')
                ->has('session.template.sections')
                ->where(
                    'session.template.sections',
                    fn ($sections) => collect($sections)->contains(
                        fn ($section) => ($section['audience'] ?? null) === 'leader_self'
                            || ($section['key'] ?? null) === 'dev_lider_self'
                            || str_contains((string) ($section['title'] ?? ''), 'líder para o líder'),
                    ),
                ));

        $this->actingAs($leader)
            ->get(route('client.feedbacks.sessions.edit', $session))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Feedbacks/Sessions/Edit')
                ->where(
                    'session.template.sections',
                    fn ($sections) => collect($sections)->every(
                        fn ($section) => ($section['audience'] ?? null) !== 'leader_self'
                            && ($section['key'] ?? null) !== 'dev_lider_self',
                    ),
                ));
    }

    public function test_company_user_cannot_save_leader_self_answers(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'feedbacks_access' => true,
        ]);
        $this->subscribeCompanyToNr1($company);
        $leader = User::factory()->companyUser($company->id)->create();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'Maria',
            'email' => 'maria@empresa.local',
            'leader_user_id' => $leader->id,
        ]);

        $session = FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $leader->id,
            'title' => 'Feedback Maria',
            'status' => FeedbackSessionStatus::InProgress,
            'scheduled_at' => now(),
        ]);

        $question = FeedbackTemplateQuestion::query()
            ->whereHas(
                'section',
                fn ($q) => $q
                    ->where('feedback_template_id', $session->feedback_template_id)
                    ->where('audience', 'leader_self'),
            )
            ->firstOrFail();

        $this->actingAs($leader)
            ->patch(route('client.feedbacks.sessions.update', $session), [
                'answers' => [
                    $question->id => 'Resposta indevida',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('feedback_session_answers', [
            'feedback_session_id' => $session->id,
            'feedback_template_question_id' => $question->id,
        ]);
    }

    private function createFeedbackSession(Company $company, User $leader): FeedbackSession
    {
        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::create([
            'company_id' => $company->id,
            'name' => 'Ana',
            'email' => 'ana@empresa.local',
            'leader_user_id' => $leader->id,
        ]);

        return FeedbackSession::create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $leader->id,
            'title' => 'Feedback PDF',
            'status' => FeedbackSessionStatus::Completed,
            'scheduled_at' => now(),
        ]);
    }
}
