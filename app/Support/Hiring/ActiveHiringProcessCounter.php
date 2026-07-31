<?php

declare(strict_types=1);

namespace App\Support\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\HiringProcessStage;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\HiringProcess;
use App\Models\User;

final class ActiveHiringProcessCounter
{
    /**
     * Conta vagas ativas no acompanhamento (fase diferente de Contratação).
     * Visível para Administrador Talents (Sólides) e Administrador da empresa.
     */
    public function forUser(?User $user): int
    {
        if ($user === null || ! $user->isActive()) {
            return 0;
        }

        if ($user->isSuperAdmin()) {
            if (! $user->canAccessAdmin(AdminPermissionModule::Solides, PermissionAction::View)) {
                return 0;
            }

            return HiringProcess::query()
                ->where('current_stage', '!=', HiringProcessStage::Contratacao->value)
                ->count();
        }

        if (! $user->isCompanyAdmin()) {
            return 0;
        }

        if (! $user->canAccess(PermissionModule::Acompanhamento, PermissionAction::View)) {
            return 0;
        }

        $companyId = $user->contextCompanyId();
        if ($companyId === null) {
            return 0;
        }

        return HiringProcess::query()
            ->where('company_id', $companyId)
            ->where('current_stage', '!=', HiringProcessStage::Contratacao->value)
            ->count();
    }
}
