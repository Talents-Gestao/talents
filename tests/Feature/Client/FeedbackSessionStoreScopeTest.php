<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\User;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\CreatesFeedbackFixtures;
use Tests\TestCase;

/**
 * Criar feedback: líder e modelo precisam ser da mesma empresa do feedback (isolamento),
 * modelo inativo ou inválido é recusado com mensagem clara (nunca trocado em silêncio).
 */
class FeedbackSessionStoreScopeTest extends TestCase
{
    use CreatesFeedbackFixtures;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(int $leaderId, ?int $templateId): array
    {
        return array_filter([
            'employee_name' => 'Bruno Lima',
            'leader_user_id' => $leaderId,
            'feedback_template_id' => $templateId,
            'scheduled_at' => now()->format('Y-m-d H:i'),
        ], fn ($value) => $value !== null);
    }

    public function test_leader_must_be_an_active_user_of_the_same_company(): void
    {
        $talents = $this->seedFeedbackTemplate();
        $company = $this->createFeedbackCompany();
        $other = $this->createFeedbackCompany(['name' => 'Outra empresa']);
        $admin = User::factory()->companyAdmin($company->id)->create();
        $foreignLeader = User::factory()->companyAdmin($other->id)->create();
        $inactiveLeader = User::factory()->companyUser($company->id)->create(['is_active' => false]);
        $leader = User::factory()->companyUser($company->id)->create();

        foreach ([$foreignLeader, $inactiveLeader] as $refused) {
            $this->actingAs($admin)
                ->post(route('client.feedbacks.sessions.store'), $this->payload($refused->id, $talents->id))
                ->assertSessionHasErrors(['leader_user_id' => 'O líder precisa ser um usuário ativo desta empresa.']);
        }
        $this->assertSame(0, FeedbackSession::query()->count());

        $this->actingAs($admin)
            ->post(route('client.feedbacks.sessions.store'), $this->payload($leader->id, $talents->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertDatabaseHas('feedback_sessions', ['company_id' => $company->id, 'leader_user_id' => $leader->id]);
    }

    public function test_admin_context_also_scopes_the_leader_to_the_selected_company(): void
    {
        $talents = $this->seedFeedbackTemplate();
        $company = $this->createFeedbackCompany();
        $other = $this->createFeedbackCompany(['name' => 'Outra empresa']);
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $foreignLeader = User::factory()->companyAdmin($other->id)->create();

        $this->actingAs($owner)
            ->withSession([FeedbackCompanyContext::SESSION_KEY => $company->id])
            ->post(route('admin.feedbacks.sessions.store'), $this->payload($foreignLeader->id, $talents->id))
            ->assertSessionHasErrors('leader_user_id');

        // Nem o próprio super admin (sem empresa) vira líder do feedback da empresa.
        $this->actingAs($owner)
            ->withSession([FeedbackCompanyContext::SESSION_KEY => $company->id])
            ->post(route('admin.feedbacks.sessions.store'), $this->payload($owner->id, $talents->id))
            ->assertSessionHasErrors('leader_user_id');

        $this->assertSame(0, FeedbackSession::query()->count());
    }

    public function test_inactive_foreign_or_missing_template_is_refused_instead_of_swapped(): void
    {
        $talents = $this->seedFeedbackTemplate();
        $company = $this->createFeedbackCompany();
        $other = $this->createFeedbackCompany(['name' => 'Outra empresa']);
        $admin = User::factory()->companyAdmin($company->id)->create();

        $inactive = FeedbackTemplate::query()->create(['company_id' => $company->id, 'title' => 'Inativo', 'is_active' => false]);
        $foreign = FeedbackTemplate::query()->create(['company_id' => $other->id, 'title' => 'Alheio', 'is_active' => true]);

        foreach ([$inactive->id, $foreign->id, 999999] as $templateId) {
            $this->actingAs($admin)
                ->post(route('client.feedbacks.sessions.store'), $this->payload($admin->id, $templateId))
                ->assertSessionHasErrors([
                    'feedback_template_id' => 'Modelo indisponível para esta empresa (inexistente, de outra empresa ou inativo). Escolha um modelo da lista.',
                ]);
        }
        $this->assertSame(0, FeedbackSession::query()->count());

        // Sem modelo informado, usa o padrão disponível.
        $this->actingAs($admin)
            ->post(route('client.feedbacks.sessions.store'), $this->payload($admin->id, null))
            ->assertSessionHasNoErrors();
        $this->assertSame($talents->id, (int) FeedbackSession::query()->sole()->feedback_template_id);
    }

    public function test_no_template_available_shows_a_user_message_and_logs_the_detail(): void
    {
        Log::spy();
        $company = $this->createFeedbackCompany();
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->post(route('client.feedbacks.sessions.store'), $this->payload($admin->id, null))
            ->assertSessionHasErrors(['feedback_template_id' => 'Nenhum modelo disponível. Fale com o suporte.']);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => str_contains($message, 'nenhum modelo ativo')
                && $context['company_id'] === $company->id)
            ->once();
        $this->assertSame(0, FeedbackSession::query()->count());
    }
}
