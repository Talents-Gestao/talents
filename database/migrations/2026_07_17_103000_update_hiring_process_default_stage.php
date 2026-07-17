<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hiring_processes')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE hiring_processes ALTER COLUMN current_stage SET DEFAULT 'engenharia_cargo'");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE hiring_processes MODIFY current_stage VARCHAR(64) NOT NULL DEFAULT 'engenharia_cargo'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hiring_processes')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE hiring_processes ALTER COLUMN current_stage SET DEFAULT 'analise_curriculo'");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE hiring_processes MODIFY current_stage VARCHAR(64) NOT NULL DEFAULT 'analise_curriculo'");
        }
    }
};
