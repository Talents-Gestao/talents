<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 7: fluxo de documentos (Caderno §12 e §13). Aditivo.
 *
 * psychosocial_review_rounds: uma rodada de revisão por campanha, com os DOIS documentos finais
 * (relatório executivo e relatório técnico + plano de ação). As versões dos documentos continuam
 * imutáveis; o estado vive na rodada. Publicada, a rodada não muda mais.
 * psychosocial_review_events: histórico com autor, data e justificativa. Só inserção.
 * psychosocial_review_acknowledgements: ciência da empresa, uma por publicação. Só inserção.
 * psychosocial_pgr_integrations: "Integração ao PGR confirmada", campo separado, só com evidência.
 */
return new class extends Migration
{
    private const STATUSES = "'draft', 'in_review', 'changes_requested', 'approved', 'published'";

    private const ACTIONS = "'created', 'regenerated', 'sent', 'change_requested', 'approved', 'published', 'acknowledged', 'pgr_integration_confirmed'";

    public function up(): void
    {
        Schema::create('psychosocial_review_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->string('status', 24)->default('draft');
            $table->foreignId('executive_version_id')->nullable()->constrained('psychosocial_document_versions')->restrictOnDelete();
            $table->foreignId('technical_version_id')->nullable()->constrained('psychosocial_document_versions')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('protocol', 40)->nullable()->unique();
            $table->timestamps();

            $table->unique(['survey_id', 'round_number']);
            $table->index(['survey_id', 'status']);
        });

        Schema::create('psychosocial_review_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('psychosocial_review_rounds')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('body')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['round_id', 'id']);
        });

        Schema::create('psychosocial_review_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->unique()->constrained('psychosocial_review_rounds')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('protocol', 40);
            $table->timestamp('acknowledged_at');
        });

        Schema::create('psychosocial_pgr_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->unique()->constrained('psychosocial_review_rounds')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('note')->nullable();
            $table->string('evidence_path');
            $table->string('evidence_name');
            $table->string('evidence_sha256', 64);
            $table->timestamp('confirmed_at');
        });

        // A suíte roda em SQLite, que não aceita ADD CONSTRAINT; lá a regra fica nos models e nos serviços.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE psychosocial_review_rounds ADD CONSTRAINT psychosocial_review_rounds_status_check CHECK (status IN ('.self::STATUSES.'))');
            DB::statement('ALTER TABLE psychosocial_review_events ADD CONSTRAINT psychosocial_review_events_action_check CHECK (action IN ('.self::ACTIONS.'))');
            DB::statement("ALTER TABLE psychosocial_review_rounds ADD CONSTRAINT psychosocial_review_rounds_published_check CHECK (status <> 'published' OR (published_at IS NOT NULL AND published_by IS NOT NULL AND protocol IS NOT NULL AND executive_version_id IS NOT NULL AND technical_version_id IS NOT NULL))");
            DB::statement('ALTER TABLE psychosocial_documents DROP CONSTRAINT IF EXISTS psychosocial_documents_type_check');
            DB::statement("ALTER TABLE psychosocial_documents ADD CONSTRAINT psychosocial_documents_type_check CHECK (type IN ('executive_brief', 'pgr_subsidy', 'evidence_dossier', 'technical_report'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            if (DB::table('psychosocial_documents')->where('type', 'technical_report')->exists()) {
                throw new RuntimeException('Rollback bloqueado: existem relatórios técnicos emitidos.');
            }

            DB::statement('ALTER TABLE psychosocial_documents DROP CONSTRAINT IF EXISTS psychosocial_documents_type_check');
            DB::statement("ALTER TABLE psychosocial_documents ADD CONSTRAINT psychosocial_documents_type_check CHECK (type IN ('executive_brief', 'pgr_subsidy', 'evidence_dossier'))");
        }

        Schema::dropIfExists('psychosocial_pgr_integrations');
        Schema::dropIfExists('psychosocial_review_acknowledgements');
        Schema::dropIfExists('psychosocial_review_events');
        Schema::dropIfExists('psychosocial_review_rounds');
    }
};
