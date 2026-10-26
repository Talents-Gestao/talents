<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correção encontrada pela bancada no PostgreSQL: `psychosocial_measures.origin` foi criada com 16 caracteres, mas o valor
 * `engine_suggestion` tem 17. No PostgreSQL toda sugestão do motor falhava com «value too long for type character varying(16)»
 * (o SQLite dos testes não aplica o limite). Só alarga a coluna; nenhum dado nem regra muda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psychosocial_measures', function (Blueprint $table) {
            $table->string('origin', 32)->change();
        });
    }

    public function down(): void
    {
        // Não reduz de volta: valores de 17 caracteres já gravados seriam truncados ou recusados.
    }
};
