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
            $table->text('admin_notes')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('landing_interest_submissions', function (Blueprint $table) {
            $table->dropColumn('admin_notes');
        });
    }
};
