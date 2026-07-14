<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_calendar_items', function (Blueprint $table) {
            $table->string('source', 20)->default('talents')->after('company_id');
            $table->foreignId('created_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('strategic_calendar_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
