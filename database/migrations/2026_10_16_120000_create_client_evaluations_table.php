<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('cycle_year');
            $table->unsignedTinyInteger('cycle_quarter');
            $table->uuid('public_token')->unique();
            $table->string('status', 32)->default('pending');
            $table->string('respondent_name');
            $table->string('respondent_email');
            $table->string('respondent_phone', 30)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('nps_score')->nullable();
            $table->unsignedTinyInteger('service_score')->nullable();
            $table->unsignedTinyInteger('delivery_score')->nullable();
            $table->unsignedTinyInteger('communication_score')->nullable();
            $table->text('comment')->nullable();
            $table->string('answered_name')->nullable();
            $table->string('answered_role')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'cycle_year', 'cycle_quarter']);
            $table->index('status');
        });

        // Uma resposta aberta (pendente ou respondida) por empresa e ciclo.
        // Expirada e substituída liberam um novo convite.
        DB::statement(
            "CREATE UNIQUE INDEX client_evaluations_one_open_per_cycle
             ON client_evaluations (company_id, cycle_year, cycle_quarter)
             WHERE status IN ('pending', 'answered')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('client_evaluations');
    }
};
