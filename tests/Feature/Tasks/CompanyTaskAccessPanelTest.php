<?php

namespace Tests\Feature\Tasks;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskList;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\InvitationPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Painel «Acesso da empresa» (Admin → Tarefas): leitura de quem acessa o quadro no portal e quem recebe e-mail.
 */
class CompanyTaskAccessPanelTest extends TestCase
{
    use RefreshDatabase;

    private function company(array $attributes = []): Company
    {
        return Company::query()->create([
            'name' => 'Passeg',
            'cnpj' => '55.555.555/0001-55',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'tasks_access' => true,
            'contact_email' => 'contato@passeg.test',
            ...$attributes,
        ]);
    }

    private function grant(User $user, Company $company, PermissionAction ...$actions): void
    {
        $workspace = $user->companyWorkspace($company->id);
        foreach ($actions as $action) {
            UserPermission::query()->create([
                'user_workspace_id' => $workspace->id,
                'module' => PermissionModule::Tarefas->value,
                'action' => $action->value,
            ]);
        }
    }

    /** @return array{board: TaskBoard, card: TaskCard} */
    private function board(Company $company): array
    {
        $board = TaskBoard::query()->create(['company_id' => $company->id, 'name' => 'Onboarding', 'is_archived' => false]);
        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A fazer',
            'position' => 1000,
            'visibility' => 'company',
            'is_archived' => false,
        ]);
        $card = TaskCard::query()->create([
            'list_id' => $list->id,
            'company_id' => $company->id,
            'title' => 'Enviar documentos',
            'position' => 1000,
            'visibility' => 'inherit',
            'is_archived' => false,
        ]);

        return ['board' => $board, 'card' => $card];
    }

    /** @return array<string, mixed> */
    private function access(Company $company, ?TaskBoard $board = null): array
    {
        $admin = User::factory()->talentsOwner()->create();

        return $this->actingAs($admin)
            ->getJson(route('admin.tarefas.empresas.acesso', [$company, ...($board ? ['quadro' => $board->id] : [])]))
            ->assertOk()
            ->json();
    }

    public function test_users_with_varied_roles_permissions_and_invitation_state(): void
    {
        $company = $this->company();
        ['board' => $board, 'card' => $card] = $this->board($company);

        $contact = User::factory()->companyAdmin($company->id)->create(['name' => 'Ana Contato', 'email' => 'contato@passeg.test']);
        $editor = User::factory()->companyUser($company->id)->create(['name' => 'Bruno Editor']);
        $this->grant($editor, $company, PermissionAction::View, PermissionAction::Edit);
        $card->members()->attach($editor->id);
        $viewerNotMember = User::factory()->companyUser($company->id)->create(['name' => 'Carla Leitura']);
        $this->grant($viewerNotMember, $company, PermissionAction::View);
        $noPermission = User::factory()->companyUser($company->id)->create(['name' => 'Davi Sem Permissão']);
        $card->members()->attach($noPermission->id);
        $pending = User::factory()->companyUser($company->id)->pendingRegistration()->create(['name' => 'Eva Pendente']);
        $this->grant($pending, $company, PermissionAction::View);
        $card->members()->attach($pending->id);
        InvitationPassword::createToken($pending);
        $inactive = User::factory()->companyAdmin($company->id)->create(['name' => 'Fábio Inativo', 'is_active' => false]);

        $data = $this->access($company, $board);
        $users = collect($data['users'])->keyBy('name');

        $this->assertTrue($data['module']['enabled']);
        $this->assertSame('company_on', $data['module']['rule']);
        $this->assertFalse($data['sends_email']);
        $this->assertSame(2, $data['with_access_count']);
        $this->assertSame([$pending->email], $data['pending_emails']);
        $this->assertSame('contato@passeg.test', $data['registration_contact']);

        $this->assertTrue($users['Ana Contato']['board_access']);
        $this->assertTrue($users['Ana Contato']['is_registration_contact']);
        $this->assertSame('edit', $users['Ana Contato']['tasks_level']);

        $this->assertTrue($users['Bruno Editor']['board_access']);
        $this->assertSame('edit', $users['Bruno Editor']['tasks_level']);

        $this->assertFalse($users['Carla Leitura']['board_access']);
        $this->assertSame('view', $users['Carla Leitura']['tasks_level']);

        $this->assertFalse($users['Davi Sem Permissão']['board_access']);
        $this->assertSame('none', $users['Davi Sem Permissão']['tasks_level']);

        $this->assertSame('pending', $users['Eva Pendente']['status']);
        $this->assertFalse($users['Eva Pendente']['board_access']);
        $this->assertNotNull($users['Eva Pendente']['last_invite_at']);
        $this->assertNull($users['Bruno Editor']['last_invite_at']);

        $this->assertSame('inactive', $users['Fábio Inativo']['status']);
        $this->assertFalse($users['Fábio Inativo']['board_access']);

        // O painel bate com o portal: quem "tem acesso" abre o quadro; quem não tem, não abre.
        $this->withoutVite();
        foreach ([$contact, $editor] as $user) {
            $this->actingAs($user)->get(route('client.tarefas.show', $board))->assertOk();
        }
        foreach ([$viewerNotMember, $noPermission] as $user) {
            $status = $this->actingAs($user)->get(route('client.tarefas.show', $board))->status();
            $this->assertContains($status, [403, 404], $user->name);
        }

        // Só nome, e-mail, papel e situação: nada de dados sensíveis.
        $this->assertSame(
            ['id', 'name', 'email', 'role', 'role_label', 'tasks_level', 'tasks_level_label', 'status', 'status_label', 'last_invite_at', 'is_registration_contact', 'board_access', 'board_access_reason'],
            array_keys($data['users'][0]),
        );
    }

    public function test_pending_invitation_becomes_active_after_password_is_set(): void
    {
        $company = $this->company();
        $admin = User::factory()->companyAdmin($company->id)->pendingRegistration()->create(['email' => 'contato@passeg.test']);

        $before = $this->access($company);
        $this->assertSame('pending', $before['users'][0]['status']);
        $this->assertSame(0, $before['with_access_count']);
        $this->assertSame(['contato@passeg.test'], $before['pending_emails']);

        $admin->forceFill(['password_set_at' => now()])->save();

        $after = $this->access($company);
        $this->assertSame('active', $after['users'][0]['status']);
        $this->assertSame(1, $after['with_access_count']);
        $this->assertSame([], $after['pending_emails']);
    }

    public function test_module_disabled_means_nobody_has_access(): void
    {
        $company = $this->company(['tasks_access' => false]);
        ['board' => $board] = $this->board($company);
        User::factory()->companyAdmin($company->id)->create(['email' => 'contato@passeg.test']);

        $data = $this->access($company, $board);

        $this->assertFalse($data['module']['enabled']);
        $this->assertSame('company_off', $data['module']['rule']);
        $this->assertSame(0, $data['with_access_count']);
        $this->assertFalse($data['users'][0]['board_access']);
    }

    public function test_company_without_any_user_with_access(): void
    {
        $company = $this->company(['tasks_access' => null]);
        ['board' => $board] = $this->board($company);
        User::factory()->companyUser($company->id)->create();

        $data = $this->access($company, $board);

        $this->assertSame('plan_off', $data['module']['rule']);
        $this->assertSame(0, $data['with_access_count']);

        $empty = $this->company(['name' => 'Sem usuários', 'cnpj' => '66.666.666/0001-66']);
        $this->assertSame([], $this->access($empty)['users']);
    }

    public function test_board_of_another_company_is_not_found_and_company_users_are_forbidden(): void
    {
        $company = $this->company();
        $other = $this->company(['name' => 'Outra', 'cnpj' => '77.777.777/0001-77']);
        ['board' => $board] = $this->board($other);
        $admin = User::factory()->talentsOwner()->create();

        $this->actingAs($admin)
            ->getJson(route('admin.tarefas.empresas.acesso', [$company, 'quadro' => $board->id]))
            ->assertNotFound();

        $clientAdmin = User::factory()->companyAdmin($company->id)->create();
        $this->actingAs($clientAdmin)
            ->getJson(route('admin.tarefas.empresas.acesso', $company))
            ->assertForbidden();
    }

    public function test_resend_from_tasks_screen_returns_to_the_tasks_screen(): void
    {
        Mail::fake();
        $company = $this->company();
        $pending = User::factory()->companyUser($company->id)->pendingRegistration()->create();
        $admin = User::factory()->talentsOwner()->create();

        $this->actingAs($admin)
            ->from(route('admin.tarefas.quadros.index'))
            ->post(route('admin.companies.users.resend-invitation', [$company, $pending]))
            ->assertRedirect(route('admin.tarefas.quadros.index'))
            ->assertSessionHas('success');

        Mail::assertSent(UserInvitationMail::class, fn ($mail) => $mail->hasTo($pending->email));
    }
}
