<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function actionValues(): array
    {
        return ['view', 'create', 'edit', 'delete'];
    }

    public function up(): void
    {
        $now = now();

        $workspaceIds = DB::table('user_workspaces')
            ->join('users', 'users.id', '=', 'user_workspaces.user_id')
            ->where('user_workspaces.workspace_type', 'talents')
            ->where('users.role', 'super_admin')
            ->where(function ($q) {
                $q->where('users.is_owner', false)->orWhereNull('users.is_owner');
            })
            ->pluck('user_workspaces.id');

        foreach ($workspaceIds as $workspaceId) {
            foreach ($this->actionValues() as $action) {
                DB::table('admin_user_permissions')->updateOrInsert(
                    [
                        'user_workspace_id' => $workspaceId,
                        'module' => 'feedbacks',
                        'action' => $action,
                    ],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('admin_user_permissions')->where('module', 'feedbacks')->delete();
    }
};
