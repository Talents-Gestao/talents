<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Defaults true: propostas existentes mantêm o comportamento anterior
 * (sempre exibiam Público Atendido e Permanência mínima no PDF).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->boolean('include_publico_atendido')->default(true)->after('employee_count');
            $table->boolean('include_minimum_stay')->default(true)->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->dropColumn(['include_publico_atendido', 'include_minimum_stay']);
        });
    }
};
