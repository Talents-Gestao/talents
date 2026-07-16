<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exit_interviews', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->unique()->after('created_by');
            $table->timestamp('employee_submitted_at')->nullable()->after('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('exit_interviews', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['public_token', 'employee_submitted_at']);
        });
    }
};
