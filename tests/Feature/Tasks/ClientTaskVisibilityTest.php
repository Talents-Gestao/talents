<?php

namespace Tests\Feature\Tasks;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskList;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\Tasks\BoardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientTaskVisibilityTest extends TestCase
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
            'cnpj' => '44.444.444/0001-44',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'tasks_access' => true,
        ]);
    }

    private function grantTasksView(User $user): void
    {
        $workspace = $user->workspaces()->first();
        $this->assertNotNull($workspace);

        UserPermission::query()->create([
            'user_workspace_id' => $workspace->id,
            'module' => PermissionModule::Tarefas->value,
            'action' => PermissionAction::View->value,
        ]);
    }

    /**
     * @return array{board: TaskBoard, list: TaskList, cardA: TaskCard, cardB: TaskCard}
     */
    private function createBoardWithCards(Company $company): array
    {
        $board = TaskBoard::query()->create([
            'company_id' => $company->id,
            'name' => 'Quadro empresa',
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

        $cardA = TaskCard::query()->create([
            'list_id' => $list->id,
            'company_id' => $company->id,
            'title' => 'Tarefa A',
            'position' => 1000,
            'visibility' => 'company',
            'is_archived' => false,
        ]);

        $cardB = TaskCard::query()->create([
            'list_id' => $list->id,
            'company_id' => $company->id,
            'title' => 'Tarefa B',
            'position' => 2000,
            'visibility' => 'company',
            'is_archived' => false,
        ]);

        return compact('board', 'list', 'cardA', 'cardB');
    }

    public function test_company_user_board_member_sees_board_and_all_visible_cards(): void
    {
        $company = $this->baseCompany();
        ['board' => $board, 'cardA' => $cardA, 'cardB' => $cardB] = $this->createBoardWithCards($company);

        $member = User::factory()->companyUser($company->id)->create();
        $this->grantTasksView($member);
        $board->members()->attach($member->id, ['role' => 'viewer']);

        $this->actingAs($member)
            ->get('/client/tarefas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Tasks/Index')
                ->has('boards', 1)
                ->where('boards.0.id', $board->id)
                ->where('boards.0.cards_count', 2));

        $this->actingAs($member)
            ->get('/client/tarefas/'.$board->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('boardPayload.lists', 1)
                ->where('boardPayload.lists.0.cards', fn ($cards) => collect($cards)->pluck('id')->sort()->values()->all() === collect([$cardA->id, $cardB->id])->sort()->values()->all()));

        $payload = BoardPresenter::forClient($board->fresh(), $company->id, $member);
        $cardIds = collect($payload['lists'])->flatMap(fn ($l) => collect($l['cards'])->pluck('id'))->all();
        $this->assertContains($cardA->id, $cardIds);
        $this->assertContains($cardB->id, $cardIds);
    }

    public function test_company_user_without_membership_or_assignment_cannot_see_board(): void
    {
        $company = $this->baseCompany();
        ['board' => $board] = $this->createBoardWithCards($company);

        $outsider = User::factory()->companyUser($company->id)->create();
        $this->grantTasksView($outsider);

        $this->actingAs($outsider)
            ->get('/client/tarefas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Tasks/Index')
                ->has('boards', 0));

        $this->actingAs($outsider)
            ->get('/client/tarefas/'.$board->id)
            ->assertForbidden();
    }

    public function test_company_user_with_assigned_card_sees_board_but_only_that_card(): void
    {
        $company = $this->baseCompany();
        ['board' => $board, 'cardA' => $cardA, 'cardB' => $cardB] = $this->createBoardWithCards($company);

        $assignee = User::factory()->companyUser($company->id)->create();
        $this->grantTasksView($assignee);
        $cardA->members()->attach($assignee->id);

        $this->actingAs($assignee)
            ->get('/client/tarefas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Tasks/Index')
                ->has('boards', 1)
                ->where('boards.0.id', $board->id)
                ->where('boards.0.cards_count', 1));

        $this->actingAs($assignee)
            ->get('/client/tarefas/'.$board->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('boardPayload.lists', 1)
                ->where('boardPayload.lists.0.cards', fn ($cards) => collect($cards)->pluck('id')->all() === [$cardA->id]));

        $payload = BoardPresenter::forClient($board->fresh(), $company->id, $assignee);
        $cardIds = collect($payload['lists'])->flatMap(fn ($l) => collect($l['cards'])->pluck('id'))->all();
        $this->assertSame([$cardA->id], $cardIds);
        $this->assertNotContains($cardB->id, $cardIds);
    }

    public function test_company_admin_sees_all_company_boards_without_membership(): void
    {
        $company = $this->baseCompany();
        ['board' => $board, 'cardA' => $cardA, 'cardB' => $cardB] = $this->createBoardWithCards($company);

        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)
            ->get('/client/tarefas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Tasks/Index')
                ->has('boards', 1)
                ->where('boards.0.cards_count', 2));

        $this->actingAs($admin)
            ->get('/client/tarefas/'.$board->id)
            ->assertOk();

        $payload = BoardPresenter::forClient($board->fresh(), $company->id, $admin);
        $cardIds = collect($payload['lists'])->flatMap(fn ($l) => collect($l['cards'])->pluck('id'))->all();
        $this->assertContains($cardA->id, $cardIds);
        $this->assertContains($cardB->id, $cardIds);
    }
}
