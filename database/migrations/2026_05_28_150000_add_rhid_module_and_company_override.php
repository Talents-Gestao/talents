<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rhidId = DB::table('modules')->where('key', 'rhid')->value('id');
        if (! $rhidId) {
            $rhidId = DB::table('modules')->insertGetId([
                'key' => 'rhid',
                'name' => 'RHID / Ponto',
                'description' => 'Integração de ponto eletrônico Control iD (espelho, compliance NR-1).',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $nr1Id = DB::table('modules')->where('key', 'nr1')->value('id');
        if ($nr1Id) {
            $planIds = DB::table('module_plan')->where('module_id', $nr1Id)->pluck('plan_id')->unique();

            foreach ($planIds as $planId) {
                DB::table('module_plan')->updateOrInsert(
                    ['plan_id' => $planId, 'module_id' => $rhidId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('rhid_access')->nullable()->after('tasks_access');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('rhid_access');
        });

        $id = DB::table('modules')->where('key', 'rhid')->value('id');
        if (! $id) {
            return;
        }

        DB::table('module_plan')->where('module_id', $id)->delete();
        DB::table('modules')->where('id', $id)->delete();
    }
};
