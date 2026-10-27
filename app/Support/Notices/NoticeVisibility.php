<?php

declare(strict_types=1);

namespace App\Support\Notices;

use App\Enums\AdminPermissionModule;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quem enxerga cada aviso do NR-1 (as demais origens seguem como sempre foram). Vale para a lista, o contador, o sino e o
 * e-mail, para que ninguém receba aviso de uma área que não pode abrir:
 *  - «nova oportunidade»: Comercial (módulo nr1_oportunidades) e o proprietário Talents;
 *  - «nova solicitação de proposta ou conversa»: equipe Talents com acesso às empresas, e o proprietário;
 *  - «relatório publicado»: usuários da empresa com acesso a Relatórios.
 */
final class NoticeVisibility
{
    /** Tipos de aviso com restrição por permissão. */
    private const RESTRICTED = [
        CompanyNoticeEventKind::Nr1OpportunityCreated,
        CompanyNoticeEventKind::Nr1ProposalRequested,
        CompanyNoticeEventKind::Nr1ReportPublished,
    ];

    public static function canSee(User $user, ?CompanyNoticeEventKind $kind): bool
    {
        if ($kind === null || ! in_array($kind, self::RESTRICTED, true)) {
            return true;
        }

        return match ($kind) {
            CompanyNoticeEventKind::Nr1OpportunityCreated => $user->isSuperAdmin()
                && $user->canAccessAdmin(AdminPermissionModule::Nr1Oportunidades, PermissionAction::View),
            CompanyNoticeEventKind::Nr1ProposalRequested => $user->isSuperAdmin()
                && $user->canAccessAdmin(AdminPermissionModule::Companies, PermissionAction::View),
            CompanyNoticeEventKind::Nr1ReportPublished => ! $user->isSuperAdmin()
                && $user->canAccess(PermissionModule::Relatorios, PermissionAction::View),
            default => true,
        };
    }

    /**
     * Tira da consulta os tipos restritos que o usuário não pode ver.
     *
     * @template T of Builder
     *
     * @param  T  $query
     * @return T
     */
    public static function restrict(Builder $query, User $user): Builder
    {
        $hidden = array_values(array_map(
            fn (CompanyNoticeEventKind $kind) => $kind->value,
            array_filter(self::RESTRICTED, fn (CompanyNoticeEventKind $kind) => ! self::canSee($user, $kind)),
        ));

        if ($hidden === []) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->whereNull('event_kind')->orWhereNotIn('event_kind', $hidden));
    }
}
