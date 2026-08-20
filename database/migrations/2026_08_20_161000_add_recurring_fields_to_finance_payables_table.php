<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_payables', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('notes');
            $table->unsignedSmallInteger('recurring_months')->nullable()->after('is_recurring');
            $table->unsignedSmallInteger('recurring_index')->nullable()->after('recurring_months');
            $table->uuid('recurring_group_id')->nullable()->after('recurring_index');

            $table->index('recurring_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('finance_payables', function (Blueprint $table) {
            $table->dropIndex(['recurring_group_id']);
            $table->dropColumn([
                'is_recurring',
                'recurring_months',
                'recurring_index',
                'recurring_group_id',
            ]);
        });
    }
};
