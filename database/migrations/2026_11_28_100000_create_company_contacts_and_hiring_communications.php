<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratação · Fase 4: contatos da empresa (com histórico só de inserção) e comunicações da vaga
 * (atualização de etapa e envio do dossiê), com chave de idempotência única.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('phone_digits', 20)->nullable()->index();
            $table->boolean('channel_email')->default(false);
            $table->boolean('channel_whatsapp')->default(false);
            $table->json('topics')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'archived_at']);
        });

        Schema::create('company_contact_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_contact_id')->constrained('company_contacts')->cascadeOnDelete();
            $table->string('type', 20);
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_contact_id', 'created_at']);
        });

        Schema::create('hiring_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_vacancy_id')->constrained('hiring_vacancies')->cascadeOnDelete();
            $table->foreignId('hiring_application_id')->nullable()->constrained('hiring_applications')->nullOnDelete();
            $table->foreignId('company_contact_id')->constrained('company_contacts')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('channel', 20);
            $table->string('stage_name', 80)->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 20)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hiring_vacancy_id', 'created_at']);
            $table->index(['channel', 'status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_communications');
        Schema::dropIfExists('company_contact_events');
        Schema::dropIfExists('company_contacts');
    }
};
