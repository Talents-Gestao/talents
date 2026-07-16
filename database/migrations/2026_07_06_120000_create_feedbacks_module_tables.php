<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $moduleId = DB::table('modules')->where('key', 'feedbacks')->value('id');
        if (! $moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'key' => 'feedbacks',
                'name' => 'Feedbacks internos',
                'description' => 'Feedback estruturado entre líder e colaborador, com assinatura digital interna.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $nr1Id = DB::table('modules')->where('key', 'nr1')->value('id');
        if ($nr1Id) {
            $planIds = DB::table('module_plan')->where('module_id', $nr1Id)->pluck('plan_id')->unique();
            foreach ($planIds as $planId) {
                DB::table('module_plan')->updateOrInsert(
                    ['plan_id' => $planId, 'module_id' => $moduleId],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('feedbacks_access')->nullable()->after('denuncias_access');
        });

        Schema::create('company_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leader_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'email']);
        });

        Schema::create('feedback_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('feedback_template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_template_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('section_type', 32)->default('questions');
            $table->string('audience', 32)->default('both');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('feedback_template_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_template_section_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->text('body');
            $table->string('question_type', 32)->default('textarea');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('feedback_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feedback_template_id')->constrained();
            $table->foreignId('company_employee_id')->constrained();
            $table->foreignId('leader_user_id')->constrained('users');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('next_alignment_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('feedback_session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feedback_template_question_id')->constrained();
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(
                ['feedback_session_id', 'feedback_template_question_id'],
                'feedback_session_answer_unique',
            );
        });

        Schema::create('feedback_session_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('signer_name');
            $table->string('signer_email');
            $table->uuid('token')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_session_signatures');
        Schema::dropIfExists('feedback_session_answers');
        Schema::dropIfExists('feedback_sessions');
        Schema::dropIfExists('feedback_template_questions');
        Schema::dropIfExists('feedback_template_sections');
        Schema::dropIfExists('feedback_templates');
        Schema::dropIfExists('company_employees');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('feedbacks_access');
        });

        $id = DB::table('modules')->where('key', 'feedbacks')->value('id');
        if ($id) {
            DB::table('module_plan')->where('module_id', $id)->delete();
            DB::table('modules')->where('id', $id)->delete();
        }
    }
};
