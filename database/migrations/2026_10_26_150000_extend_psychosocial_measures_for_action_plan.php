<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plano de ação operacional (Caderno §11). Aditivo: nada do que existe muda de significado.
 *
 * - Sugerido (Talents) × confirmado (empresa): responsável e prazo; a confirmação guarda quem e quando.
 * - Status novo `replanned` ("Replanejada"). Valores gravados mantidos; os rótulos do plano são do model.
 * - Aferição: indicador e resultado da eficácia (a nota e a data de verificação já existiam).
 * - Evidências de execução em tabela própria, só inserção, com hash do arquivo.
 */
return new class extends Migration
{
    private const STATUSES_NEW = "'to_approve', 'pending', 'in_progress', 'implemented', 'replanned'";

    private const STATUSES_OLD = "'to_approve', 'pending', 'in_progress', 'implemented'";

    public function up(): void
    {
        Schema::table('psychosocial_measures', function (Blueprint $table) {
            $table->text('objective')->nullable();
            $table->text('expected_result')->nullable();
            $table->string('suggested_due_origin', 32)->nullable();
            $table->text('suggested_due_note')->nullable();
            $table->string('indicator')->nullable();

            $table->foreignId('confirmed_responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confirmed_responsible_text')->nullable();
            $table->date('confirmed_due_date')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('effectiveness_result', 16)->nullable();
        });

        Schema::create('psychosocial_measure_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_id')->constrained('psychosocial_measures')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 8);
            $table->text('description');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_sha256', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['measure_id', 'id']);
        });

        // A suíte roda em SQLite, que não aceita ADD CONSTRAINT; lá a regra fica no model e no serviço.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE psychosocial_measures DROP CONSTRAINT IF EXISTS psychosocial_measures_status_check');
            DB::statement('ALTER TABLE psychosocial_measures ADD CONSTRAINT psychosocial_measures_status_check CHECK (status IN ('.self::STATUSES_NEW.'))');
            DB::statement('ALTER TABLE psychosocial_measures ADD CONSTRAINT psychosocial_measures_confirmed_check CHECK (confirmed_at IS NULL OR (confirmed_due_date IS NOT NULL AND (confirmed_responsible_user_id IS NOT NULL OR confirmed_responsible_text IS NOT NULL)))');
            DB::statement("ALTER TABLE psychosocial_measures ADD CONSTRAINT psychosocial_measures_effectiveness_result_check CHECK (effectiveness_result IS NULL OR effectiveness_result IN ('effective', 'partial', 'ineffective'))");
            DB::statement("ALTER TABLE psychosocial_measure_evidences ADD CONSTRAINT psychosocial_measure_evidences_kind_check CHECK (kind IN ('file', 'text'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            if (DB::table('psychosocial_measures')->where('status', 'replanned')->exists()) {
                throw new RuntimeException('Rollback bloqueado: existem medidas «Replanejada».');
            }

            DB::statement('ALTER TABLE psychosocial_measures DROP CONSTRAINT IF EXISTS psychosocial_measures_status_check');
            DB::statement('ALTER TABLE psychosocial_measures ADD CONSTRAINT psychosocial_measures_status_check CHECK (status IN ('.self::STATUSES_OLD.'))');
            DB::statement('ALTER TABLE psychosocial_measures DROP CONSTRAINT IF EXISTS psychosocial_measures_confirmed_check');
            DB::statement('ALTER TABLE psychosocial_measures DROP CONSTRAINT IF EXISTS psychosocial_measures_effectiveness_result_check');
        }

        Schema::dropIfExists('psychosocial_measure_evidences');

        Schema::table('psychosocial_measures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropConstrainedForeignId('confirmed_responsible_user_id');
            $table->dropColumn([
                'objective', 'expected_result', 'suggested_due_origin', 'suggested_due_note', 'indicator',
                'confirmed_responsible_text', 'confirmed_due_date', 'confirmed_at', 'effectiveness_result',
            ]);
        });
    }
};
