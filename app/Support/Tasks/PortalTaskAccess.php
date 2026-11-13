<?php

namespace App\Support\Tasks;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\TaskBoardMemberRole;
use App\Models\TaskBoard;
use App\Models\TaskCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Fonte única do que um usuário do portal vê e pode fazer em Tarefas (listagem, resumo, conteúdo sob demanda,
 * modal, ações, Home). Usada pelas policies de quadro e cartão e pelo BoardPresenter.
 *
 * Cartão visível ao usuário U da empresa C:
 * - não arquivado, em lista e quadro não arquivados, quadro interno ou da própria C;
 * - visibilidade do cartão `company` ou `inherit` (`internal` nunca aparece, nem atribuído);
 * - o cartão é da empresa (`company_id` = C, em qualquer lista, regra 7.9.3) OU, sem empresa (`company_id` nulo),
 *   está numa lista `company` e U é membro do cartão (atribuído). Cartão sem empresa nunca aparece só por estar em
 *   lista `company`: num quadro interno partilhado com várias empresas, vazaria entre elas;
 * - `company_user` fora dos membros do quadro vê só os cartões em que é membro.
 *
 * Quadro listado: quadro da própria empresa (administrador da empresa); OU U é membro (convidado) do quadro; OU há
 * pelo menos um cartão visível a U. Quadro interno sem nada disso não aparece.
 *
 * O que U pode fazer num cartão visível, sempre o mais restritivo entre esta regra e a permissão do módulo:
 * - U atribuído ao cartão (task_card_members): edita título, descrição, datas e conclusão, cria/edita/marca itens de
 *   checklist, anexa e remove anexos, comenta e move entre listas da empresa com drop-in, seja qual for o papel no
 *   quadro (inclusive Leitor e Comentarista). Vale só para aquele cartão;
 * - U não atribuído: o papel no quadro decide (Dono/Editor editam; Comentarista só comenta; Leitor só lê); quem vê o
 *   cartão sem ser membro do quadro só comenta.
 * Arquivar, excluir, mudar visibilidade, convidar, partilhar, criar listas ou quadros e atribuir membros ao cartão
 * nunca são do portal (rotas só do Admin).
 */
final class PortalTaskAccess
{
    public const READ = 'read';

    public const COMMENT = 'comment';

    public const EDIT = 'edit';

    private const LEVELS = [self::READ => 0, self::COMMENT => 1, self::EDIT => 2];

    public static function companyId(User $user): int
    {
        return (int) ($user->contextCompanyId() ?? $user->company_id);
    }

    public static function moduleEnabled(User $user): bool
    {
        return ! $user->isSuperAdmin()
            && $user->belongsToCompany()
            && (bool) $user->contextCompany()?->hasTasksEnabled();
    }

