<?php

declare(strict_types=1);

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\WorkspaceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_user_permissions')) {
            Schema::create('admin_user_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_workspace_id')->constrained('user_workspaces')->cascadeOnDelete();
                $table->string('module');
                $table->string('action');
                $table->timestamps();

                $table->unique(['user_workspace_id', 'module', 'action']);
            });
        }

        // Compatibilidade: colaboradores Talents existentes mantêm acesso total até a Equipe restringir.
        $now = now();
        $workspaceIds = DB::table('user_workspaces')
            ->where('workspace_type', WorkspaceType::Talents->value)
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('is_owner', false)->orWhereNull('is_owner');
            })
            ->pluck('id');

        $modules = array_map(
            static fn (AdminPermissionModule $m) => $m->value,
            AdminPermissionModule::all(),
        );
        $actions = array_map(
            static fn (PermissionAction $a) => $a->value,
            PermissionAction::all(),
        );

        foreach ($workspaceIds as $workspaceId) {
            $exists = DB::table('admin_user_permissions')
                ->where('user_workspace_id', $workspaceId)
                ->exists();

            if ($exists) {
                continue;
            }

            $rows = [];
            foreach ($modules as $module) {
                foreach ($actions as $action) {
                    $rows[] = [
                        'user_workspace_id' => $workspaceId,
                        'module' => $module,
                        'action' => $action,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('admin_user_permissions')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_permissions');
    }
};
