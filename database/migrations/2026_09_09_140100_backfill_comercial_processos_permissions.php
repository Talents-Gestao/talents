<?php

declare(strict_types=1);

use App\Enums\AdminPermissionModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Concede comercial_processos a quem já tem acesso à Gestão comercial.
 */
return new class extends Migration
{
    public function up(): void
    {
        $source = AdminPermissionModule::ComercialPropostas->value;
        $target = AdminPermissionModule::ComercialProcessos->value;

        $rows = DB::table('admin_user_permissions')
            ->where('module', $source)
            ->get(['user_workspace_id', 'action']);

        foreach ($rows as $row) {
            $exists = DB::table('admin_user_permissions')
                ->where('user_workspace_id', $row->user_workspace_id)
                ->where('module', $target)
                ->where('action', $row->action)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('admin_user_permissions')->insert([
                'user_workspace_id' => $row->user_workspace_id,
                'module' => $target,
                'action' => $row->action,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_user_permissions')
            ->where('module', AdminPermissionModule::ComercialProcessos->value)
            ->delete();
    }
};
