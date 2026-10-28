<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $moduleId = DB::table('modules')->where('key', 'sugestoes')->value('id');
        if (! $moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'key' => 'sugestoes',
                'name' => 'Canal de sugestões e dúvidas',
                'description' => 'Canal anônimo de sugestões, dúvidas e feedback, com link público por empresa.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sourceModuleIds = DB::table('modules')
            ->whereIn('key', ['denuncias', 'nr1'])
            ->pluck('id');

        $planIds = DB::table('module_plan')
            ->whereIn('module_id', $sourceModuleIds)
            ->pluck('plan_id')
            ->unique();

        foreach ($planIds as $planId) {
            DB::table('module_plan')->updateOrInsert(
                ['plan_id' => $planId, 'module_id' => $moduleId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $this->copyPermissions('admin_user_permissions', 'denuncias', 'sugestoes');
        $this->copyPermissions('user_permissions', 'denuncias', 'sugestoes');
    }

    public function down(): void
    {
        DB::table('admin_user_permissions')->where('module', 'sugestoes')->delete();
        DB::table('user_permissions')->where('module', 'sugestoes')->delete();

        $moduleId = DB::table('modules')->where('key', 'sugestoes')->value('id');
        if (! $moduleId) {
            return;
        }

        DB::table('module_plan')->where('module_id', $moduleId)->delete();
        DB::table('modules')->where('id', $moduleId)->delete();
    }

    private function copyPermissions(string $table, string $source, string $target): void
    {
        $rows = DB::table($table)->where('module', $source)->get();

        foreach ($rows as $row) {
            $exists = DB::table($table)
                ->where('user_workspace_id', $row->user_workspace_id)
                ->where('module', $target)
                ->where('action', $row->action)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table($table)->insert([
                'user_workspace_id' => $row->user_workspace_id,
                'module' => $target,
                'action' => $row->action,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
