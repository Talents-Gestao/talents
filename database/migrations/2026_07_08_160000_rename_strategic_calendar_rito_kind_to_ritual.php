<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('strategic_calendar_items')
            ->where('kind', 'rito')
            ->update(['kind' => 'ritual']);

        DB::table('modules')
            ->where('key', 'calendario_estrategico')
            ->where('description', 'like', '%ritos%')
            ->update([
                'description' => 'Eventos e Rituais orientados pela Talents para acompanhamento das empresas.',
            ]);
    }

    public function down(): void
    {
        DB::table('strategic_calendar_items')
            ->where('kind', 'ritual')
            ->update(['kind' => 'rito']);

        DB::table('modules')
            ->where('key', 'calendario_estrategico')
            ->where('description', 'like', '%Rituais%')
            ->update([
                'description' => 'Eventos e ritos orientados pela Talents para acompanhamento das empresas.',
            ]);
    }
};
