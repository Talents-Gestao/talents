<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria da opção «Exigir revisão PASSEG» por campanha: quem mudou, quando, valor anterior e novo. Só inserção.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychosocial_passeg_option_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('previous_value');
            $table->boolean('new_value');
            $table->timestamp('changed_at');

            $table->index(['survey_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psychosocial_passeg_option_changes');
    }
};
