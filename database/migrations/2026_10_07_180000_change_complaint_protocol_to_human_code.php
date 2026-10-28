<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE complaints ALTER COLUMN protocol TYPE varchar(36) USING protocol::text');
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique(['protocol']);
        });

        $counters = [];
        foreach (DB::table('complaints')->orderBy('id')->get(['id', 'company_id', 'created_at', 'protocol']) as $row) {
            if (is_string($row->protocol) && preg_match('/^DEN-\d{4}-\d{5}$/', $row->protocol) === 1) {
                continue;
            }

            $year = Carbon::parse((string) $row->created_at)->year;
            $key = $row->company_id.'-'.$year;
            $counters[$key] = ($counters[$key] ?? 0) + 1;
            $protocol = sprintf('DEN-%d-%05d', $year, $counters[$key]);

            DB::table('complaints')->where('id', $row->id)->update(['protocol' => $protocol]);
        }

        Schema::table('complaints', function (Blueprint $table) {
            $table->unique(['company_id', 'protocol']);
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'protocol']);
            $table->unique('protocol');
        });
    }
};
