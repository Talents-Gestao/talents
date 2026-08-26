<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_interest_submissions', function (Blueprint $table) {
            $table->boolean('is_qualified')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('landing_interest_submissions', function (Blueprint $table) {
            $table->dropColumn('is_qualified');
        });
    }
};
