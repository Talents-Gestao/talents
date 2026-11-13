<?php

namespace Tests\Feature\Tasks;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\TaskActivityLog;
use App\Models\TaskAttachment;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\TaskChecklist;
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
 * Ações do portal em Tarefas (PortalTaskAccess): atribuído ao cartão edita naquele cartão, seja qual for o papel no
 * quadro; não atribuído segue o papel (Dono/Editor editam, Comentarista comenta, Leitor lê, não-membro comenta);
 * sempre cruzado com a permissão do módulo. Arquivar e excluir nunca são do portal.
 */
class PortalTaskActionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private TaskBoard $board;

    private TaskList $todo;

    private TaskList $doing;

    private TaskCard $card;

    private TaskCard $otherCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');

        $this->company = Company::query()->create([
            'name' => 'ESCRITORIO TESTE',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
            'tasks_access' => true,
            'strategic_calendar_access' => true,
        ]);

        $this->board = TaskBoard::query()->create(['company_id' => null, 'name' => 'Interno compartilhado', 'is_archived' => false]);
        $this->todo = $this->list('A fazer');
        $this->doing = $this->list('Em andamento');

        $this->card = $this->card('Cartão A', $this->company->id);
        $this->otherCard = $this->card('Cartão B', $this->company->id);
    }

    private function list(string $name): TaskList
    {
        return TaskList::query()->create([
            'board_id' => $this->board->id,
            'name' => $name,
            'position' => (TaskList::query()->where('board_id', $this->board->id)->count() + 1) * 1000,
            'visibility' => 'company',
            'allow_company_drop_in' => true,
            'is_archived' => false,
        ]);
    }

    private function card(string $title, ?int $companyId): TaskCard
    {
        return TaskCard::query()->create([
            'list_id' => $this->todo->id,
            'company_id' => $companyId,
            'title' => $title,
            'position' => (TaskCard::query()->where('list_id', $this->todo->id)->count() + 1) * 1000,
            'visibility' => 'inherit',
            'is_archived' => false,
        ]);
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

    private function portalUser(string $profile, array $actions = [PermissionAction::View, PermissionAction::Create, PermissionAction::Edit, PermissionAction::Delete]): User
    {
        if ($profile === 'company_admin') {
            return User::factory()->companyAdmin($this->company->id)->create();
        }

        $user = User::factory()->companyUser($this->company->id)->create();
        $this->grant($user, $actions);

        return $user;
    }

    private function checklistItem(TaskCard $card): TaskChecklistItem
    {
        $checklist = $card->checklists()->create(['name' => 'Etapas', 'position' => 1000]);

        return TaskChecklistItem::query()->create([
            'task_checklist_id' => $checklist->id,
            'text' => 'Etapa', 'position' => 1000, 'is_completed' => false,
        ]);
    }

    private function attachment(TaskCard $card): TaskAttachment
    {
        return TaskAttachment::query()->create([
            'task_card_id' => $card->id, 'disk' => 'public', 'path' => 'x/'.$card->id.'.pdf',
            'original_name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 1,
        ]);
    }

    /**
     * Executa cada ação do portal no cartão e devolve o status HTTP de cada uma.
     *
     * @return array<string, int>
     */
    private function attemptAll(User $user, TaskCard $card): array
    {
        $item = $this->checklistItem($card);
        $attachment = $this->attachment($card);
        $this->actingAs($user);

        return [
            'edit' => $this->patch(route('client.tarefas.cards.update', $card), ['title' => 'Título novo', 'due_date' => '2026-12-01'])->status(),
            'complete' => $this->patch(route('client.tarefas.cards.update', $card), ['complete' => true])->status(),
            'checklist_create' => $this->post(route('client.tarefas.checklists.itens.store', $item->task_checklist_id), ['text' => 'Etapa nova'])->status(),
            'checklist_mark' => $this->patch(route('client.tarefas.checklist-itens.update', $item), ['is_completed' => true, 'text' => 'Etapa editada'])->status(),
            'attach' => $this->post(route('client.tarefas.cards.anexos.store', $card), ['file' => UploadedFile::fake()->create('b.pdf', 1, 'application/pdf')])->status(),
            'remove_attachment' => $this->delete(route('client.tarefas.anexos.destroy', $attachment))->status(),
            'comment' => $this->post(route('client.tarefas.cards.comentarios.store', $card), ['body' => 'Comentário'])->status(),
            'move' => $this->post(route('client.tarefas.cards.move', $card), ['list_id' => $this->doing->id, 'position' => 500])->status(),
        ];
    }

    /**
     * company_admin e company_user × atribuído ou não × papel no quadro (null = não-membro).
     *
     * @return array<string, array{0: string, 1: bool, 2: ?string, 3: bool, 4: bool, 5: bool}>
     *                                                                                         perfil, atribuído, papel, vê, comenta, edita
     */
    public static function matrix(): array
    {
        $cases = [];
        foreach (['company_admin', 'company_user'] as $profile) {
            foreach ([true, false] as $assigned) {
                foreach (['viewer' => 'Leitor', 'commenter' => 'Comentarista', 'editor' => 'Editor', 'owner' => 'Dono', '' => 'não-membro'] as $role => $label) {
                    $role = $role === '' ? null : $role;
                    // company_user fora do quadro e sem atribuição não vê o cartão da empresa.
                    $sees = $assigned || $role !== null || $profile === 'company_admin';
                    $edits = $sees && ($assigned || in_array($role, ['editor', 'owner'], true));
                    $comments = $sees && ($edits || $role !== 'viewer');
                    $name = sprintf('%s %s %s', $profile, $assigned ? 'atribuído' : 'não atribuído', $label);
                    $cases[$name] = [$profile, $assigned, $role, $sees, $comments, $edits];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_action_matrix(string $profile, bool $assigned, ?string $role, bool $sees, bool $comments, bool $edits): void
    {
        $user = $this->portalUser($profile);
        if ($role !== null) {
            $this->board->members()->attach($user->id, ['role' => $role]);
        }
        if ($assigned) {
            $this->card->members()->attach($user->id);
        }

        if ($sees) {
            $payload = $this->actingAs($user)->get('/client/tarefas/'.$this->board->id)->assertOk()->inertiaProps('boardPayload');
            $flags = collect($payload['lists'])->flatMap(fn ($l) => $l['cards'])->firstWhere('id', $this->card->id)['portal_can'];
            $this->assertSame(
                ['comment' => $comments, 'edit' => $edits, 'checklist' => $edits, 'move' => $edits, 'attach' => $edits, 'remove_attachment' => $edits],
                $flags,
            );
        }

        $statuses = $this->attemptAll($user, $this->card);
        $ok = fn (bool $allowed) => $allowed ? 302 : 403;
        $this->assertSame([
            'edit' => $ok($edits),
            'complete' => $ok($edits),
            'checklist_create' => $ok($edits),
            'checklist_mark' => $ok($edits),
            'attach' => $ok($edits),
            'remove_attachment' => $ok($edits),
            'comment' => $ok($comments),
            'move' => $ok($edits),
        ], $statuses);

        $card = $this->card->fresh();
        $this->assertSame($edits ? 'Título novo' : 'Cartão A', $card->title);
        $this->assertSame($edits, $card->completed_at !== null);
        $this->assertSame($edits ? $this->doing->id : $this->todo->id, (int) $card->list_id);
        $this->assertSame($edits ? 2 : 1, TaskChecklistItem::query()->count());
        $this->assertSame($comments ? 1 : 0, $card->comments()->count());

        // Arquivar e excluir: nunca pelo portal, qualquer que seja a combinação.
        $this->actingAs($user);
        $this->assertFalse($this->post(route('admin.tarefas.cards.arquivar', $card))->isSuccessful());
        $this->assertFalse($this->delete(route('admin.tarefas.cards.destroy', $card))->isSuccessful());
        $this->assertNotNull($card->fresh());
        $this->assertFalse($card->fresh()->is_archived);
    }

    public function test_viewer_assigned_to_a_card_edits_it(): void
    {
        $user = $this->portalUser('company_admin');
        $this->board->members()->attach($user->id, ['role' => 'viewer']);
        $this->card->members()->attach($user->id);

        $this->actingAs($user)->patch(route('client.tarefas.cards.update', $this->card), ['title' => 'Editado pela Leitora'])->assertRedirect();
        $this->assertSame('Editado pela Leitora', $this->card->fresh()->title);
    }

    public function test_viewer_not_assigned_does_not_edit(): void
    {
        $user = $this->portalUser('company_admin');
        $this->board->members()->attach($user->id, ['role' => 'viewer']);

        $this->actingAs($user)->patch(route('client.tarefas.cards.update', $this->card), ['title' => 'x'])->assertForbidden();
        $this->actingAs($user)->post(route('client.tarefas.cards.comentarios.store', $this->card), ['body' => 'x'])->assertForbidden();
        $this->assertSame('Cartão A', $this->card->fresh()->title);
    }

    public function test_assignment_to_card_a_grants_nothing_on_card_b(): void
    {
        foreach (['company_admin', 'company_user'] as $profile) {
            $user = $this->portalUser($profile);
            $this->board->members()->attach($user->id, ['role' => 'viewer']);
            $this->card->members()->attach($user->id);

            $statuses = $this->attemptAll($user, $this->otherCard);
            $this->assertSame(array_fill_keys(array_keys($statuses), 403), $statuses, $profile);
            $this->assertSame('Cartão B', $this->otherCard->fresh()->title);

            $flags = collect($this->actingAs($user)->get('/client/tarefas/'.$this->board->id)->inertiaProps('boardPayload.lists'))
                ->flatMap(fn ($l) => $l['cards'])->keyBy('title');
            $this->assertTrue($flags['Cartão A']['portal_can']['edit'], $profile);
            $this->assertFalse($flags['Cartão B']['portal_can']['edit'], $profile);
            $this->assertFalse($flags['Cartão B']['portal_can']['comment'], $profile);

            $this->board->members()->detach($user->id);
            $this->card->members()->detach($user->id);
        }
    }

    public function test_assigned_user_without_tasks_edit_does_not_edit(): void
    {
        $user = $this->portalUser('company_user', [PermissionAction::View, PermissionAction::Create]);
        $this->board->members()->attach($user->id, ['role' => 'owner']);
        $this->card->members()->attach($user->id);

        $statuses = $this->attemptAll($user, $this->card);

        $this->assertSame(302, $statuses['comment']);
        unset($statuses['comment']);
        $this->assertSame(array_fill_keys(array_keys($statuses), 403), $statuses);
        $this->assertSame('Cartão A', $this->card->fresh()->title);
    }

    public function test_assigned_user_without_the_module_does_not_edit(): void
    {
        $user = $this->portalUser('company_admin');
        $this->card->members()->attach($user->id);
        $this->company->update(['tasks_access' => false]);

        $this->actingAs($user)->patch(route('client.tarefas.cards.update', $this->card), ['title' => 'x'])->assertForbidden();
        $this->assertSame('Cartão A', $this->card->fresh()->title);
    }

    public function test_assigned_card_hidden_by_visibility_rules_stays_refused(): void
    {
        $user = $this->portalUser('company_admin');
        $internal = $this->card('Interno atribuído', null);
        $internal->update(['visibility' => 'internal']);
        $internal->members()->attach($user->id);
        $otherCompany = Company::query()->create(['name' => 'OUTRA', 'is_active' => true, 'complaints_public_token' => (string) Str::uuid(), 'tasks_access' => true]);
        $foreign = $this->card('De outra empresa', $otherCompany->id);
        $foreign->members()->attach($user->id);

        foreach ([$internal, $foreign] as $card) {
            $statuses = $this->attemptAll($user, $card);
            $this->assertSame(array_fill_keys(array_keys($statuses), 403), $statuses, $card->title);
            $this->assertSame(
                1,
                TaskChecklistItem::query()->whereIn('task_checklist_id', TaskChecklist::query()->where('task_card_id', $card->id)->pluck('id'))->count(),
                'Item novo criado em cartão que o usuário não vê.',
            );
            $this->assertSame(0, $card->comments()->count());
        }
    }

    public function test_portal_cannot_change_visibility_company_or_members_of_its_own_card(): void
    {
        $user = $this->portalUser('company_admin');
        $colleague = User::factory()->companyUser($this->company->id)->create();
        $this->card->members()->attach($user->id);

        $this->actingAs($user)->patch(route('client.tarefas.cards.update', $this->card), [
            'title' => 'Ok',
            'visibility' => 'internal',
            'company_id' => null,
            'member_ids' => [$colleague->id],
            'is_archived' => true,
            'recurrence' => 'weekly',
        ])->assertRedirect();

        $card = $this->card->fresh();
        $this->assertSame('Ok', $card->title);
        $this->assertSame('inherit', $card->visibility);
        $this->assertSame($this->company->id, (int) $card->company_id);
        $this->assertFalse($card->is_archived);
        $this->assertNull($card->recurrence);
        $this->assertSame([$user->id], $card->members()->pluck('users.id')->all());
    }

    public function test_assigned_viewer_completes_her_card_from_the_calendar_but_not_another(): void
    {
        $user = $this->portalUser('company_admin');
        $this->board->members()->attach($user->id, ['role' => 'viewer']);
        $this->card->members()->attach($user->id);

        $this->actingAs($user)->patch(route('client.strategic-calendar.toggle-task-completion', $this->card))->assertRedirect();
        $this->assertNotNull($this->card->fresh()->completed_at);

        $this->actingAs($user)->patch(route('client.strategic-calendar.toggle-task-completion', $this->otherCard))->assertForbidden();
        $this->assertNull($this->otherCard->fresh()->completed_at);
    }

    public function test_portal_actions_are_logged_with_the_author(): void
    {
        $user = $this->portalUser('company_user');
        $this->board->members()->attach($user->id, ['role' => 'viewer']);
        $this->card->members()->attach($user->id);

        $this->attemptAll($user, $this->card);

        $actions = TaskActivityLog::query()
            ->where('task_card_id', $this->card->id)
            ->where('actor_user_id', $user->id)
            ->pluck('action')
            ->all();

        foreach (['card.updated', 'card.completed', 'checklist_item.created', 'checklist_item.updated', 'attachment.added', 'attachment.removed', 'comment.created', 'card.moved'] as $expected) {
            $this->assertContains($expected, $actions, "Ação «{$expected}» sem registro do autor.");
        }
    }
}
