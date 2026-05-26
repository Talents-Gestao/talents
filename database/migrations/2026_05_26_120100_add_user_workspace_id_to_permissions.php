<?php

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_permissions', function (Blueprint $table) {
            $table->foreignId('user_workspace_id')->nullable()->after('id')->constrained('user_workspaces')->cascadeOnDelete();
        });

        Schema::table('admin_user_permissions', function (Blueprint $table) {
            $table->foreignId('user_workspace_id')->nullable()->after('id')->constrained('user_workspaces')->cascadeOnDelete();
        });

        $this->backfillWorkspacesAndPermissions();

        Schema::table('user_permissions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'module', 'action']);
            $table->dropColumn('user_id');
            $table->unique(['user_workspace_id', 'module', 'action']);
        });

        Schema::table('admin_user_permissions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'module', 'action']);
            $table->dropColumn('user_id');
            $table->unique(['user_workspace_id', 'module', 'action']);
        });
    }

    public function down(): void
    {
        Schema::table('user_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('admin_user_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $workspaceRows = DB::table('user_workspaces')->get()->keyBy('id');

        foreach (DB::table('user_permissions')->get() as $row) {
            $workspace = $workspaceRows->get($row->user_workspace_id);
            if ($workspace) {
                DB::table('user_permissions')->where('id', $row->id)->update(['user_id' => $workspace->user_id]);
            }
        }

        foreach (DB::table('admin_user_permissions')->get() as $row) {
            $workspace = $workspaceRows->get($row->user_workspace_id);
            if ($workspace) {
                DB::table('admin_user_permissions')->where('id', $row->id)->update(['user_id' => $workspace->user_id]);
            }
        }

        Schema::table('user_permissions', function (Blueprint $table) {
            $table->dropForeign(['user_workspace_id']);
            $table->dropUnique(['user_workspace_id', 'module', 'action']);
            $table->dropColumn('user_workspace_id');
            $table->unique(['user_id', 'module', 'action']);
        });

        Schema::table('admin_user_permissions', function (Blueprint $table) {
            $table->dropForeign(['user_workspace_id']);
            $table->dropUnique(['user_workspace_id', 'module', 'action']);
            $table->dropColumn('user_workspace_id');
            $table->unique(['user_id', 'module', 'action']);
        });

        Schema::dropIfExists('user_workspaces');
    }

    private function backfillWorkspacesAndPermissions(): void
    {
        $now = now();

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $workspaceId = null;

            if ($user->role === UserRole::SuperAdmin->value) {
                $workspaceId = DB::table('user_workspaces')->insertGetId([
                    'user_id' => $user->id,
                    'workspace_type' => WorkspaceType::Talents->value,
                    'company_id' => null,
                    'role' => UserRole::SuperAdmin->value,
                    'is_owner' => (bool) ($user->is_owner ?? false),
                    'is_active' => (bool) ($user->is_active ?? true),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('admin_user_permissions')
                    ->where('user_id', $user->id)
                    ->update(['user_workspace_id' => $workspaceId]);
            } elseif ($user->company_id) {
                $workspaceId = DB::table('user_workspaces')->insertGetId([
                    'user_id' => $user->id,
                    'workspace_type' => WorkspaceType::Company->value,
                    'company_id' => $user->company_id,
                    'role' => $user->role,
                    'is_owner' => false,
                    'is_active' => (bool) ($user->is_active ?? true),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('user_permissions')
                    ->where('user_id', $user->id)
                    ->update(['user_workspace_id' => $workspaceId]);
            }
        }
    }
};
