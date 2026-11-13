<?php

// Cartão do quadro SUPORTE: «TAREFAS» (quadros, listas, cartões, checklist, membros, favoritos, partilhar com empresa,
// visibilidade 7.9.3 e melhorias recentes).
// Já cobertos: Tasks/TaskModuleTest (quadros, listas, cartões, checklist, membros, partilha, arquivar, recorrência),
// Tasks/ClientTaskVisibilityTest (7.9.3), Tasks/TaskBoardUnifiedScreenTest (quadros listados com resumo e carga sob
// demanda), Tasks/CompanyTaskAccessPanelTest (acesso da empresa) e tests/JavaScript/taskBoardIndex.test.mjs
// (descrição oculta com expandir, salvar e fechar o modal). Aqui: favoritos de cada usuário.

namespace Tests\Feature\Acceptance\Support;

use App\Models\TaskBoard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_starring_a_board_is_personal_and_can_be_undone(): void
    {
        $this->withoutVite();
        $board = TaskBoard::query()->create(['name' => 'Operação', 'company_id' => null, 'is_archived' => false]);
        $me = User::factory()->talentsOwner()->create();
        $colleague = User::factory()->talentsOwner()->create();

        $starred = function (User $user) use ($board): bool {
            $boards = $this->actingAs($user)->get(route('admin.tarefas.quadros.index'))
                ->assertOk()
                ->inertiaProps('boards');

            return (bool) collect($boards)->firstWhere('id', $board->id)['is_starred'];
        };

        $this->actingAs($me)->post(route('admin.tarefas.quadros.favoritar', $board))->assertRedirect();
        $this->assertTrue($starred($me));
        $this->assertFalse($starred($colleague));

        $this->actingAs($me)->delete(route('admin.tarefas.quadros.desfavoritar', $board))->assertRedirect();
        $this->assertFalse($starred($me));
    }
}
