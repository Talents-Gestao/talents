<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contratações por vaga: cadastro de candidatos, vagas dentro do processo de acompanhamento,
 * etapas próprias por vaga, candidaturas e histórico (só inserção).
 *
 * Também concede as permissões novas a quem já tinha acesso às telas equivalentes:
 * solides_acompanhamento ou solides → contratacao_vagas;
 * solides_banco_talentos ou solides → contratacao_candidatos (mesmas ações).
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const PERMISSION_BACKFILL = [
        'contratacao_vagas' => ['solides_acompanhamento', 'solides'],
        'contratacao_candidatos' => ['solides_banco_talentos', 'solides'],
    ];

    public function up(): void
    {
        Schema::create('talent_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('solides_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('email_normalized')->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->boolean('email_reviewed')->default(false);
            $table->boolean('phone_reviewed')->default(false);
            $table->boolean('email_opt_in')->default(false);
            $table->string('contact_preference', 20)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('current_role')->nullable();
            $table->string('current_company')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hiring_vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_process_id')->constrained('hiring_processes')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('hiring_vacancy_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_vacancy_id')->constrained('hiring_vacancies')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('position');
            $table->boolean('is_hired')->default(false);
            $table->timestamps();

            $table->index(['hiring_vacancy_id', 'position']);
        });

        Schema::create('hiring_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_vacancy_id')->constrained('hiring_vacancies')->cascadeOnDelete();
            $table->foreignId('talent_candidate_id')->constrained('talent_candidates')->restrictOnDelete();
            $table->foreignId('hiring_vacancy_stage_id')->constrained('hiring_vacancy_stages')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('hired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hiring_vacancy_id', 'talent_candidate_id']);
            $table->index(['hiring_vacancy_stage_id', 'sort_order']);
        });

        Schema::create('hiring_application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_application_id')->constrained('hiring_applications')->cascadeOnDelete();
            $table->string('type', 20);
            $table->foreignId('from_stage_id')->nullable()->constrained('hiring_vacancy_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('hiring_vacancy_stages')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['hiring_application_id', 'created_at']);
        });

        $this->backfillPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_application_events');
        Schema::dropIfExists('hiring_applications');
        Schema::dropIfExists('hiring_vacancy_stages');
        Schema::dropIfExists('hiring_vacancies');
        Schema::dropIfExists('talent_candidates');

        if (Schema::hasTable('admin_user_permissions')) {
            DB::table('admin_user_permissions')
                ->whereIn('module', array_keys(self::PERMISSION_BACKFILL))
                ->delete();
        }
    }

    private function backfillPermissions(): void
    {
        if (! Schema::hasTable('admin_user_permissions')) {
            return;
        }

        $now = now();

        foreach (self::PERMISSION_BACKFILL as $target => $sources) {
            $rows = DB::table('admin_user_permissions')
                ->whereIn('module', $sources)
                ->get(['user_workspace_id', 'action']);

            $seen = [];
            foreach ($rows as $row) {
                $key = $row->user_workspace_id.'|'.$row->action;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $exists = DB::table('admin_user_permissions')
                    ->where('user_workspace_id', $row->user_workspace_id)
                    ->where('module', $target)
                    ->where('action', $row->action)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('admin_user_permissions')->insert([
                    'user_workspace_id' => $row->user_workspace_id,
                    'module' => $target,
                    'action' => $row->action,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
