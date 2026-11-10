<?php

namespace Tests\Feature\Tasks;

use App\Http\Middleware\HandleInertiaRequests;
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

    /** @return array<string, string> */
    private function partialHeaders(string $component, string $only, array $extra = []): array
    {
        return array_merge([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => $component,
            'X-Inertia-Partial-Data' => $only,
        ], $extra);
    }

    public function test_admin_screen_lists_all_boards_with_summary_and_focus(): void
    {
        $admin = User::factory()->talentsOwner()->create();
        $board = $this->board(['company_id' => null, 'name' => 'Com cartões', 'position' => 1000]);
        $this->board(['company_id' => null, 'name' => 'Vazio', 'position' => 2000]);
        $todo = $board->lists()->create(['name' => 'A fazer', 'position' => 1000, 'visibility' => 'internal', 'is_archived' => false]);
        $done = $board->lists()->create(['name' => 'Feito', 'position' => 2000, 'visibility' => 'internal', 'is_archived' => false]);
        $todo->cards()->create(['title' => 'Atrasado', 'position' => 1000, 'visibility' => 'inherit', 'is_archived' => false, 'due_date' => now()->subDays(2)->toDateString()]);
        $todo->cards()->create(['title' => 'Próximo', 'position' => 2000, 'visibility' => 'inherit', 'is_archived' => false, 'due_date' => now()->addDays(3)->toDateString()]);
        $done->cards()->create(['title' => 'Concluído', 'position' => 1000, 'visibility' => 'inherit', 'is_archived' => false, 'completed_at' => now()]);
        $todo->cards()->create(['title' => 'Arquivado', 'position' => 3000, 'visibility' => 'inherit', 'is_archived' => true]);

        $this->actingAs($admin)
            ->get('/admin/tarefas/quadros')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tasks/Boards/Index')
                ->has('boards', 2)
                ->where('focusBoardId', $board->id)
                ->where('scrollToFocus', false)
                ->where('boardPayloads', [])
                ->where('boards.0.lists_count', 2)
                ->where('boards.0.cards_count', 3)
                ->where('boards.0.summary.completed_count', 1)
                ->where('boards.0.summary.overdue_count', 1)
                ->where('boards.0.summary.due_soon_count', 1)
                ->where('boards.0.summary.next_due_date', now()->addDays(3)->toDateString())
                ->where('boards.0.summary.lists.0.name', 'A fazer')
                ->where('boards.0.summary.lists.0.cards_count', 2)
                ->where('boards.1.cards_count', 0));
    }

    public function test_admin_partial_reload_loads_only_requested_boards_and_remembers_opened(): void
    {
        $admin = User::factory()->talentsOwner()->create();
        $first = $this->board(['company_id' => null, 'name' => 'Primeiro', 'position' => 1000]);
        $second = $this->board(['company_id' => null, 'name' => 'Segundo', 'position' => 2000]);
        $third = $this->board(['company_id' => null, 'name' => 'Terceiro', 'position' => 3000]);
        $list = $third->lists()->create(['name' => 'Lista', 'position' => 1000, 'visibility' => 'internal', 'is_archived' => false]);
        $list->cards()->create(['title' => 'Guardado', 'position' => 1000, 'visibility' => 'inherit', 'is_archived' => true]);

        $response = $this->actingAs($admin)
            ->withHeaders($this->partialHeaders('Admin/Tasks/Boards/Index', 'boardPayloads', [
                'X-Task-Boards' => "{$second->id},{$third->id},999999,abc",
                'X-Task-Boards-Archived' => (string) $third->id,
                'X-Task-Board-Opened' => (string) $second->id,
            ]))
            ->get('/admin/tarefas/quadros')
            ->assertOk();

        $props = $response->json('props');
        $this->assertArrayNotHasKey('boards', $props);
        $this->assertArrayNotHasKey('boardPayload', $props);
        $this->assertSame([$second->id, $third->id], array_column($props['boardPayloads'], 'id'));
        $this->assertFalse($props['boardPayloads'][0]['show_archived']);
        $this->assertTrue($props['boardPayloads'][1]['show_archived']);
        $this->assertSame('Guardado', $props['boardPayloads'][1]['lists'][0]['cards'][0]['title']);

        $this->flushHeaders();

        $this->actingAs($admin)
            ->get('/admin/tarefas/quadros')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('focusBoardId', $second->id)
                ->where('boardPayload.id', $second->id));

        $this->actingAs($admin)
            ->get('/admin/tarefas/quadros/'.$first->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('focusBoardId', $first->id)
                ->where('scrollToFocus', true)
                ->has('boards', 3));
    }

    public function test_client_partial_reload_never_returns_boards_of_another_company(): void
    {
        $company = $this->company();
        $other = Company::query()->create([
            'name' => 'Outra empresa',
            'cnpj' => '66.666.666/0001-66',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'tasks_access' => true,
        ]);
        $mine = $this->board(['company_id' => $company->id, 'name' => 'Meu', 'position' => 1000]);
        $foreign = $this->board(['company_id' => $other->id, 'name' => 'Alheio', 'position' => 2000]);
        $list = $mine->lists()->create(['name' => 'Lista', 'position' => 1000, 'visibility' => 'company', 'is_archived' => false]);
        $list->cards()->create(['title' => 'Visível', 'company_id' => $company->id, 'position' => 1000, 'visibility' => 'inherit', 'is_archived' => false]);
        $list->cards()->create(['title' => 'Interno', 'company_id' => $company->id, 'position' => 2000, 'visibility' => 'internal', 'is_archived' => false]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->get('/client/tarefas')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('boards', 1)
                ->where('boards.0.cards_count', 1)
                ->where('boards.0.lists_count', 1));

        $props = $this->actingAs($user)
            ->withHeaders($this->partialHeaders('Client/Tasks/Index', 'boardPayloads', [
                'X-Task-Boards' => "{$foreign->id},{$mine->id}",
            ]))
            ->get('/client/tarefas')
            ->assertOk()
            ->json('props');

        $this->assertSame([$mine->id], array_column($props['boardPayloads'], 'id'));
        $this->assertSame(['Visível'], array_column($props['boardPayloads'][0]['lists'][0]['cards'], 'title'));
    }

    public function test_requested_board_ids_are_filtered_and_limited(): void
    {
        $this->assertSame([3, 1], BoardPresenter::requestedBoardIds(' 3, 1,3,x,-2,9', [1, 2, 3]));
        $this->assertSame([], BoardPresenter::requestedBoardIds(null, [1]));
        $this->assertSame([1, 2], BoardPresenter::requestedBoardIds('1,2,3', [1, 2, 3], 2));
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
