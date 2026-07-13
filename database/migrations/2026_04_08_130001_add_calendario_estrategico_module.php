<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $calId = DB::table('modules')->where('key', 'calendario_estrategico')->value('id');
        if (! $calId) {
            $calId = DB::table('modules')->insertGetId([
                'key' => 'calendario_estrategico',
                'name' => 'Calendário estratégico',
                'description' => 'Eventos e Rituais orientados pela Talents para acompanhamento das empresas.',
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
                ['plan_id' => $planId, 'module_id' => $calId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $id = DB::table('modules')->where('key', 'calendario_estrategico')->value('id');
        if (! $id) {
            return;
        }

        DB::table('module_plan')->where('module_id', $id)->delete();
        DB::table('modules')->where('id', $id)->delete();
    }
};
