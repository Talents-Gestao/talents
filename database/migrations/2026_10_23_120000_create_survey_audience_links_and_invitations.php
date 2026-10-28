<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coleta V11: link próprio por público (Time/Líderes), com janela de abertura, e convites de uso único
 * (só o hash do código é guardado; o uso é registrado só com o mês, sem ligação com a resposta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_audience_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 16);
            $table->string('token', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'audience']);
        });

        Schema::create('survey_invitation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 16)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->date('used_month')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['survey_id', 'used_month']);
        });

        Schema::table('survey_responses', function (Blueprint $table) {
            $table->string('audience', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropColumn('audience');
        });

        Schema::dropIfExists('survey_invitation_tokens');
        Schema::dropIfExists('survey_audience_links');
    }
};
