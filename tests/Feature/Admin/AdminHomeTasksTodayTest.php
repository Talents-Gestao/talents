<?php

namespace Tests\Feature\Admin;

use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use App\Models\TaskList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * «Tarefas · hoje» da Home do Admin: cartões e etapas de checklist que vencem hoje (America/Sao_Paulo).
 */
class AdminHomeTasksTodayTest extends TestCase
{
    use RefreshDatabase;

    private TaskList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $board = TaskBoard::query()->create(['name' => 'Quadro Admin', 'company_id' => null, 'is_archived' => false]);
        $this->list = TaskList::query()->create([
            'board_id' => $board->id,
            'name' => 'A fazer',
            'position' => 1000,
            'is_archived' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function card(string $title, ?string $dueDate, array $extra = []): TaskCard
    {
        return TaskCard::query()->create([
            'list_id' => $this->list->id,
            'title' => $title,
            'position' => 1000,
            'due_date' => $dueDate,
            'is_archived' => false,
            ...$extra,
        ]);
    }

    private function step(TaskCard $card, string $text, ?string $dueDate, array $extra = []): TaskChecklistItem
    {
        $checklist = TaskChecklist::query()->firstOrCreate(
            ['task_card_id' => $card->id, 'name' => 'Etapas'],
            ['position' => 1000],
        );

        return TaskChecklistItem::query()->create([
            'task_checklist_id' => $checklist->id,
            'text' => $text,
            'position' => 1000,
            'due_date' => $dueDate,
            ...$extra,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function tasksTodayFor(User $user): array
    {
        $tasks = null;
        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$tasks) {
                $page->component('Admin/Dashboard');
                $tasks = $page->toArray()['props']['tasksToday'];
            });

        return $tasks;
    }

    public function test_card_due_today_step_due_today_and_items_without_due_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-12 10:00', 'America/Sao_Paulo'));
        $me = User::factory()->superAdmin()->create(['is_owner' => true]);
        $other = User::factory()->superAdmin()->create();
        $today = '2026-11-12';

        // (a) cartão com prazo hoje: membro e criador entram; de outra pessoa, não.
        $asMember = $this->card('Cartão hoje (membro)', $today);
        $asMember->members()->attach($me->id);
        $this->card('Cartão hoje (criador)', $today, ['created_by_user_id' => $me->id]);
        $this->card('Cartão hoje de outro', $today)->members()->attach($other->id);

        // (b) etapa com prazo hoje e eu como responsável, num cartão sem prazo do qual não sou membro.
        $planning = $this->card('Planejar atividade', null);
        $planning->members()->attach($other->id);
        $this->step($planning, 'Enviar convite', $today, ['assignee_user_id' => $me->id]);
        $this->step($planning, 'Etapa de outro', $today, ['assignee_user_id' => $other->id]);
        $this->step($planning, 'Etapa concluída', $today, ['assignee_user_id' => $me->id, 'is_completed' => true]);

        // Etapa com prazo hoje em cartão do qual sou membro (sem responsável): entra no mesmo cartão, sem duplicar.
        $this->step($asMember, 'Revisar texto', $today);

        // (c) sem prazo: não entram.
        $noDue = $this->card('Cartão sem prazo', null);
        $noDue->members()->attach($me->id);
        $this->step($noDue, 'Etapa sem prazo', null, ['assignee_user_id' => $me->id]);

        // Arquivado e concluído não entram, nem as etapas deles.
        $archived = $this->card('Arquivado', $today, ['is_archived' => true]);
        $archived->members()->attach($me->id);
        $this->step($archived, 'Etapa de arquivado', $today, ['assignee_user_id' => $me->id]);
        $this->card('Concluído', $today, ['completed_at' => now()])->members()->attach($me->id);

        $tasks = collect($this->tasksTodayFor($me))->keyBy('title');

        $this->assertEqualsCanonicalizing(
            ['Cartão hoje (membro)', 'Cartão hoje (criador)', 'Planejar atividade'],
            $tasks->keys()->all(),
        );
        $this->assertSame(['Revisar texto'], array_column($tasks['Cartão hoje (membro)']['steps'], 'text'));
        $this->assertSame(['Enviar convite'], array_column($tasks['Planejar atividade']['steps'], 'text'));
        $this->assertSame($today, $tasks['Planejar atividade']['due_date']);
        $this->assertSame(
            route('admin.tarefas.quadros.show', ['board' => $this->list->board_id, 'cartao' => $planning->id]),
            $tasks['Planejar atividade']['href'],
        );
    }

    public function test_today_uses_sao_paulo_date_near_midnight(): void
    {
        // 22h em São Paulo já é dia 13 em UTC: "hoje" continua sendo 12.
        Carbon::setTestNow(Carbon::parse('2026-11-12 22:00', 'America/Sao_Paulo'));
        $me = User::factory()->superAdmin()->create(['is_owner' => true]);

        $card = $this->card('Cartão', null);
        $card->members()->attach($me->id);
        $this->step($card, 'Vence hoje', '2026-11-12', ['assignee_user_id' => $me->id]);
        $this->step($card, 'Vence amanhã', '2026-11-13', ['assignee_user_id' => $me->id]);
        $this->card('Cartão de amanhã', '2026-11-13')->members()->attach($me->id);

        $tasks = $this->tasksTodayFor($me);

        $this->assertCount(1, $tasks);
        $this->assertSame('Cartão', $tasks[0]['title']);
        $this->assertSame(['Vence hoje'], array_column($tasks[0]['steps'], 'text'));
    }
}
