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
            'title' => 'Card',
            'position' => 1000,
            'visibility' => 'company',
            'is_archived' => false,
        ]);

        $this->actingAs($mentioner)
            ->post('/client/tarefas/cards/'.$card->id.'/comentarios', [
                'body' => 'Olá equipa',
                'mentioned_user_ids' => [$mentioned->id],
            ])
            ->assertRedirect();

        Notification::assertSentTo($mentioned, \App\Notifications\TaskCommentMentionNotification::class);
    }
}
