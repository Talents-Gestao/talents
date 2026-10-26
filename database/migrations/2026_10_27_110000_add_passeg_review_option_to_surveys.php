<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Exigir revisão PASSEG" por campanha. Desligada (padrão): a Talents publica os documentos diretamente, com data,
 * responsável, protocolo e a mesma checagem de campos obrigatórios. Ligada: Em revisão PASSEG → Aprovado → Publicado.
 * A rodada guarda se foi publicada sem revisão, para o histórico mostrar o caminho que valeu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->boolean('requires_passeg_review')->default(false);
        });

        Schema::table('psychosocial_review_rounds', function (Blueprint $table) {
            $table->boolean('published_without_review')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('psychosocial_review_rounds', function (Blueprint $table) {
            $table->dropColumn('published_without_review');
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('requires_passeg_review');
        });
    }
};
