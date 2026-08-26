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
            $table->string('lost_reason', 40)->nullable()->after('list_status');
            $table->text('lost_reason_notes')->nullable()->after('lost_reason');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->dropColumn(['lost_reason', 'lost_reason_notes']);
        });
    }
};
