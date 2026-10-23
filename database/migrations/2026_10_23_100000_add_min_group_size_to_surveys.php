<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mínimo efetivo de respondentes por grupo, por campanha. Piso 5: a campanha só pode subir.
 * A coluna legada min_responses_for_breakdown fica como está (deixa de ser lida).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_group_size')->default(5);
        });

        // A suíte roda em SQLite, que não aceita ADD CONSTRAINT; lá a regra fica no model e na validação.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE surveys ADD CONSTRAINT surveys_min_group_size_check CHECK (min_group_size >= 5)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE surveys DROP CONSTRAINT IF EXISTS surveys_min_group_size_check');
        }

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('min_group_size');
        });
    }
};
