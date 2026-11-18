<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma linha por chamada de IA (por enquanto, Feedbacks): finalidade, empresa, provedor, modelo, tokens,
 * custo estimado em centavos, duração e sucesso. Nunca guarda o conteúdo enviado ou recebido. Tabela nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 64);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('provider', 32);
            $table->string('model', 128);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('estimated_cost_cents')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('success');
            $table->string('error_code', 64)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
