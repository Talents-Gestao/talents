<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trava de conformidade da IA em Feedbacks (provedor aprovado, por quem e quando) e limite mensal de custo
 * por empresa. Colunas novas anuláveis: sem aprovação, nenhuma chamada de IA de Feedbacks é feita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('feedback_approved_provider', 32)->nullable();
            $table->foreignId('feedback_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('feedback_approved_at')->nullable();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('feedback_ai_monthly_limit_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('feedback_ai_monthly_limit_cents');
        });

        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feedback_approved_by');
            $table->dropColumn(['feedback_approved_provider', 'feedback_approved_at']);
        });
    }
};
