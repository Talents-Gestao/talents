<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->timestamp('contacted_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->dropColumn('contacted_at');
        });
    }
};
