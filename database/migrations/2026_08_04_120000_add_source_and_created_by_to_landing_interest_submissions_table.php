<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_interest_submissions', function (Blueprint $table) {
            $table->string('source', 32)->default('site')->after('message');
            $table->foreignId('created_by')
                ->nullable()
                ->after('source')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('landing_interest_submissions')
            ->whereNull('source')
            ->orWhere('source', '')
            ->update(['source' => 'site']);
    }

    public function down(): void
    {
        Schema::table('landing_interest_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('source');
        });
    }
};
