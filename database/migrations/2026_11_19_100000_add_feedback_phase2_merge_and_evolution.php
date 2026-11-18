<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedbacks, Fase 2: união de cadastros de colaborador (marca no cadastro absorvido) e relatórios de Evolução
 * (narrativa por IA entre períodos, uma linha por geração). Só colunas anuláveis e uma tabela nova: retrocompatível.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_employees', function (Blueprint $table) {
            // Cadastro absorvido numa união: aponta para o que ficou (o absorvido fica inativo, não é apagado).
            $table->foreignId('merged_into_employee_id')->nullable()->constrained('company_employees')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();
            $table->foreignId('merged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('feedback_evolution_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_employee_id')->constrained('company_employees')->cascadeOnDelete();
            // Sessões (e seus relatórios) usadas na geração: a narrativa só é mostrada a quem vê todas elas.
            $table->json('session_ids');
            $table->json('report_ids');
            // processing → ready | failed
            $table->string('status', 16)->default('processing');
            $table->string('error', 500)->nullable();
            $table->longText('report_json')->nullable();
            $table->string('prompt_version', 32);
            $table->string('schema_version', 32);
            $table->string('provider', 32)->nullable();
            $table->string('model', 128)->nullable();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_evolution_reports');

        Schema::table('company_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_employee_id');
            $table->dropConstrainedForeignId('merged_by_user_id');
            $table->dropColumn('merged_at');
        });
    }
};
