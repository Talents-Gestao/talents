<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('surveys')
            ->where('min_responses_for_breakdown', 5)
            ->update(['min_responses_for_breakdown' => 1]);

        Schema::table('surveys', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_responses_for_breakdown')->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_responses_for_breakdown')->default(5)->change();
        });
    }
};
