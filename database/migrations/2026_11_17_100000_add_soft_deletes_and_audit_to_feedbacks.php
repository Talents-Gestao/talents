<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exclusão de modelo e de feedback: lógica (deleted_at) para o suporte poder recuperar, e trilha de quem excluiu.
 * Só acrescenta colunas nulas e uma tabela nova: retrocompatível.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['feedback_templates', 'feedback_sessions', 'feedback_session_answers'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        Schema::create('feedback_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label')->nullable();
            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedInteger('answers_count')->default(0);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_audit_logs');

        foreach (['feedback_session_answers', 'feedback_sessions', 'feedback_templates'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
