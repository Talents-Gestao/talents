<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campanha NR-1 (Caderno §6) e fila comercial (Caderno §14). Aditivo.
 *
 * nr1_trainings: treinamento Online ou Presencial por público (Time, Líderes) de uma campanha. Só a Talents marca como
 * contratado. `released_at` libera o material ao público do colaborador (T05/T06).
 * nr1_opportunities: oportunidade comercial. Origem treinamento (empresa pediu proposta) ou solução. `open_key` é preenchida
 * enquanto a oportunidade está aberta e tem índice único: bloqueia pedido duplicado aberto para a mesma modalidade e
 * público (T07) mesmo com duas requisições simultâneas; ao fechar (Ganho/Perdido) vira nula e libera novo pedido.
 * nr1_opportunity_events: histórico, só inserção.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nr1_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('modality', 12);
            $table->string('audience', 12);
            $table->timestamp('contracted_at')->nullable();
            $table->foreignId('contracted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->text('scope')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('material_path')->nullable();
            $table->string('material_name')->nullable();
            $table->string('responsible')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->string('facilitator')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('attendance')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'modality', 'audience']);
        });

        Schema::create('nr1_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('origin', 24);
            $table->string('modality', 12)->nullable();
            $table->string('audience', 12)->nullable();
            $table->string('service_key', 60)->nullable();
            $table->text('need')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at');
            $table->text('next_action')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->string('open_key', 160)->nullable()->unique();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('nr1_opportunity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('nr1_opportunities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('body')->nullable();
            $table->timestamp('created_at');
        });

        // A suíte roda em SQLite, que não aceita ADD CONSTRAINT; lá a regra fica nos models e nos serviços.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE nr1_trainings ADD CONSTRAINT nr1_trainings_modality_check CHECK (modality IN ('online', 'presencial'))");
            DB::statement("ALTER TABLE nr1_trainings ADD CONSTRAINT nr1_trainings_audience_check CHECK (audience IN ('time', 'lideres'))");
            DB::statement("ALTER TABLE nr1_opportunities ADD CONSTRAINT nr1_opportunities_status_check CHECK (status IN ('new', 'to_qualify', 'in_contact', 'proposal_sent', 'won', 'lost'))");
            DB::statement("ALTER TABLE nr1_opportunities ADD CONSTRAINT nr1_opportunities_origin_check CHECK (origin IN ('training_request', 'solution_suggestion', 'solution_request'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nr1_opportunity_events');
        Schema::dropIfExists('nr1_opportunities');
        Schema::dropIfExists('nr1_trainings');
    }
};
