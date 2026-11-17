<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Models\FeedbackSession;
use App\Models\FeedbackTemplate;
use App\Models\User;
use App\Models\UserWorkspace;
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
                ->assertSessionHasErrors(['leader_user_id' => 'O líder precisa ter acesso ativo ao sistema nesta empresa.']);
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

    /**
     * Vincula um usuário já existente a mais uma empresa (como a tela de Acessos faz com e-mail repetido).
     */
    private function linkToCompany(User $user, int $companyId, bool $active = true, UserRole $role = UserRole::CompanyUser): void
    {
        UserWorkspace::create([
            'user_id' => $user->id,
            'workspace_type' => WorkspaceType::Company,
            'company_id' => $companyId,
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_leader_comes_from_company_access_not_from_legacy_company_id(): void
    {
        $talents = $this->seedFeedbackTemplate();
        $company = $this->createFeedbackCompany();
        $base = $this->createFeedbackCompany(['name' => 'Empresa-base']);
        $other = $this->createFeedbackCompany(['name' => 'Outra empresa']);
        $admin = User::factory()->companyAdmin($company->id)->create(['name' => 'Ana RH']);

        // Conta com company_id de outra empresa, mas com vínculo ativo nesta (o caso relatado).
        $linked = User::factory()->companyUser($base->id)->create(['name' => 'Isa Vinculada']);
        $this->linkToCompany($linked, $company->id);
        // Vínculo inativo nesta empresa.
        $inactiveLink = User::factory()->companyUser($base->id)->create(['name' => 'Bia Inativa']);
        $this->linkToCompany($inactiveLink, $company->id, active: false);
        // company_id desta empresa (coluna legada), mas sem vínculo aqui: não tem acesso.
        $legacyOnly = User::factory()->companyUser($other->id)->create(['name' => 'Caio Legado']);
        $legacyOnly->forceFill(['company_id' => $company->id])->save();
        $foreign = User::factory()->companyAdmin($other->id)->create(['name' => 'Duda Alheia']);

        $this->actingAs($admin)
            ->get(route('client.feedbacks.sessions.create'))
            ->assertInertia(function ($page) use ($admin, $linked) {
                $leaders = collect($page->toArray()['props']['leaders']);
                $this->assertSame(
                    collect([$admin->id, $linked->id])->sort()->values()->all(),
                    $leaders->pluck('id')->sort()->values()->all(),
                );
                $this->assertSame('Isa Vinculada (Usuário da empresa)', $leaders->firstWhere('id', $linked->id)['label']);
            });

        foreach ([$inactiveLink, $legacyOnly, $foreign] as $refused) {
            $this->actingAs($admin)
                ->post(route('client.feedbacks.sessions.store'), $this->payload($refused->id, $talents->id))
                ->assertSessionHasErrors(['leader_user_id' => 'O líder precisa ter acesso ativo ao sistema nesta empresa.']);
        }
        $this->assertSame(0, FeedbackSession::query()->count());

        $this->actingAs($admin)
            ->post(route('client.feedbacks.sessions.store'), $this->payload($linked->id, $talents->id))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('feedback_sessions', ['company_id' => $company->id, 'leader_user_id' => $linked->id]);
    }

    public function test_leader_dropdown_and_access_list_show_the_same_active_set(): void
    {
        $company = $this->createFeedbackCompany();
        $base = $this->createFeedbackCompany(['name' => 'Empresa-base']);
        $owner = User::factory()->superAdmin()->create(['is_owner' => true, 'name' => 'Owner Talents']);
        $admin = User::factory()->companyAdmin($company->id)->create(['name' => 'Ana RH']);
        $linked = User::factory()->companyUser($base->id)->create(['name' => 'Isa Vinculada']);
        $this->linkToCompany($linked, $company->id);
        $inactiveAccount = User::factory()->companyUser($company->id)->create(['name' => 'Bia', 'is_active' => false]);
        // Super admin da Talents com vínculo ativo entra; o Owner sem vínculo, não.
        $talentsLinked = User::factory()->superAdmin()->create(['name' => 'Consultora Talents']);
        $this->linkToCompany($talentsLinked, $company->id, role: UserRole::CompanyAdmin);
        // Nome repetido: o rótulo traz o e-mail.
        $twin = User::factory()->companyUser($company->id)->create(['name' => 'Ana RH', 'email' => 'ana2@example.test']);

        $leaderIds = [];
        $this->actingAs($owner)
            ->withSession([FeedbackCompanyContext::SESSION_KEY => $company->id])
            ->get(route('admin.feedbacks.sessions.create'))
            ->assertInertia(function ($page) use (&$leaderIds, $twin) {
                $leaders = collect($page->toArray()['props']['leaders']);
                $leaderIds = $leaders->pluck('id')->sort()->values()->all();
                $this->assertSame('Ana RH (Usuário da empresa · ana2@example.test)', $leaders->firstWhere('id', $twin->id)['label']);
            });

        $activeInList = [];
        $this->actingAs($owner)
            ->get(route('admin.companies.users.index', $company))
            ->assertInertia(function ($page) use (&$activeInList) {
                $activeInList = collect($page->toArray()['props']['users'])
                    ->where('is_active', true)->pluck('id')->sort()->values()->all();
            });

        $expected = collect([$admin->id, $linked->id, $talentsLinked->id, $twin->id])->sort()->values()->all();
        $this->assertSame($expected, $leaderIds);
        $this->assertSame($expected, $activeInList);
        $this->assertNotContains($owner->id, $leaderIds);
        $this->assertNotContains($inactiveAccount->id, $leaderIds);
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
                    'feedback_template_id' => 'Modelo indisponível para esta empresa (inexistente, excluído, de outra empresa ou inativo). Escolha um modelo da lista.',
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
