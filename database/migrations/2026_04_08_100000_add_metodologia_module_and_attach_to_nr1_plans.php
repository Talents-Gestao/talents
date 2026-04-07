<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $metaId = DB::table('modules')->where('key', 'metodologia')->value('id');
        if (! $metaId) {
            $metaId = DB::table('modules')->insertGetId([
                'key' => 'metodologia',
                'name' => 'Metodologia Talents',
                'description' => 'Jornada de diagnóstico, pesquisa de satisfação e etapas da metodologia.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $nr1Id = DB::table('modules')->where('key', 'nr1')->value('id');
        if (! $nr1Id) {
            return;
        }

        $planIds = DB::table('module_plan')->where('module_id', $nr1Id)->pluck('plan_id')->unique();

        foreach ($planIds as $planId) {
            DB::table('module_plan')->updateOrInsert(
                ['plan_id' => $planId, 'module_id' => $metaId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $id = DB::table('modules')->where('key', 'metodologia')->value('id');
        if (! $id) {
            return;
        }

        DB::table('module_plan')->where('module_id', $id)->delete();
        DB::table('modules')->where('id', $id)->delete();
    }
};
