<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão externa de documentos NR-1. Aditivo: nada do que existe muda.
 *
 * approver_organizations: organização aprovadora, genérica (quem ela é na prática é decisão em aberto).
 * document_shares: compartilhamento explícito de UMA versão com UM revisor, com validade.
 * document_share_reviews: comentários e aprovação do revisor. Só inserção; a versão nunca é alterada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approver_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('user_workspaces', function (Blueprint $table) {
            $table->foreignId('approver_organization_id')->nullable()->after('company_id')
                ->constrained('approver_organizations')->restrictOnDelete();
        });

        Schema::create('document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained('psychosocial_document_versions')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approver_organization_id')->constrained('approver_organizations')->restrictOnDelete();
            $table->foreignId('shared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('shared_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['document_version_id', 'reviewer_id']);
            $table->index(['reviewer_id', 'expires_at']);
        });

        Schema::create('document_share_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_share_id')->constrained('document_shares')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->text('body')->nullable();
            $table->timestamp('created_at');

            $table->index(['document_share_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_share_reviews');
        Schema::dropIfExists('document_shares');

        Schema::table('user_workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_organization_id');
        });

        Schema::dropIfExists('approver_organizations');
    }
};
