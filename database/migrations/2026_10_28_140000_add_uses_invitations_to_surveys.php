<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Convite único» por campanha: a campanha exige um convite de uso único para abrir e enviar o questionário. Desligado
 * por padrão; o padrão global (nr1.collection.duplicate_policy) continua `none` até o jurídico validar. Os convites ficam
 * em `survey_invitation_tokens` (só hash e mês de uso), sem nenhum vínculo com a resposta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->boolean('uses_invitations')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('uses_invitations');
        });
    }
};
