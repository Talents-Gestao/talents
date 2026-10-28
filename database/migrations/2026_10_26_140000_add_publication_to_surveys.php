<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gate mínimo de publicação (T02): a empresa só vê resultados, impressão, exportações e documentos
 * de uma campanha que exige publicação depois de "Publicado".
 *
 * requires_publication: false para toda campanha existente (D9: sem decisão, nada muda para as abertas);
 * true para as campanhas novas criadas pela empresa. published_at/published_by: estado "Publicado".
 * O fluxo completo de revisão (Etapa 7) passa a alimentar este estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->boolean('requires_publication')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn(['published_at', 'requires_publication']);
        });
    }
};
