<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hiring_processes', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('notes');
            $table->index(['current_stage', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('hiring_processes', function (Blueprint $table) {
            $table->dropIndex(['current_stage', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
