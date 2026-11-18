<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatias 2 e 3 da gravação do feedback: mapa de falantes, estado do processamento por IA, transcrição limpa
 * (criptografada, editável com revisão) e relatórios estruturados (uma linha por geração, nunca sobrescrita).
 * Só colunas novas anuláveis ou com padrão e uma tabela nova: retrocompatível.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_recordings', function (Blueprint $table) {
            // Rótulo do Plaud («Speaker 1») → papel (leader|employee|other).
            $table->json('speaker_map')->nullable();
            $table->foreignId('speakers_mapped_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('speakers_mapped_at')->nullable();

            // uploaded (enviado) → processing → ready | failed
            $table->string('processing_status', 16)->default('uploaded');
            $table->string('processing_mode', 16)->nullable();
            $table->string('processing_stage', 16)->nullable();
            $table->unsignedSmallInteger('processing_chunks_done')->default(0);
            $table->unsignedSmallInteger('processing_chunks_total')->default(0);
            $table->string('processing_error', 500)->nullable();
            $table->unsignedSmallInteger('processing_attempts')->default(0);
            $table->foreignId('processing_requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->longText('clean_text')->nullable();
            $table->timestamp('clean_generated_at')->nullable();
            $table->unsignedInteger('clean_revision')->default(0);
            $table->foreignId('clean_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('clean_edited_at')->nullable();
        });

        Schema::create('feedback_recording_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feedback_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feedback_recording_id')->constrained()->cascadeOnDelete();
            $table->longText('report_json');
            $table->string('prompt_version', 32);
            $table->string('schema_version', 32);
            $table->string('provider', 32);
            $table->string('model', 128);
            $table->unsignedInteger('clean_revision')->default(0);
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['feedback_recording_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_recording_reports');

        Schema::table('feedback_recordings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('speakers_mapped_by_user_id');
            $table->dropConstrainedForeignId('processing_requested_by_user_id');
            $table->dropConstrainedForeignId('clean_edited_by_user_id');
            $table->dropColumn([
                'speaker_map', 'speakers_mapped_at', 'processing_status', 'processing_mode', 'processing_stage',
                'processing_chunks_done', 'processing_chunks_total', 'processing_error', 'processing_attempts',
                'processing_started_at', 'processed_at', 'clean_text', 'clean_generated_at', 'clean_revision', 'clean_edited_at',
            ]);
        });
    }
};
