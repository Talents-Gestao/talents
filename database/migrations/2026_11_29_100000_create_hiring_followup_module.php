<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contratação · Fase 5 (pós-contratação): vínculo do colaborador com o candidato, ciclos de vínculo
 * (admissão, pausa, desligamento, readmissão) com histórico só de inserção, marcos D15/D30/D60/D90,
 * convites individuais por token e respostas da pesquisa pública.
 *
 * Backfill: quem tinha contratacao_vagas recebe as mesmas ações em contratacao_pos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_employees', function (Blueprint $table) {
            $table->foreignId('talent_candidate_id')->nullable()->constrained('talent_candidates')->nullOnDelete();
        });

        Schema::create('employment_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('company_employee_id')->constrained('company_employees')->cascadeOnDelete();
            $table->foreignId('hiring_application_id')->nullable()->constrained('hiring_applications')->nullOnDelete();
            $table->string('status', 24)->default('pending_admission');
            $table->date('admission_date')->nullable();
            $table->timestamp('active_confirmed_at')->nullable();
            $table->string('role_title')->nullable();
            $table->foreignId('manager_contact_id')->nullable()->constrained('company_contacts')->nullOnDelete();
            $table->string('collaborator_email')->nullable();
            $table->string('collaborator_phone', 40)->nullable();
            $table->boolean('collaborator_email_opt_in')->default(false);
            $table->timestamp('paused_at')->nullable();
            $table->text('pause_reason')->nullable();
            $table->date('terminated_on')->nullable();
            $table->text('termination_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_employee_id', 'status']);
        });

        Schema::create('employment_cycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_cycle_id')->constrained('employment_cycles')->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['employment_cycle_id', 'created_at']);
        });

        Schema::create('hiring_followup_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_cycle_id')->constrained('employment_cycles')->cascadeOnDelete();
            $table->string('moment', 8);
            $table->date('due_on');
            $table->timestamp('skipped_at')->nullable();
            $table->text('skip_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['employment_cycle_id', 'moment']);
            $table->index('due_on');
        });

        Schema::create('hiring_followup_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained('hiring_followup_milestones')->cascadeOnDelete();
            $table->string('audience', 16);
            $table->string('recipient_name');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->uuid('token')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->timestamp('copied_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->text('email_failure_reason')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['milestone_id', 'audience']);
            $table->index('emailed_at');
        });

        Schema::create('hiring_followup_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->unique()->constrained('hiring_followup_invitations')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('support_requested')->default(false);
            $table->text('support_note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $this->backfillPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_followup_responses');
        Schema::dropIfExists('hiring_followup_invitations');
        Schema::dropIfExists('hiring_followup_milestones');
        Schema::dropIfExists('employment_cycle_events');
        Schema::dropIfExists('employment_cycles');

        Schema::table('company_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('talent_candidate_id');
        });

        if (Schema::hasTable('admin_user_permissions')) {
            DB::table('admin_user_permissions')->where('module', 'contratacao_pos')->delete();
        }
    }

    private function backfillPermissions(): void
    {
        if (! Schema::hasTable('admin_user_permissions')) {
            return;
        }

        $now = now();
        $rows = DB::table('admin_user_permissions')
            ->where('module', 'contratacao_vagas')
            // «Validar» só existe em contratacao_vagas (aprovar dossiê); não se aplica ao pós-contratação.
            ->whereIn('action', ['view', 'create', 'edit', 'delete'])
            ->get(['user_workspace_id', 'action']);

        foreach ($rows as $row) {
            $exists = DB::table('admin_user_permissions')
                ->where('user_workspace_id', $row->user_workspace_id)
                ->where('module', 'contratacao_pos')
                ->where('action', $row->action)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('admin_user_permissions')->insert([
                'user_workspace_id' => $row->user_workspace_id,
                'module' => 'contratacao_pos',
                'action' => $row->action,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
