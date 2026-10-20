<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — cadastro técnico da campanha, ainda sem tela.
 * Todos os campos de conteúdo nascem nulos. Elegíveis vazios permanecem lacuna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_campaign_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('unit_name')->nullable();
            $table->string('process_name')->nullable();
            $table->string('legal_name_snapshot')->nullable();
            $table->string('cnpj_snapshot', 20)->nullable();
            $table->unsignedInteger('eligible_count')->nullable();
            $table->text('eligible_source')->nullable();
            $table->json('exposure_groups')->nullable();
            $table->text('aep_summary')->nullable();
            $table->text('observation_summary')->nullable();
            $table->text('existing_controls')->nullable();
            $table->string('technical_responsible_name')->nullable();
            $table->string('technical_responsible_role')->nullable();
            $table->string('technical_responsible_registration', 64)->nullable();
            $table->string('approving_organization_name')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_campaign_registrations');
    }
};
