<?php

namespace Tests\Feature\Tasks;

use App\Models\Company;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskList;
use App\Models\TaskProcessTemplate;
use App\Models\TaskTemplateCard;
use App\Models\TaskTemplateList;
use App\Models\User;
use App\Notifications\TaskCommentMentionNotification;
use App\Support\Tasks\BoardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function baseCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Empresa teste',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'tasks_access' => true,
        ]);
    }

    public function test_client_cannot_view_board_of_other_company(): void
    {
        $companyA = $this->baseCompany();
        $companyB = Company::query()->create([
            'name' => 'Outra',
            'cnpj' => '33.333.333/0001-33',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'tasks_access' => true,
        ]);

        $board = TaskBoard::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Quadro A',
            'is_archived' => false,
        ]);

        $userB = User::factory()->companyAdmin($companyB->id)->create();

        $this->actingAs($userB)
            ->get('/client/tarefas/'.$board->id)
            ->assertForbidden();
    }

    public function test_client_cannot_move_card_to_list_without_drop_in(): void
    {
        $company = $this->baseCompany();
        $board = TaskBoard::query()->create([
            'company_id' => $company->id,
            'name' => 'Q',
            'is_archived' => false,
        ]);

        $listFrom = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $listTo = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'B',
            'position' => 2000,
            'visibility' => 'company',
            'allow_company_drop_in' => false,
            'is_archived' => false,
        ]);

        $card = TaskCard::query()->create([
            'list_id' => $listFrom->id,
            'company_id' => $company->id,
            'title' => 'Card',
            'position' => 1000,
            'visibility' => 'company',
            'is_archived' => false,
        ]);

        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->post('/client/tarefas/cards/'.$card->id.'/mover', [
                'list_id' => $listTo->id,
                'position' => 1500,
            ])
            ->assertForbidden();
    }

    public function test_super_admin_activate_process_creates_board_lists_and_cards(): void
    {
        $company = $this->baseCompany();
        $admin = User::factory()->superAdmin()->create();

        $template = TaskProcessTemplate::query()->create([
            'name' => 'Onboarding',
            'slug' => 'onboarding-test',
            'is_active' => true,
        ]);

        $tl = TaskTemplateList::query()->create([
            'process_template_id' => $template->id,
            'name' => 'Fazer',
            'position' => 1000,
            'default_visibility' => 'company',
            'allow_company_drop_in' => true,
        ]);

        TaskTemplateCard::query()->create([
            'template_list_id' => $tl->id,
            'title' => 'Passo 1',
            'position' => 1000,
            'default_visibility' => 'inherit',
        ]);

        $this->actingAs($admin)
            ->post('/admin/tarefas/processos/'.$template->id.'/ativar', [
                'company_id' => $company->id,
                'board_name' => 'Meu quadro',
            ])
            ->assertRedirect();

        $board = TaskBoard::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($board);
        $this->assertSame('Meu quadro', $board->name);
        $this->assertSame(1, $board->lists()->count());
        $this->assertSame(1, $board->lists()->first()->cards()->count());
        $card = $board->lists()->first()->cards()->first();
        $this->assertSame($company->id, (int) $card->company_id);
    }

    public function test_client_sees_card_on_internal_list_when_company_assigned(): void
    {
        $company = $this->baseCompany();
        $admin = User::factory()->superAdmin()->create();

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'name' => 'Quadro global',
            'is_archived' => false,
        ]);

        $listInternal = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A fazer',
            'position' => 1000,
            'visibility' => 'internal',
            'allow_company_drop_in' => false,
            'is_archived' => false,
        ]);

        $card = TaskCard::query()->create([
            'list_id' => $listInternal->id,
            'company_id' => $company->id,
            'title' => 'Tarefa cliente',
            'position' => 1000,
            'visibility' => 'inherit',
            'is_archived' => false,
        ]);

        $clientUser = User::factory()->companyAdmin($company->id)->create();
        $payload = BoardPresenter::forClient($board->fresh(), $company->id);
        $cardIds = collect($payload['lists'])->flatMap(fn ($l) => collect($l['cards'])->pluck('id'))->all();
        $this->assertContains($card->id, $cardIds);

        $this->actingAs($clientUser)
            ->get('/client/tarefas/'.$board->id)
            ->assertOk();
    }

    public function test_admin_assigning_company_coerces_internal_card_visibility(): void
    {
        $company = $this->baseCompany();
        $admin = User::factory()->superAdmin()->create();

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'name' => 'Quadro global',
            'is_archived' => false,
        ]);

        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A fazer',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $card = TaskCard::query()->create([
            'list_id' => $list->id,
            'company_id' => null,
            'title' => 'Rascunho',
            'position' => 1000,
            'visibility' => 'internal',
            'is_archived' => false,
        ]);

        $this->actingAs($admin)
            ->patch('/admin/tarefas/cards/'.$card->id, [
                'company_id' => $company->id,
            ])
            ->assertRedirect();

        $card->refresh();
        $this->assertSame('company', $card->visibility);
        $this->assertSame($company->id, (int) $card->company_id);

        $clientUser = User::factory()->companyAdmin($company->id)->create();
        $payload = BoardPresenter::forClient($board->fresh(), $company->id);
        $cardIds = collect($payload['lists'])->flatMap(fn ($l) => collect($l['cards'])->pluck('id'))->all();
        $this->assertContains($card->id, $cardIds);
    }

    public function test_admin_cannot_create_company_list_card_on_global_board_without_company(): void
    {
        $company = $this->baseCompany();
        $admin = User::factory()->superAdmin()->create();

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'name' => 'Quadro global',
            'is_archived' => false,
        ]);

        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A fazer',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $this->actingAs($admin)
            ->post('/admin/tarefas/listas/'.$list->id.'/cards', [
                'title' => 'Sem empresa',
                'visibility' => 'inherit',
            ])
            ->assertSessionHasErrors('company_id');

        $this->assertSame(0, TaskCard::query()->count());
    }

    public function test_admin_creates_company_list_card_on_global_board_with_company_visible_to_client(): void
    {
        $company = $this->baseCompany();
        $admin = User::factory()->superAdmin()->create();

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'name' => 'Quadro global',
            'is_archived' => false,
        ]);

        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A fazer',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $this->actingAs($admin)
            ->post('/admin/tarefas/listas/'.$list->id.'/cards', [
                'title' => 'Com empresa',
                'visibility' => 'inherit',
                'company_id' => $company->id,
            ])
            ->assertRedirect();

        $card = TaskCard::query()->first();
        $this->assertNotNull($card);
        $this->assertSame($company->id, (int) $card->company_id);

        $clientUser = User::factory()->companyAdmin($company->id)->create();
        $this->actingAs($clientUser)
            ->get('/client/tarefas/'.$board->id)
            ->assertOk();

        $payload = BoardPresenter::forClient($board->fresh(), $company->id);
        $cardIds = collect($payload['lists'])->flatMap(fn ($l) => collect($l['cards'])->pluck('id'))->all();
        $this->assertContains($card->id, $cardIds);
    }

    public function test_comment_mention_sends_notification(): void
    {
        Notification::fake();

        $company = $this->baseCompany();
        $mentioner = User::factory()->companyAdmin($company->id)->create();
        $mentioned = User::factory()->companyAdmin($company->id)->create([
            'email' => 'mentioned@example.com',
        ]);

        $board = TaskBoard::query()->create([
            'company_id' => $company->id,
            'name' => 'Q',
            'is_archived' => false,
        ]);

        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'Colaboração',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $card = TaskCard::query()->create([
            'list_id' => $list->id,
            'company_id' => $company->id,
            'title' => 'Card',
            'position' => 1000,
            'visibility' => 'company',
            'is_archived' => false,
        ]);

        $this->actingAs($mentioner)
            ->post('/client/tarefas/cards/'.$card->id.'/comentarios', [
                'body' => 'Olá equipe',
                'mentioned_user_ids' => [$mentioned->id],
            ])
            ->assertRedirect();

        Notification::assertSentTo($mentioned, TaskCommentMentionNotification::class);
    }

    public function test_client_company_user_cannot_patch_task_fields(): void
    {
        $company = $this->baseCompany();
        $clientUser = User::factory()->companyAdmin($company->id)->create();

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'name' => 'Quadro global',
            'is_archived' => false,
        ]);

        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A fazer',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $card = TaskCard::query()->create([
            'list_id' => $list->id,
            'company_id' => $company->id,
            'title' => 'Original',
            'position' => 1000,
            'visibility' => 'company',
            'is_archived' => false,
        ]);

        $this->actingAs($clientUser)
            ->patch('/client/tarefas/cards/'.$card->id, [
                'title' => 'Alterado pelo cliente',
            ])
            ->assertForbidden();

        $this->assertSame('Original', $card->fresh()->title);
    }

    public function test_admin_can_set_board_list_color(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'name' => 'Quadro teste',
            'is_archived' => false,
        ]);

        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'Coluna',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $this->actingAs($admin)
            ->patch('/admin/tarefas/listas/'.$list->id, ['color' => '#3b82f6'])
            ->assertRedirect();

        $this->assertSame('#3b82f6', $list->fresh()->color);

        $this->actingAs($admin)
            ->patch('/admin/tarefas/listas/'.$list->id, ['color' => ''])
            ->assertRedirect();

        $this->assertNull($list->fresh()->color);
    }

    public function test_admin_can_delete_board_list_and_its_cards(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'name' => 'Quadro teste',
            'is_archived' => false,
        ]);

        $list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'Coluna extra',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $card = TaskCard::query()->create([
            'list_id' => $list->id,
            'title' => 'Tarefa na coluna',
            'position' => 1000,
            'visibility' => 'inherit',
            'is_archived' => false,
        ]);

        $this->actingAs($admin)
            ->delete('/admin/tarefas/listas/'.$list->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('task_lists', ['id' => $list->id]);
        $this->assertDatabaseMissing('task_cards', ['id' => $card->id]);
    }

    public function test_client_company_user_cannot_move_card_even_when_allowed(): void
    {
        $company = $this->baseCompany();
        $clientUser = User::factory()->companyAdmin($company->id)->create();

        $board = TaskBoard::query()->create([
            'company_id' => null,
            'name' => 'Quadro global',
            'is_archived' => false,
        ]);

        $listA = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A',
            'position' => 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $listB = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'B',
            'position' => 2000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);

        $card = TaskCard::query()->create([
            'list_id' => $listA->id,
            'company_id' => $company->id,
            'title' => 'Mover',
            'position' => 1000,
            'visibility' => 'company',
            'is_archived' => false,
        ]);

        $this->actingAs($clientUser)
            ->post('/client/tarefas/cards/'.$card->id.'/mover', [
                'list_id' => $listB->id,
                'position' => 1500,
            ])
            ->assertForbidden();

        $this->assertSame($listA->id, (int) $card->fresh()->list_id);
    }
}
