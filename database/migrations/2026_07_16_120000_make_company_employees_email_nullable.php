<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Instalações novas já criam email nullable; isto cobre bases existentes (Postgres).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE company_employees ALTER COLUMN email DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("UPDATE company_employees SET email = '' WHERE email IS NULL");
            DB::statement('ALTER TABLE company_employees ALTER COLUMN email SET NOT NULL');
        }
    }
};
