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
            $table->timestamp('notes_at')->nullable()->after('notes');
            $table->unsignedInteger('candidates_count')->nullable()->after('notes_at');
        });
    }

    public function down(): void
    {
        Schema::table('hiring_processes', function (Blueprint $table) {
            $table->dropColumn(['notes_at', 'candidates_count']);
        });
    }
};
