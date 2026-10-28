<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soluções Talents (Caderno §14): regras configuráveis «dimensão em atenção → serviço». Cada regra tem o serviço, os
 * serviços elegíveis, a explicação consultiva, o grau de confiança e a revisão pela Talents. As seis soluções do HTML
 * aprovado entram como padrão inicial, ainda NÃO revisadas: a empresa só vê uma regra depois que a Talents revisa o
 * texto (pergunta 11 da Suzane). Alterar o texto de uma regra revisada exige nova revisão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nr1_service_matches', function (Blueprint $table) {
            $table->id();
            $table->string('factor_label', 120)->unique();
            $table->string('service_name');
            $table->string('eligible_services', 500);
            $table->text('consultative_text');
            $table->string('confidence', 8)->default('media');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('commercial_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE nr1_service_matches ADD CONSTRAINT nr1_service_matches_confidence_check CHECK (confidence IN ('alta', 'media', 'baixa'))");
        }

        $defaults = [
            ['Demandas e Sobrecarga', 'Diagnóstico Empresarial e Contratação de Talentos', 'Diagnóstico Empresarial, Contratação de Talentos e Reestruturação Organizacional', 'Investigar se a sobrecarga decorre de processos, metas, prioridades ou capacidade da equipe antes de recomendar novas vagas.'],
            ['Segurança Psicológica', 'Voz do Time e comunicação segura', 'Voz do Time, CNV e Canal de Denúncias', 'Apoiar a construção de canais confiáveis de comunicação e escuta.'],
            ['Esgotamento e Saúde Emocional', 'Organização da jornada e prevenção', 'Diagnóstico Empresarial e ações de gestão', 'Revisar períodos de maior exigência, recursos e práticas de prevenção.'],
            ['Liderança e Gestão', 'Desenvolvimento de Lideranças', 'Lideranças, Profiler, PDI e Feedback', 'Fortalecer competências de gestão e os rituais de acompanhamento.'],
            ['Relacionamentos e Clima', 'Imersão e fortalecimento de equipes', 'Imersão, CNV e Contrato de Expectativas', 'Aprimorar cooperação, combinados e resolução de conflitos.'],
            ['Percepção Organizacional', 'Direcionamento Estratégico e Voz do Time', 'Direcionamento Estratégico e Diagnóstico Empresarial', 'Alinhar prioridades e ampliar a clareza organizacional.'],
        ];

        foreach ($defaults as $index => [$factor, $service, $eligible, $text]) {
            DB::table('nr1_service_matches')->insert([
                'factor_label' => $factor,
                'service_name' => $service,
                'eligible_services' => $eligible,
                'consultative_text' => $text,
                'confidence' => 'media',
                'active' => true,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nr1_service_matches');
    }
};
