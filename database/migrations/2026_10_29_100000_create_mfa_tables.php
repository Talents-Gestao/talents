<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificação em duas etapas (TOTP). Aditivo.
 *
 * user_mfa_credentials: segredo TOTP cifrado com a APP_KEY (se não decifrar, exige nova inscrição), confirmação e o último
 * intervalo de tempo usado (um código já usado não vale de novo).
 * user_mfa_recovery_codes: códigos de recuperação de uso único; só o hash.
 * mfa_audit_events: auditoria (inscrição, reset por outro admin ou por comando, código de recuperação usado). Só inserção.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mfa_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('secret');
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('last_used_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('user_mfa_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'used_at']);
        });

        Schema::create('mfa_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['target_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_audit_events');
        Schema::dropIfExists('user_mfa_recovery_codes');
        Schema::dropIfExists('user_mfa_credentials');
    }
};
