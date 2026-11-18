<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gravação do feedback (transcrição exportada do Plaud): guarda só o texto extraído, criptografado, e o hash
 * do arquivo enviado (o arquivo é descartado logo após a extração). Tabela nova: retrocompatível.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feedback_session_id')->constrained()->cascadeOnDelete();
            $table->string('source_format', 8);
            $table->text('original_filename');
            $table->char('file_sha256', 64);
            $table->unsignedBigInteger('file_size_bytes');
            $table->longText('original_text');
            $table->unsignedInteger('original_chars');
            // Sinais detectados no texto: sem falantes, o relatório fica menos preciso (a tela avisa).
            $table->boolean('has_speaker_labels')->default(false);
            $table->boolean('has_timestamps')->default(false);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('consent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consent_confirmed_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['feedback_session_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_recordings');
    }
};
