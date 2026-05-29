<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('surveys')
            ->where('min_responses_for_breakdown', '>', 1)
            ->update(['min_responses_for_breakdown' => 1]);
    }

    public function down(): void
    {
        // Não é possível restaurar valores anteriores (5, 60, etc.).
    }
};
