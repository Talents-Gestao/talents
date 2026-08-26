<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_boards', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('is_archived');
            $table->index(['is_archived', 'position']);
        });

        $ids = DB::table('task_boards')
            ->orderByRaw('company_id is null desc')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id');

        $position = 1000;
        foreach ($ids as $id) {
            DB::table('task_boards')->where('id', $id)->update(['position' => $position]);
            $position += 1000;
        }
    }

    public function down(): void
    {
        Schema::table('task_boards', function (Blueprint $table) {
            $table->dropIndex(['is_archived', 'position']);
            $table->dropColumn('position');
        });
    }
};
