<?php

declare(strict_types=1);

namespace App\Support\Feedback;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use Illuminate\Http\Request;

/**
 * Quem administra os cadastros de colaborador em Feedbacks (sugestão de duplicados e união): no portal, o
 * administrador da empresa; no admin, a matriz `feedbacks` com «editar», com a empresa selecionada.
 */
final class FeedbackEmployeeMergeAccess
{
    public static function canManage(Request $request): bool
    {
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        if (app(FeedbackCompanyContext::class)->isAdminContext($request)) {
            return $user->canAccessAdmin(AdminPermissionModule::Feedbacks, PermissionAction::Edit);
        }

        return $user->isCompanyAdmin();
    }
}
