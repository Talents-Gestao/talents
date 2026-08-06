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
            $table->timestamp('candidates_count_at')->nullable()->after('candidates_count');
        });
    }

    public function down(): void
    {
        Schema::table('hiring_processes', function (Blueprint $table) {
            $table->dropColumn('candidates_count_at');
        });
    }
};
