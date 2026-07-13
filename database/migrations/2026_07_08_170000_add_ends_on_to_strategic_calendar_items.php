<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_calendar_items', function (Blueprint $table) {
            $table->date('ends_on')->nullable()->after('occurs_on');
        });
    }

    public function down(): void
    {
        Schema::table('strategic_calendar_items', function (Blueprint $table) {
            $table->dropColumn('ends_on');
        });
    }
};
