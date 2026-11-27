<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contratação · Candidatos (Fase 2): busca por dígitos do telefone, anexos internos restritos
 * e auditoria do cadastro (só inserção).
 *
 * O módulo contratacao_anexos NÃO recebe backfill: só o Owner até ser concedido em Configuração → Equipe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talent_candidates', function (Blueprint $table) {
            $table->string('phone_digits', 20)->nullable()->after('phone')->index();
        });

        // Preenche phone_digits dos cadastros já existentes.
        DB::table('talent_candidates')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->each(function (object $row): void {
                $digits = preg_replace('/\D+/', '', (string) $row->phone) ?? '';
                DB::table('talent_candidates')
                    ->where('id', $row->id)
                    ->update(['phone_digits' => $digits !== '' ? $digits : null]);
            });

        Schema::create('talent_candidate_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_candidate_id')->constrained('talent_candidates')->restrictOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('sender_name');
            $table->date('received_on');
            $table->text('purpose');
            $table->timestamp('privacy_confirmed_at');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['talent_candidate_id', 'sha256']);
        });

        Schema::create('talent_candidate_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_candidate_id')->constrained('talent_candidates')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['talent_candidate_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_candidate_audit_logs');
        Schema::dropIfExists('talent_candidate_attachments');

        Schema::table('talent_candidates', function (Blueprint $table) {
            $table->dropIndex(['phone_digits']);
            $table->dropColumn('phone_digits');
        });
    }
};
