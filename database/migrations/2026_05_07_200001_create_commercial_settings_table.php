<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_settings', function (Blueprint $table) {
            $table->id();

            // Profiler — 4 faixas (até X funcionários, valor por funcionário em centavos)
            $table->unsignedInteger('profiler_tier1_max')->default(5);
            $table->unsignedInteger('profiler_tier1_cents')->default(32700);
            $table->unsignedInteger('profiler_tier2_max')->default(10);
            $table->unsignedInteger('profiler_tier2_cents')->default(30500);
            $table->unsignedInteger('profiler_tier3_max')->default(20);
            $table->unsignedInteger('profiler_tier3_cents')->default(28500);
            $table->unsignedInteger('profiler_tier4_cents')->default(26500);

            // Pesquisas e Organograma — 4 faixas
            $table->unsignedInteger('pesquisas_tier1_max')->default(10);
            $table->unsignedInteger('pesquisas_tier1_cents')->default(12700);
            $table->unsignedInteger('pesquisas_tier2_max')->default(20);
            $table->unsignedInteger('pesquisas_tier2_cents')->default(12000);
            $table->unsignedInteger('pesquisas_tier3_max')->default(30);
            $table->unsignedInteger('pesquisas_tier3_cents')->default(11450);
            $table->unsignedInteger('pesquisas_tier4_cents')->default(10700);

            // Direcionamento Estratégico — 4 faixas
            $table->unsignedInteger('direcionamento_tier1_max')->default(5);
            $table->unsignedInteger('direcionamento_tier1_cents')->default(5000);
            $table->unsignedInteger('direcionamento_tier2_max')->default(10);
            $table->unsignedInteger('direcionamento_tier2_cents')->default(4750);
            $table->unsignedInteger('direcionamento_tier3_max')->default(20);
            $table->unsignedInteger('direcionamento_tier3_cents')->default(4500);
            $table->unsignedInteger('direcionamento_tier4_cents')->default(4250);

            // NR-1 Mapeamento — 4 faixas
            $table->unsignedInteger('nr1_tier1_max')->default(5);
            $table->unsignedInteger('nr1_tier1_cents')->default(1700);
            $table->unsignedInteger('nr1_tier2_max')->default(10);
            $table->unsignedInteger('nr1_tier2_cents')->default(1615);
            $table->unsignedInteger('nr1_tier3_max')->default(20);
            $table->unsignedInteger('nr1_tier3_cents')->default(1535);
            $table->unsignedInteger('nr1_tier4_cents')->default(1500);

            // Devolutiva — 2 modalidades fixas
            $table->unsignedInteger('devolutiva_individual_cents')->default(157700);
            $table->unsignedInteger('devolutiva_grupo_cents')->default(210700);

            // NR-1 Implantação — On-line (por func) ou Presencial (fixo)
            $table->unsignedInteger('nr1_implantacao_online_cents')->default(14000);
            $table->unsignedInteger('nr1_implantacao_presencial_cents')->default(421400);

            // Palestras — base + multiplicador acima de threshold
            $table->unsignedInteger('palestras_base_cents')->default(157700);
            $table->unsignedInteger('palestras_threshold_funcionarios')->default(30);
            $table->unsignedTinyInteger('palestras_multiplier')->default(2);

            // Configurações da proposta em PDF
            $table->unsignedSmallInteger('pdf_validade_dias')->default(7);
            $table->text('pdf_observacoes')->nullable();
            $table->text('pdf_aceite_texto')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Cria singleton inicial usando os defaults + textos padrão para o PDF.
        DB::table('commercial_settings')->insert([
            'pdf_observacoes' => null,
            'pdf_aceite_texto' => 'Declaro estar de acordo com os termos, valores e prazos descritos nesta proposta comercial.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_settings');
    }
};
