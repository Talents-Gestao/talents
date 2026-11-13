<?php

namespace Tests\Feature\Tasks;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Company;
use App\Models\TaskAttachment;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskChecklistItem;
use App\Models\TaskList;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Quadro INTERNO da Talents (sem company_id) com listas «company», compartilhado com uma empresa por convite
 * (membro do quadro) e por atribuição de cartões. Regra única em PortalTaskAccess.
 *
 * Cenários de cartão, no quadro interno «Interno compartilhado»:
 *  (1) company_id da empresa                       → visível;
 *  (2) sem company_id, lista company, inherit      → invisível (vazaria entre empresas);
 *  (3) sem company_id, visibility internal, atribuído ao convidado → invisível;
 *  (4) sem company_id, lista company, atribuído ao convidado → visível só para ele;
 *  (5) convidado (membro) × não-membro do quadro.
 */
class PortalTaskGuestAccessTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private TaskBoard $board;

    private TaskList $todo;

    private TaskList $doing;

    private TaskList $internalList;

    private TaskList $closedList;

    /** @var array<string, TaskCard> */
    private array $cards = [];

    private User $guest;

    private User $teamAssigned;

    private User $teamNotAssigned;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->company = $this->company('ESCRITORIO TESTE');
        $this->otherCompany = $this->company('OUTRA EMPRESA');

        $this->board = TaskBoard::query()->create(['company_id' => null, 'name' => 'Interno compartilhado', 'is_archived' => false]);
        $this->todo = $this->list($this->board, 'A fazer', 'company', true);
        $this->doing = $this->list($this->board, 'Em andamento', 'company', true);
        $this->closedList = $this->list($this->board, 'Só leitura', 'company', false);
        $this->internalList = $this->list($this->board, 'Interna', 'internal', false);

        $this->guest = User::factory()->companyAdmin($this->company->id)->create(['name' => 'Convidada']);
        $this->teamAssigned = User::factory()->superAdmin()->create(['name' => 'Equipe Atribuída']);
        $this->teamNotAssigned = User::factory()->superAdmin()->create(['name' => 'Equipe Sem Cartão']);
        $this->board->members()->attach($this->teamAssigned->id, ['role' => 'owner']);
        $this->board->members()->attach($this->teamNotAssigned->id, ['role' => 'editor']);

        $this->cards['company'] = $this->card($this->todo, 'Da empresa', $this->company->id);
        $this->cards['loose'] = $this->card($this->todo, 'Solto na lista company', null);
        $this->cards['internal_assigned'] = $this->card($this->todo, 'Interno atribuído', null, 'internal', [$this->guest]);
        $this->cards['assigned'] = $this->card($this->doing, 'Atribuído à convidada', null, 'inherit', [$this->guest, $this->teamAssigned]);
        $this->cards['assigned_internal_list'] = $this->card($this->internalList, 'Atribuído em lista interna', null, 'inherit', [$this->guest]);
        $this->cards['other_company'] = $this->card($this->todo, 'Da outra empresa', $this->otherCompany->id);
        $this->cards['other_assigned'] = $this->card($this->todo, 'Outra empresa atribuída', $this->otherCompany->id, 'inherit', [$this->guest]);

        // Quadro interno sem nada para a empresa e quadro interno de outra empresa: nunca listados.
        $empty = TaskBoard::query()->create(['company_id' => null, 'name' => 'Interno vazio', 'is_archived' => false]);
        $this->card($this->list($empty, 'Lista', 'company', true), 'Sem empresa', null);
    }

    private function company(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'tasks_access' => true,
            'strategic_calendar_access' => true,
        ]);
    }

    private function list(TaskBoard $board, string $name, string $visibility, bool $dropIn): TaskList
    {
        return TaskList::query()->create([
            'board_id' => $board->id,
            'name' => $name,
            'position' => (TaskList::query()->where('board_id', $board->id)->count() + 1) * 1000,
            'visibility' => $visibility,
            'allow_company_drop_in' => $dropIn,
            'is_archived' => false,
        ]);
    }

    /** @param  array<int, User>  $members */
    private function card(TaskList $list, string $title, ?int $companyId, string $visibility = 'inherit', array $members = []): TaskCard
    {
        $card = TaskCard::query()->create([
            'list_id' => $list->id,
            'company_id' => $companyId,
            'title' => $title,
            'position' => (TaskCard::query()->where('list_id', $list->id)->count() + 1) * 1000,
            'visibility' => $visibility,
            'is_archived' => false,
        ]);
        $card->members()->attach(collect($members)->pluck('id')->all());

        return $card;
    }

    private function invite(User $user, string $role, ?TaskBoard $board = null): void
    {
        ($board ?? $this->board)->members()->attach($user->id, ['role' => $role]);
    }

    /** @param  array<int, PermissionAction>  $actions */
    private function grant(User $user, array $actions): void
    {
        $workspace = $user->workspaces()->first();
        foreach ($actions as $action) {
            UserPermission::query()->create([
                'user_workspace_id' => $workspace->id,
                'module' => PermissionModule::Tarefas->value,
                'action' => $action->value,
            ]);
        }
    }

    /** @return array<int, string> títulos dos cartões no conteúdo do quadro (página cheia) */
    private function boardCardTitles(User $user, ?TaskBoard $board = null): array
    {
        $payload = $this->actingAs($user)
            ->get('/client/tarefas/'.($board ?? $this->board)->id)
            ->assertOk()
            ->inertiaProps('boardPayload');

        return collect($payload['lists'])->flatMap(fn ($l) => collect($l['cards'])->pluck('title'))->sort()->values()->all();
    }

    /** @return array<int, string> títulos no conteúdo sob demanda (recarga parcial boardPayloads) */
    private function lazyCardTitles(User $user, ?TaskBoard $board = null): array
    {
        $payloads = $this->actingAs($user)
            ->get('/client/tarefas', [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Client/Tasks/Index',
                'X-Inertia-Partial-Data' => 'boardPayloads',
                'X-Task-Boards' => (string) ($board ?? $this->board)->id,
            ])
            ->assertOk()
            ->json('props.boardPayloads');

        return collect($payloads)->flatMap(fn ($p) => collect($p['lists'])->flatMap(fn ($l) => collect($l['cards'])->pluck('title')))
            ->sort()->values()->all();
    }

    /** @return array<string, mixed> nome do quadro => resumo, na listagem */
    private function listedBoards(User $user): array
    {
        return collect($this->actingAs($user)->get('/client/tarefas')->assertOk()->inertiaProps('boards'))
            ->keyBy('name')->all();
    }

    // ── Visibilidade ────────────────────────────────────────────────────────

    public function test_invited_company_admin_sees_company_cards_and_cards_assigned_to_her_only(): void
    {
        $this->invite($this->guest, 'editor');
        $expected = ['Atribuído à convidada', 'Da empresa'];

        $boards = $this->listedBoards($this->guest);
        $this->assertSame(['Interno compartilhado'], array_keys($boards), 'Quadro interno sem nada visível não pode ser listado.');
        $this->assertSame(2, $boards['Interno compartilhado']['cards_count']);

        $this->assertSame($expected, $this->boardCardTitles($this->guest));
        $this->assertSame($expected, $this->lazyCardTitles($this->guest));
    }

    public function test_board_members_shown_are_only_people_assigned_to_visible_cards_and_without_email(): void
    {
        $this->invite($this->guest, 'editor');

        $payload = $this->actingAs($this->guest)->get('/client/tarefas/'.$this->board->id)->inertiaProps('boardPayload');

        $this->assertEqualsCanonicalizing(['Convidada', 'Equipe Atribuída'], collect($payload['members'])->pluck('name')->all());
        $json = json_encode($payload);
        $this->assertStringNotContainsString('Equipe Sem Cartão', $json);
        $this->assertStringNotContainsString($this->teamAssigned->email, $json);
        $this->assertStringNotContainsString('Solto na lista company', $json);
        $this->assertStringNotContainsString('Da outra empresa', $json);
    }

    public function test_company_admin_who_is_not_invited_sees_only_company_cards_and_no_empty_internal_boards(): void
    {
        $admin = User::factory()->companyAdmin($this->company->id)->create();

        $this->assertSame(['Interno compartilhado'], array_keys($this->listedBoards($admin)));
        $this->assertSame(['Da empresa'], $this->boardCardTitles($admin));
    }

    public function test_company_user_sees_only_cards_assigned_to_him_unless_invited(): void
    {
        $user = User::factory()->companyUser($this->company->id)->create();
        $this->grant($user, [PermissionAction::View]);

        $this->assertSame([], array_keys($this->listedBoards($user)));
        $this->actingAs($user)->get('/client/tarefas/'.$this->board->id)->assertForbidden();

        $this->cards['loose']->members()->attach($user->id);
        $this->assertSame(['Solto na lista company'], $this->boardCardTitles($user));
        $this->assertSame(['Solto na lista company'], $this->lazyCardTitles($user));

        $this->invite($user, 'viewer');
        $this->assertSame(['Da empresa', 'Solto na lista company'], $this->boardCardTitles($user));
    }

    public function test_invited_member_with_nothing_visible_sees_the_board_listed_but_empty(): void
    {
        $user = User::factory()->companyUser($this->company->id)->create();
        $this->grant($user, [PermissionAction::View]);
        $board = TaskBoard::query()->create(['company_id' => null, 'name' => 'Convite sem cartões', 'is_archived' => false]);
        $this->card($this->list($board, 'L', 'company', true), 'Interno', null);
        $this->invite($user, 'viewer', $board);

        $this->assertSame(0, $this->listedBoards($user)['Convite sem cartões']['cards_count']);
        $this->assertSame([], $this->boardCardTitles($user, $board));
    }

    public function test_user_of_another_company_never_sees_this_company_cards(): void
    {
        $other = User::factory()->companyAdmin($this->otherCompany->id)->create();

        $this->assertSame(['Da outra empresa', 'Outra empresa atribuída'], $this->boardCardTitles($other));
        $this->assertSame(['Da outra empresa', 'Outra empresa atribuída'], $this->lazyCardTitles($other));
    }

    public function test_user_without_the_tasks_module_is_refused(): void
    {
        $this->company->update(['tasks_access' => false]);
        $this->invite($this->guest, 'editor');

        $this->actingAs($this->guest)->get('/client/tarefas')->assertForbidden();
        $this->actingAs($this->guest)->get('/client/tarefas/'.$this->board->id)->assertForbidden();
        $this->actingAs($this->guest)
            ->patch(route('client.tarefas.cards.update', $this->cards['company']), ['title' => 'x'])
            ->assertForbidden();
    }

    public function test_assigning_a_card_or_creating_one_for_the_company_makes_it_visible(): void
    {
        $this->invite($this->guest, 'editor');

        $this->cards['loose']->members()->attach($this->guest->id);
        $this->card($this->todo, 'Novo para a empresa', $this->company->id);

        $this->assertSame(['Atribuído à convidada', 'Da empresa', 'Novo para a empresa', 'Solto na lista company'], $this->boardCardTitles($this->guest));
    }

    public function test_two_internal_boards_shared_with_different_companies_do_not_leak(): void
    {
        $boardB = TaskBoard::query()->create(['company_id' => null, 'name' => 'Interno da outra', 'is_archived' => false]);
        $listB = $this->list($boardB, 'A fazer', 'company', true);
        $this->card($listB, 'Tarefa B', $this->otherCompany->id);
        $this->card($listB, 'Rascunho sem empresa', null);
        $otherGuest = User::factory()->companyAdmin($this->otherCompany->id)->create();
        $this->invite($otherGuest, 'editor', $boardB);
        $this->invite($this->guest, 'editor');

        $this->assertSame(['Interno compartilhado'], array_keys($this->listedBoards($this->guest)));
        $this->actingAs($this->guest)->get('/client/tarefas/'.$boardB->id)->assertForbidden();
        $this->assertSame(['Tarefa B'], $this->boardCardTitles($otherGuest, $boardB));
        $this->assertNotContains('Da empresa', $this->boardCardTitles($otherGuest));
        $this->assertNotContains('Atribuído à convidada', $this->boardCardTitles($otherGuest));
    }

    public function test_home_lists_the_pending_cards_assigned_to_the_user_including_cards_without_company(): void
    {
        $this->invite($this->guest, 'editor');

        $titles = collect($this->actingAs($this->guest)->get(route('client.dashboard'))->assertOk()->inertiaProps('pendingTasks'))
            ->pluck('title')->all();

        $this->assertSame(['Atribuído à convidada'], $titles);
    }

    // ── Ações por papel ─────────────────────────────────────────────────────

    /** @return array<string, array{0: string, 1: bool, 2: bool}> papel => [papel, comenta, edita] */
    public static function roles(): array
    {
        return [
            'Leitor' => ['viewer', false, false],
            'Comentarista' => ['commenter', true, false],
            'Editor' => ['editor', true, true],
            'Dono' => ['owner', true, true],
        ];
    }

    /** Cartão visível e NÃO atribuído ao convidado: o papel no quadro decide (atribuição: PortalTaskActionMatrixTest). */
    #[DataProvider('roles')]
    public function test_invited_role_limits_what_the_guest_does_on_visible_cards_not_assigned_to_her(string $role, bool $comments, bool $edits): void
    {
        Storage::fake('public');
        $this->invite($this->guest, $role);
        $card = $this->cards['company'];
        $item = TaskChecklistItem::query()->create([
            'task_checklist_id' => $card->checklists()->create(['name' => 'Etapas', 'position' => 1000])->id,
            'text' => 'Etapa', 'position' => 1000, 'is_completed' => false,
        ]);
        $attachment = TaskAttachment::query()->create([
            'task_card_id' => $card->id, 'disk' => 'public', 'path' => 'x/a.pdf', 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 1,
        ]);

        $payload = $this->actingAs($this->guest)->get('/client/tarefas/'.$this->board->id)->inertiaProps('boardPayload');
        $flags = collect($payload['lists'])->flatMap(fn ($l) => $l['cards'])->firstWhere('id', $card->id)['portal_can'];
        $this->assertSame(['comment' => $comments, 'edit' => $edits, 'checklist' => $edits, 'move' => $edits, 'attach' => $edits, 'remove_attachment' => $edits], $flags);

        $status = fn ($response) => $response->status();
        $ok = fn (bool $allowed) => $allowed ? 302 : 403;

        $this->assertSame($ok($comments), $status($this->actingAs($this->guest)->post(route('client.tarefas.cards.comentarios.store', $card), ['body' => 'Oi'])));
        $this->assertSame($ok($edits), $status($this->actingAs($this->guest)->patch(route('client.tarefas.cards.update', $card), ['title' => 'Novo título'])));
        $this->assertSame($ok($edits), $status($this->actingAs($this->guest)->patch(route('client.tarefas.checklist-itens.update', $item), ['is_completed' => true])));
        $this->assertSame($ok($edits), $status($this->actingAs($this->guest)->post(route('client.tarefas.cards.anexos.store', $card), ['file' => UploadedFile::fake()->create('b.pdf', 1, 'application/pdf')])));
        $this->assertSame($ok($edits), $status($this->actingAs($this->guest)->delete(route('client.tarefas.anexos.destroy', $attachment))));
        $this->assertSame($ok($edits), $status($this->actingAs($this->guest)->post(route('client.tarefas.cards.move', $card), ['list_id' => $this->doing->id, 'position' => 500])));

        $card->refresh();
        $this->assertSame($edits ? 'Novo título' : 'Da empresa', $card->title);
        $this->assertSame($edits ? $this->doing->id : $this->todo->id, (int) $card->list_id);
        $this->assertSame($edits, (bool) $item->fresh()->is_completed);
    }

    public function test_guest_never_archives_deletes_invites_shares_or_creates_lists(): void
    {
        $this->invite($this->guest, 'owner');
        $card = $this->cards['assigned'];

        $this->actingAs($this->guest)->patch(route('client.tarefas.cards.update', $card), ['is_archived' => true, 'company_id' => $this->otherCompany->id, 'visibility' => 'internal']);
        $card->refresh();
        $this->assertFalse($card->is_archived);
        $this->assertNull($card->company_id);
        $this->assertSame('inherit', $card->visibility);

        $attempts = [
            fn () => $this->post(route('admin.tarefas.cards.arquivar', $card)),
            fn () => $this->delete(route('admin.tarefas.cards.destroy', $card)),
            fn () => $this->post(route('admin.tarefas.quadros.membros.store', $this->board), ['user_id' => $this->guest->id, 'role' => 'owner']),
            fn () => $this->post(route('admin.tarefas.quadros.partilhar-cartoes', $this->board), ['company_id' => $this->company->id]),
            fn () => $this->post(route('admin.tarefas.quadros.listas.store', $this->board), ['name' => 'Nova', 'visibility' => 'company']),
        ];
        foreach ($attempts as $attempt) {
            $this->actingAs($this->guest);
            $response = $attempt();
            $this->assertFalse($response->isSuccessful(), 'Ação do Admin aceita para o portal.');
        }

        $this->assertNotNull($card->fresh());
        $this->assertFalse($card->fresh()->is_archived);
        $this->assertSame(4, TaskList::query()->where('board_id', $this->board->id)->count());
        $this->assertNull($this->cards['loose']->fresh()->company_id);
    }

    public function test_editor_cannot_move_into_a_list_that_does_not_accept_company_cards(): void
    {
        $this->invite($this->guest, 'editor');

        foreach ([$this->closedList, $this->internalList] as $target) {
            $this->actingAs($this->guest)
                ->post(route('client.tarefas.cards.move', $this->cards['assigned']), ['list_id' => $target->id, 'position' => 1])
                ->assertForbidden();
        }
        $this->assertSame($this->doing->id, (int) $this->cards['assigned']->fresh()->list_id);
    }

    public function test_module_permission_and_role_intersect_with_the_most_restrictive_winning(): void
    {
        $user = User::factory()->companyUser($this->company->id)->create();
        $this->grant($user, [PermissionAction::View]);
        $this->invite($user, 'editor');
        $card = $this->cards['company'];

        $this->actingAs($user)->patch(route('client.tarefas.cards.update', $card), ['title' => 'x'])->assertForbidden();
        $this->actingAs($user)->post(route('client.tarefas.cards.comentarios.store', $card), ['body' => 'x'])->assertForbidden();
        $flags = collect($this->actingAs($user)->get('/client/tarefas/'.$this->board->id)->inertiaProps('boardPayload.lists'))
            ->flatMap(fn ($l) => $l['cards'])->firstWhere('id', $card->id)['portal_can'];
        $this->assertFalse($flags['edit']);
        $this->assertFalse($flags['comment']);

        $this->grant($user, [PermissionAction::Create, PermissionAction::Edit]);
        $this->actingAs($user)->patch(route('client.tarefas.cards.update', $card), ['title' => 'Editado'])->assertRedirect();
        $this->assertSame('Editado', $card->fresh()->title);
    }

    // ── Acesso direto por URL/ID ────────────────────────────────────────────

    public function test_direct_requests_to_cards_and_attachments_the_user_does_not_see_are_refused(): void
    {
        Storage::fake('public');
        $this->invite($this->guest, 'owner');

        foreach (['loose', 'internal_assigned', 'assigned_internal_list', 'other_company', 'other_assigned'] as $key) {
            $card = $this->cards[$key];
            $item = TaskChecklistItem::query()->create([
                'task_checklist_id' => $card->checklists()->create(['name' => 'C', 'position' => 1])->id,
                'text' => 'Etapa', 'position' => 1, 'is_completed' => false,
            ]);
            $attachment = TaskAttachment::query()->create([
                'task_card_id' => $card->id, 'disk' => 'public', 'path' => "x/{$key}.pdf", 'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 1,
            ]);

            $this->actingAs($this->guest)->patch(route('client.tarefas.cards.update', $card), ['title' => 'x'])->assertForbidden();
            $this->actingAs($this->guest)->post(route('client.tarefas.cards.comentarios.store', $card), ['body' => 'x'])->assertForbidden();
            $this->actingAs($this->guest)->post(route('client.tarefas.cards.move', $card), ['list_id' => $this->todo->id, 'position' => 1])->assertForbidden();
            $this->actingAs($this->guest)->patch(route('client.tarefas.checklist-itens.update', $item), ['is_completed' => true])->assertForbidden();
            $this->actingAs($this->guest)->post(route('client.tarefas.cards.anexos.store', $card), ['file' => UploadedFile::fake()->create('b.pdf', 1, 'application/pdf')])->assertForbidden();
            $this->actingAs($this->guest)->delete(route('client.tarefas.anexos.destroy', $attachment))->assertForbidden();
            $this->actingAs($this->guest)->patch(route('client.strategic-calendar.toggle-task-completion', $card))->assertNotFound();

            $this->assertNotSame('x', $card->fresh()->title, $key);
            $this->assertNotNull($attachment->fresh(), $key);
        }
    }

    public function test_viewer_cannot_complete_a_visible_card_not_assigned_to_her_from_the_calendar(): void
    {
        $this->invite($this->guest, 'viewer');

        $this->actingAs($this->guest)
            ->patch(route('client.strategic-calendar.toggle-task-completion', $this->cards['company']))
            ->assertForbidden();
        $this->assertNull($this->cards['company']->fresh()->completed_at);
    }
}
