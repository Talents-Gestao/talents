<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregados de grupo calculados sobre as respostas originais (hoje só "Outros Setores", group_key = 'others').
 * Tabela própria para não se misturar às linhas geral/seção/setor de survey_results, que vários leitores
 * identificam por department_id nulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_group_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_template_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_key', 16);
            $table->decimal('average_score', 8, 3);
            $table->string('risk_level', 16);
            $table->unsignedInteger('respondent_count')->default(0);
            $table->foreignId('method_version_id')->nullable()->constrained('survey_method_versions');
            $table->decimal('score_numerator', 14, 4)->nullable();
            $table->unsignedInteger('score_denominator')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'group_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_group_results');
    }
};