    /**
     * Restringe uma consulta de cartões aos que este usuário do portal vê.
     */
    public static function scopeCards(Builder $cards, User $user): Builder
    {
        $companyId = self::companyId($user);
        $userId = (int) $user->id;
        $isMember = fn (Builder $m) => $m->where('users.id', $userId);

        $cards->where('task_cards.is_archived', false)
            ->whereIn('task_cards.visibility', ['company', 'inherit'])
            ->whereHas('list', fn (Builder $l) => $l->where('is_archived', false)
                ->whereHas('board', fn (Builder $b) => $b->where('is_archived', false)
                    ->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))))
            ->where(fn (Builder $q) => $q
                ->where('task_cards.company_id', $companyId)
                ->orWhere(fn (Builder $assigned) => $assigned
                    ->whereNull('task_cards.company_id')
                    ->whereHas('list', fn (Builder $l) => $l->where('visibility', 'company'))
                    ->whereHas('members', $isMember)));

        if (! $user->isCompanyAdmin()) {
            $cards->where(fn (Builder $q) => $q
                ->whereHas('members', $isMember)
                ->orWhereHas('list.board.members', $isMember));
        }

        return $cards;
    }

    /**
     * Quadros que aparecem no portal para este usuário (sem checar o módulo; ver canSeeBoard).
     */
    public static function boardsQuery(User $user): Builder
    {
        $companyId = self::companyId($user);
        $userId = (int) $user->id;

        return TaskBoard::query()
            ->where('is_archived', false)
            ->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->where(function (Builder $q) use ($user, $companyId, $userId) {
                if ($user->isCompanyAdmin()) {
                    $q->orWhere('company_id', $companyId);
                }
                $q->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $userId))
                    ->orWhereHas('lists.cards', fn (Builder $c) => self::scopeCards($c, $user));
            });
    }

    public static function canSeeBoard(User $user, TaskBoard $board): bool
    {
        return self::moduleEnabled($user)
            && self::boardsQuery($user)->whereKey($board->id)->exists();
    }

    public static function canSeeCard(User $user, TaskCard $card): bool
    {
        return self::moduleEnabled($user)
            && self::scopeCards(TaskCard::query(), $user)->whereKey($card->id)->exists();
    }

    /**
     * Papel do usuário como membro (convidado) do quadro do cartão; null quando não é membro.
     */
    public static function boardRole(User $user, int $boardId): ?TaskBoardMemberRole
    {
        $role = DB::table('task_board_members')
            ->where('board_id', $boardId)
            ->where('user_id', $user->id)
            ->value('role');

        return $role === null ? null : (TaskBoardMemberRole::tryFrom((string) $role) ?? TaskBoardMemberRole::Viewer);
    }

    /**
     * Nível que o papel no quadro dá: membro pelo papel; quem vê sem ser membro comenta.
     */
    public static function levelFor(?TaskBoardMemberRole $role): string
    {
        return match ($role) {
            TaskBoardMemberRole::Owner, TaskBoardMemberRole::Editor => self::EDIT,
            TaskBoardMemberRole::Commenter, null => self::COMMENT,
            TaskBoardMemberRole::Viewer => self::READ,
        };
    }

    /**
     * O usuário está atribuído (membro) a este cartão.
     */
    public static function isAssigned(User $user, int $cardId): bool
    {
        return DB::table('task_card_members')
            ->where('task_card_id', $cardId)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * O que o usuário pode fazer num cartão do quadro (já sabendo que o vê). Ver abilitiesFor.
     *
     * @return array{comment: bool, edit: bool, checklist: bool, move: bool, attach: bool, remove_attachment: bool}
     */
    public static function abilities(User $user, int $boardId, bool $assigned = false): array
    {
        return self::abilitiesFor($user, self::boardRole($user, $boardId), $assigned);
    }

    /**
     * Atribuído ao cartão: nível de edição, seja qual for o papel. Senão, o nível do papel no quadro. Cada ação ainda
     * exige a permissão do módulo (comentar: tarefas:create; editar: tarefas:edit; criar item de checklist, anexar e
     * mover: tarefas:edit e tarefas:create; remover anexo: tarefas:edit e tarefas:delete).
     *
     * @return array{comment: bool, edit: bool, checklist: bool, move: bool, attach: bool, remove_attachment: bool}
     */
    public static function abilitiesFor(User $user, ?TaskBoardMemberRole $role, bool $assigned): array
    {
        $level = self::LEVELS[$assigned ? self::EDIT : self::levelFor($role)];
        $can = fn (PermissionAction $action) => $user->canAccess(PermissionModule::Tarefas, $action);
        $edits = $level >= self::LEVELS[self::EDIT] && $can(PermissionAction::Edit);
        $creates = $can(PermissionAction::Create);

        return [
            'comment' => $level >= self::LEVELS[self::COMMENT] && $creates,
            'edit' => $edits,
            'checklist' => $edits && $creates,
            'move' => $edits && $creates,
            'attach' => $edits && $creates,
            'remove_attachment' => $edits && $can(PermissionAction::Delete),
        ];
    }

    /**
     * Concluir pelo calendário do portal (visão de toda a empresa): bloqueia só o convidado do quadro como Leitor ou
     * Comentarista que não está atribuído ao cartão. Quem não é membro do quadro (ex.: administrador da empresa) e quem
     * está atribuído concluem, como antes da regra de atribuição.
     */
    public static function calendarForbidsCompletion(User $user, TaskCard $card): bool
    {
        if (self::isAssigned($user, (int) $card->id)) {
            return false;
        }

        $boardId = (int) TaskCard::query()
            ->join('task_lists', 'task_lists.id', '=', 'task_cards.list_id')
            ->whereKey($card->id)
            ->value('task_lists.board_id');

        return in_array(self::boardRole($user, $boardId), [TaskBoardMemberRole::Viewer, TaskBoardMemberRole::Commenter], true);
    }

    /**
     * @param  'comment'|'edit'|'checklist'|'move'|'attach'|'remove_attachment'  $ability
     */
    public static function allows(User $user, TaskCard $card, string $ability): bool
    {
        if (! self::canSeeCard($user, $card)) {
            return false;
        }

        $boardId = (int) TaskCard::query()
            ->join('task_lists', 'task_lists.id', '=', 'task_cards.list_id')
            ->whereKey($card->id)
            ->value('task_lists.board_id');

        return self::abilities($user, $boardId, self::isAssigned($user, (int) $card->id))[$ability] ?? false;
    }
}
