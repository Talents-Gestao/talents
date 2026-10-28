<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — acompanhamento novo de medidas, separado de action_plan_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychosocial_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('factor_label');
            $table->string('evidence_basis', 32);
            $table->text('measure_description');
            $table->string('responsible_text')->nullable();
            $table->date('suggested_due_date')->nullable();
            $table->string('sst_priority', 32)->nullable();
            $table->text('evidence_text')->nullable();
            $table->string('status', 16)->default('to_approve');
            $table->timestamp('implemented_at')->nullable();
            $table->foreignId('implemented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('effectiveness_note')->nullable();
            $table->timestamp('effectiveness_checked_at')->nullable();
            $table->string('origin', 16);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['survey_id', 'sort_order']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('psychosocial_measure_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_id')->constrained('psychosocial_measures')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field', 64);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['measure_id', 'id']);
        });

        // A suíte roda em SQLite, que não aceita ADD CONSTRAINT; lá a regra fica no model e no serviço.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE psychosocial_measures ADD CONSTRAINT psychosocial_measures_status_check CHECK (status IN ('to_approve', 'pending', 'in_progress', 'implemented'))");
            DB::statement("ALTER TABLE psychosocial_measures ADD CONSTRAINT psychosocial_measures_origin_check CHECK (origin IN ('manual', 'engine_suggestion'))");
            DB::statement("ALTER TABLE psychosocial_measures ADD CONSTRAINT psychosocial_measures_evidence_basis_check CHECK (evidence_basis IN ('questionnaire_indication', 'validated_factor'))");
            DB::statement("ALTER TABLE psychosocial_measures ADD CONSTRAINT psychosocial_measures_implemented_check CHECK (status <> 'implemented' OR (implemented_at IS NOT NULL AND implemented_by IS NOT NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('psychosocial_measure_events');
        Schema::dropIfExists('psychosocial_measures');
    }
};
