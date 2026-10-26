<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão interna da Talents (Caderno §9): verificação da consultoria antes de emitir o relatório. Só a consultoria
 * enxerga. Cada "Salvar análise interna" é uma linha nova (histórico); a vigente é a última. Só inserção.
 * Não gera contratação nem oportunidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychosocial_internal_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision', 20);
            $table->text('note')->nullable();
            $table->json('checked_items');
            $table->timestamp('created_at');

            $table->index(['survey_id', 'id']);
        });

        // A suíte roda em SQLite, que não aceita ADD CONSTRAINT; lá a regra fica no model e no serviço.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE psychosocial_internal_reviews ADD CONSTRAINT psychosocial_internal_reviews_decision_check CHECK (decision IN ('documental', 'entrevistas', 'suficiente'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('psychosocial_internal_reviews');
    }
};
