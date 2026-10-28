<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 — schema dos documentos novos (executivo, subsídio AEP/PGR e dossiê).
 * Nada é gerado aqui; os arquivos referenciados nunca são apagados por esta migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychosocial_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'type']);
            $table->index('company_id');
        });

        Schema::create('psychosocial_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('psychosocial_documents')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('public_identifier', 64)->unique();
            $table->string('kind', 16);
            $table->foreignId('parent_version_id')->nullable()->constrained('psychosocial_document_versions');
            $table->foreignId('method_version_id')->constrained('survey_method_versions');
            $table->foreignId('instrument_version_id')->constrained('survey_instrument_versions');
            $table->string('snapshot_schema', 32);
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64);
            $table->json('validation_block')->nullable();
            $table->char('content_sha256', 64);
            $table->string('seal', 32);
            $table->string('pdf_status', 16)->nullable();
            $table->string('pdf_path')->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->text('pdf_error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['document_id', 'version_number']);
        });

        Schema::table('psychosocial_documents', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('psychosocial_document_versions');
        });

        Schema::create('psychosocial_document_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->unique()->constrained('psychosocial_document_versions')->restrictOnDelete();
            $table->string('path', 16);
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('declaration_text')->nullable();
            $table->boolean('declaration_accepted')->nullable();
            $table->string('informed_name')->nullable();
            $table->string('informed_role')->nullable();
            $table->string('informed_registry')->nullable();
            $table->date('informed_date')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_file_name')->nullable();
            $table->char('proof_sha256', 64)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
        });

        Schema::create('psychosocial_document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('psychosocial_document_versions')->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('file_path');
            $table->string('file_name');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        // survey_id e document_version_id sem FK: o log de acesso sobrevive à limpeza da campanha.
        Schema::create('psychosocial_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('survey_id');
            $table->string('resource', 32);
            $table->unsignedBigInteger('document_version_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'created_at']);
            $table->index('survey_id');
        });

        // A suíte roda em SQLite, que não aceita ADD CONSTRAINT; lá a regra fica nos models.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE psychosocial_documents ADD CONSTRAINT psychosocial_documents_type_check CHECK (type IN ('executive_brief', 'pgr_subsidy', 'evidence_dossier'))");
            DB::statement("ALTER TABLE psychosocial_document_versions ADD CONSTRAINT psychosocial_document_versions_kind_check CHECK (
                (kind = 'released' AND parent_version_id IS NULL AND validation_block IS NULL AND seal = 'pending_validation')
                OR (kind = 'validated' AND parent_version_id IS NOT NULL AND validation_block IS NOT NULL AND seal = 'validated')
            )");
            DB::statement("ALTER TABLE psychosocial_document_versions ADD CONSTRAINT psychosocial_document_versions_pdf_status_check CHECK (pdf_status IS NULL OR pdf_status IN ('queued', 'ready', 'failed'))");
            DB::statement("ALTER TABLE psychosocial_document_validations ADD CONSTRAINT psychosocial_document_validations_path_check CHECK (
                (path = 'in_system' AND user_id IS NOT NULL AND validated_at IS NOT NULL AND declaration_text IS NOT NULL AND declaration_accepted = true)
                OR (path = 'informed' AND informed_name IS NOT NULL AND informed_role IS NOT NULL AND informed_date IS NOT NULL
                    AND proof_file_path IS NOT NULL AND proof_file_name IS NOT NULL AND proof_sha256 IS NOT NULL)
            )");
            DB::statement("ALTER TABLE psychosocial_document_attachments ADD CONSTRAINT psychosocial_document_attachments_kind_check CHECK (kind IN ('aep', 'observation', 'inventory', 'other'))");
            DB::statement("ALTER TABLE psychosocial_access_logs ADD CONSTRAINT psychosocial_access_logs_resource_check CHECK (resource IN ('dashboard', 'document_download', 'export_csv', 'export_json'))");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('psychosocial_document_versions') && DB::table('psychosocial_document_versions')->exists()) {
            throw new RuntimeException(
                'Rollback bloqueado: existem versões em psychosocial_document_versions. Os documentos emitidos são evidência e o schema permanece.'
            );
        }

        Schema::dropIfExists('psychosocial_access_logs');
        Schema::dropIfExists('psychosocial_document_attachments');
        Schema::dropIfExists('psychosocial_document_validations');

        if (Schema::hasTable('psychosocial_documents')) {
            Schema::table('psychosocial_documents', function (Blueprint $table) {
                $table->dropForeign(['current_version_id']);
            });
        }

        Schema::dropIfExists('psychosocial_document_versions');
        Schema::dropIfExists('psychosocial_documents');
    }
};
