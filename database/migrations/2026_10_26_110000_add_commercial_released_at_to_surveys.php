<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liberação explícita dos resultados agregados de uma campanha ao Comercial limitado.
 * Nulo = o Comercial não vê nada da campanha. Será substituída pelo estado "publicado" da Etapa 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->timestamp('commercial_released_at')->nullable();
            $table->foreignId('commercial_released_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commercial_released_by');
            $table->dropColumn('commercial_released_at');
        });
    }
};
