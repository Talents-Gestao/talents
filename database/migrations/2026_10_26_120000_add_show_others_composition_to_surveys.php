<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D8: exibir os nomes dos setores reunidos em "Outros Setores". Desligado por padrão em toda campanha.
 * Mesmo ligado, só vale com pelo menos 3 setores no grupo e o grupo no piso de respondentes.
 * Depende de confirmação da responsável técnica e do jurídico (ver docs/nr1-v11/NOTAS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->boolean('show_others_composition')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('show_others_composition');
        });
    }
};
