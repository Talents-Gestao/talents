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
            $table->boolean('is_recurring')->default(false)->after('include_minimum_stay');
            $table->unsignedSmallInteger('recurring_months')->nullable()->after('is_recurring');
            $table->unsignedBigInteger('recurring_monthly_cents')->nullable()->after('recurring_months');
            $table->text('recurring_notes')->nullable()->after('recurring_monthly_cents');
        });

        Schema::table('commercial_sales', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('installments_count');
            $table->unsignedSmallInteger('recurring_months')->nullable()->after('is_recurring');
            $table->unsignedBigInteger('recurring_monthly_cents')->nullable()->after('recurring_months');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->dropColumn([
                'is_recurring',
                'recurring_months',
                'recurring_monthly_cents',
                'recurring_notes',
            ]);
        });

        Schema::table('commercial_sales', function (Blueprint $table) {
            $table->dropColumn([
                'is_recurring',
                'recurring_months',
                'recurring_monthly_cents',
            ]);
        });
    }
};
