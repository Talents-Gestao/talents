<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_plans', function (Blueprint $table) {
            $table->timestamp('admin_published_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('action_plans', function (Blueprint $table) {
            $table->dropColumn('admin_published_at');
        });
    }
};
