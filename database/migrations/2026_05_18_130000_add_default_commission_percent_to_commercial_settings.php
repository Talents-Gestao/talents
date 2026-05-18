<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_settings', function (Blueprint $table) {
            $table->decimal('default_commission_percent', 5, 2)->default(0)->after('palestras_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_settings', function (Blueprint $table) {
            $table->dropColumn('default_commission_percent');
        });
    }
};
