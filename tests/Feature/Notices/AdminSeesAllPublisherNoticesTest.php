<?php

declare(strict_types=1);

namespace Tests\Feature\Notices;

use App\Actions\Notices\PublishCommercialNotice;
use App\Enums\FeedbackSessionStatus;
use App\Enums\StrategicCalendarItemKind;
use App\Models\CommercialProposal;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\User;
use Database\Seeders\FeedbackTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cada publisher gera aviso visível no sino admin (workspace Talents)
 * e invisível no portal de um cliente de outra empresa.
 */
class AdminSeesAllPublisherNoticesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_commercial_proposal_won_appears_in_admin_recent_not_other_company_client(): void
    {
        $admin = $this->admin();
        $otherClient = $this->otherCompanyClient();

        $proposal = CommercialProposal::create([
            'code' => 'PROP-2026-9001',
            'client_name' => 'Cliente Comercial',
            'employee_count' => 10,
            'total_final_cents' => 150000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        app(PublishCommercialNotice::class)->proposalWon($proposal, $admin);

        $this->assertAdminRecentContainsTitle($admin, 'Proposta fechada');
        $this->assertClientRecentDoesNotContainTitle($otherClient, 'Proposta fechada');
    }

    public function test_strategic_calendar_create_appears_in_admin_recent_not_other_company_client(): void
    {
        $admin = $this->admin();
        $company = $this->companyWithCalendar('Empresa Calendário');
        $otherClient = $this->otherCompanyClient();

        $this->actingAs($admin)
            ->post(route('admin.strategic-calendar.store'), [
                'title' => 'Ritual mensal',
                'description' => 'Descrição',
                'kind' => StrategicCalendarItemKind::Ritual->value,
                'occurs_on' => now()->addWeek()->toDateString(),
                'company_id' => $company->id,
            ])
            ->assertRedirect();

        $this->assertAdminRecentContainsTitle($admin, 'Calendário atualizado');
        $this->assertClientRecentDoesNotContainTitle($otherClient, 'Calendário atualizado');
    }

    public function test_public_complaint_appears_in_admin_recent_not_other_company_client(): void
    {
        $admin = $this->admin();
        $token = (string) Str::uuid();
        Company::query()->create([
            'name' => 'Empresa Denúncia',
            'cnpj' => '44.444.444/0001-44',
            'is_active' => true,
            'complaints_public_token' => $token,
        ]);
        $otherClient = $this->otherCompanyClient();

        $this->post(route('denuncia.store', ['token' => $token]), [
            'category' => 'outros',
            'description' => str_repeat('Descrição da denúncia para teste. ', 3),
            'is_anonymous' => true,
        ])->assertRedirect();

        $this->assertAdminRecentContainsTitle($admin, 'Nova denúncia recebida');
        $this->assertClientRecentDoesNotContainTitle($otherClient, 'Nova denúncia recebida');
    }

    public function test_feedback_public_sign_completed_appears_in_admin_recent_not_other_company_client(): void
    {
        $this->seed(FeedbackTemplateSeeder::class);

        $admin = $this->admin();
        $company = Company::query()->create([
            'name' => 'Empresa Feedback',
            'cnpj' => '55.555.555/0001-55',
            'is_active' => true,
            'feedbacks_access' => true,
        ]);
        $leader = User::factory()->companyAdmin($company->id)->create();
        $otherClient = $this->otherCompanyClient();

        $template = FeedbackTemplate::query()->whereNull('company_id')->firstOrFail();
        $employee = CompanyEmployee::query()->create([
            'company_id' => $company->id,
            'name' => 'João',
            'email' => 'joao@empresa.local',
            'leader_user_id' => $leader->id,
        ]);

        $session = FeedbackSession::query()->create([
            'company_id' => $company->id,
            'feedback_template_id' => $template->id,
            'company_employee_id' => $employee->id,
            'leader_user_id' => $leader->id,
            'title' => 'Teste assinatura',
            'status' => FeedbackSessionStatus::AwaitingSignatures,
        ]);

        $employeeSignature = $session->signatures()->create([
            'role' => 'employee',
            'signer_name' => $employee->name,
            'signer_email' => $employee->email,
            'token' => (string) Str::uuid(),
        ]);

        $leaderSignature = $session->signatures()->create([
            'role' => 'leader',
            'signer_name' => $leader->name,
            'signer_email' => $leader->email,
            'token' => (string) Str::uuid(),
        ]);

        $png = 'data:image/png;base64,'.base64_encode(str_repeat('x', 120));

        $this->post(route('feedback.sign.store', $employeeSignature->token), [
            'signature_data' => $png,
            'declaration_accepted' => true,
        ])->assertRedirect();

        $this->post(route('feedback.sign.store', $leaderSignature->token), [
            'signature_data' => $png,
            'declaration_accepted' => true,
        ])->assertRedirect();

        $session->refresh();
        $this->assertSame(FeedbackSessionStatus::Completed, $session->status);

        $this->assertAdminRecentContainsTitle($admin, 'Feedback concluído');
        $this->assertClientRecentDoesNotContainTitle($otherClient, 'Feedback concluído');
    }

    private function admin(): User
    {
        return User::factory()->superAdmin()->create([
            'email' => 'admin-notices@talents.local',
            'is_owner' => true,
        ]);
    }

    private function companyWithCalendar(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'cnpj' => '66.666.666/0001-66',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'strategic_calendar_access' => true,
        ]);
    }

    private function otherCompanyClient(): User
    {
        $other = Company::query()->create([
            'name' => 'Outra Empresa',
            'cnpj' => '77.777.777/0001-77',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        return User::factory()->companyAdmin($other->id)->create([
            'email' => 'outra-empresa@client.local',
        ]);
    }

    private function assertAdminRecentContainsTitle(User $admin, string $title): void
    {
        $this->actingAs($admin)
            ->getJson(route('admin.notices.recent'))
            ->assertOk()
            ->assertJsonFragment(['title' => $title]);
    }

    private function assertClientRecentDoesNotContainTitle(User $client, string $title): void
    {
        $response = $this->actingAs($client)
            ->getJson(route('client.notices.recent'))
            ->assertOk();

        $titles = collect($response->json('notices'))->pluck('title');
        $this->assertFalse(
            $titles->contains($title),
            "Cliente de outra empresa não deveria ver o aviso «{$title}».",
        );
    }
}
