<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_suggestions', function (Blueprint $table) {
            $table->text('reporter_name')->nullable()->after('response_preference');
        });
    }

    public function down(): void
    {
        Schema::table('team_suggestions', function (Blueprint $table) {
            $table->dropColumn('reporter_name');
        });
    }
};
