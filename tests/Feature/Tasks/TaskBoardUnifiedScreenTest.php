<?php

namespace Tests\Feature\Tasks;

use App\Models\Company;
use App\Models\TaskBoard;
use App\Models\User;
use App\Support\Tasks\BoardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Tela única de Tarefas: index e show renderizam a mesma página, com o seletor de quadros e o quadro ativo.
 */
class TaskBoardUnifiedScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function board(array $attributes): TaskBoard
    {
        return TaskBoard::query()->create(array_merge(['is_archived' => false], $attributes));
    }

    private function company(): Company
    {
        return Company::query()->create([
            'name' => 'Empresa tela única',
            'cnpj' => '55.555.555/0001-55',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'tasks_access' => true,
        ]);
    }

    public function test_admin_index_opens_first_board_and_lists_boards_for_switcher(): void
    {
        $admin = User::factory()->talentsOwner()->create();
        $first = $this->board(['company_id' => null, 'name' => 'Primeiro', 'position' => 1000, 'description' => 'Descrição A']);
        $this->board(['company_id' => null, 'name' => 'Segundo', 'position' => 2000]);

        $this->actingAs($admin)
            ->get('/admin/tarefas/quadros')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tasks/Boards/Index')
                ->where('boardPayload.id', $first->id)
                ->has('boards', 2)
                ->where('boards.0.id', $first->id)
                ->where('boards.0.description', 'Descrição A')
                ->where('boards.0.is_starred', false)
                ->missing('boards.0.lists')
                ->has('companyUsers')
                ->has('recurrenceOptions'));
    }

    public function test_admin_index_prefers_first_favorite_then_last_used_board(): void
    {
        $admin = User::factory()->talentsOwner()->create();
        $this->board(['company_id' => null, 'name' => 'Primeiro', 'position' => 1000]);
        $favorite = $this->board(['company_id' => null, 'name' => 'Favorito', 'position' => 2000]);
        $other = $this->board(['company_id' => null, 'name' => 'Outro', 'position' => 3000]);

        DB::table('task_board_user_favorites')->insert([
            'board_id' => $favorite->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/tarefas/quadros')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('boardPayload.id', $favorite->id)
                ->where('boards.1.is_starred', true));

        $this->actingAs($admin)
            ->get('/admin/tarefas/quadros/'.$other->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tasks/Boards/Index')
                ->where('boardPayload.id', $other->id)
                ->has('boards', 3));

        $this->actingAs($admin)
            ->get('/admin/tarefas/quadros')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('boardPayload.id', $other->id));
    }

    public function test_admin_index_ignores_last_board_that_was_deleted(): void
    {
        $admin = User::factory()->talentsOwner()->create();
        $keep = $this->board(['company_id' => null, 'name' => 'Fica', 'position' => 1000]);
        $gone = $this->board(['company_id' => null, 'name' => 'Sai', 'position' => 2000]);

        $this->actingAs($admin)->get('/admin/tarefas/quadros/'.$gone->id)->assertOk();
        $this->actingAs($admin)->delete('/admin/tarefas/quadros/'.$gone->id)
            ->assertRedirect(route('admin.tarefas.quadros.index'));

        $this->actingAs($admin)
            ->get('/admin/tarefas/quadros')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('boardPayload.id', $keep->id)
                ->has('boards', 1));
    }

    public function test_client_index_and_show_render_the_same_screen(): void
    {
        $company = $this->company();
        $first = $this->board(['company_id' => $company->id, 'name' => 'Quadro 1', 'position' => 1000]);
        $second = $this->board(['company_id' => $company->id, 'name' => 'Quadro 2', 'position' => 2000]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/tarefas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Tasks/Index')
                ->where('boardPayload.id', $first->id)
                ->where('boardPayload.client_mode', true)
                ->has('boards', 2)
                ->where('boards.1.cards_count', 0)
                ->has('companyUsers'));

        $this->actingAs($user)
            ->get('/client/tarefas/'.$second->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Tasks/Index')
                ->where('boardPayload.id', $second->id));

        $this->actingAs($user)
            ->get('/client/tarefas')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('boardPayload.id', $second->id));
    }

    public function test_client_index_without_boards_renders_empty_screen(): void
    {
        $company = $this->company();
        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/tarefas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Tasks/Index')
                ->where('boardPayload', null)
                ->has('boards', 0));
    }

    public function test_resolve_initial_board_id_order_of_preference(): void
    {
        $boards = [
            ['id' => 10, 'is_starred' => false],
            ['id' => 20, 'is_starred' => true],
            ['id' => 30, 'is_starred' => true],
        ];

        $this->assertSame(30, BoardPresenter::resolveInitialBoardId($boards, 30));
        $this->assertSame(30, BoardPresenter::resolveInitialBoardId($boards, '30'));
        $this->assertSame(20, BoardPresenter::resolveInitialBoardId($boards, 99));
        $this->assertSame(20, BoardPresenter::resolveInitialBoardId($boards, null));
        $this->assertSame(10, BoardPresenter::resolveInitialBoardId([['id' => 10], ['id' => 11]], null));
        $this->assertNull(BoardPresenter::resolveInitialBoardId([], 5));
    }
}
