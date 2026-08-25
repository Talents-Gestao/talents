<?php

declare(strict_types=1);

use App\Enums\AdminPermissionModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expande grants monolíticos (financeiro, comercial, solides, entrevistas, companies)
 * para os submódulos granulares usados na matriz e nas rotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = AdminPermissionModule::legacyExpansionMap();
        $replaceParents = [
            AdminPermissionModule::Financeiro->value,
            AdminPermissionModule::Comercial->value,
            AdminPermissionModule::Solides->value,
            AdminPermissionModule::Entrevistas->value,
        ];

        foreach ($map as $parentModule => $children) {
            $rows = DB::table('admin_user_permissions')
                ->where('module', $parentModule)
                ->get(['user_workspace_id', 'module', 'action']);

            foreach ($rows as $row) {
                foreach ($children as $child) {
                    $exists = DB::table('admin_user_permissions')
                        ->where('user_workspace_id', $row->user_workspace_id)
                        ->where('module', $child->value)
                        ->where('action', $row->action)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('admin_user_permissions')->insert([
                        'user_workspace_id' => $row->user_workspace_id,
                        'module' => $child->value,
                        'action' => $row->action,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (in_array($parentModule, $replaceParents, true)) {
                DB::table('admin_user_permissions')->where('module', $parentModule)->delete();
            }
        }
    }

    public function down(): void
    {
        // Irreversível de forma segura: grants filhos permanecem.
    }
};
