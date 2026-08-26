<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->default(0)->after('is_commercial');
        });

        $default = (float) (DB::table('commercial_settings')->value('default_commission_percent') ?? 0);
        if ($default > 0) {
            DB::table('users')
                ->where('role', 'super_admin')
                ->where('is_commercial', true)
                ->update(['commission_percent' => $default]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });
    }
};
