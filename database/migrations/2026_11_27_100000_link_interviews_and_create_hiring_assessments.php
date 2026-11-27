<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contratação · Fase 3: entrevista vinculada ao candidato e à candidatura, fontes de transcrição,
 * revisão humana das respostas, exclusão recuperável, roteiros versionados e avaliação/dossiê por candidatura.
 *
 * Entrevistas antigas ficam sem vínculo (nada é vinculado por nome); a fonte delas é «audio_whisper»
 * e as respostas existentes contam como sugestão da IA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->foreignId('talent_candidate_id')->nullable()->after('questionnaire_id')
                ->constrained('talent_candidates')->nullOnDelete();
            $table->foreignId('hiring_application_id')->nullable()->after('talent_candidate_id')
                ->constrained('hiring_applications')->nullOnDelete();
            $table->string('transcript_source', 20)->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('finished_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->index(['talent_candidate_id', 'created_at']);
            $table->index('hiring_application_id');
        });

        DB::table('interviews')->whereNull('transcript_source')->update(['transcript_source' => 'audio_whisper']);

        Schema::table('interview_answers', function (Blueprint $table) {
            $table->text('answer')->nullable()->change();
            $table->string('origin', 20)->default('ai_suggestion')->after('raw_quote');
            $table->boolean('evidence_missing')->default(false)->after('origin');
            $table->text('reviewer_note')->nullable()->after('evidence_missing');
        });

        Schema::table('interview_questionnaires', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('is_default');
            $table->foreignId('parent_id')->nullable()->after('version')
                ->constrained('interview_questionnaires')->nullOnDelete();
        });

        Schema::create('hiring_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_application_id')->unique()->constrained('hiring_applications')->cascadeOnDelete();
            $table->text('success_profile')->nullable();
            $table->text('case_description')->nullable();
            $table->text('case_result')->nullable();
            $table->json('criteria')->nullable();
            $table->text('interviewer_summary')->nullable();
            $table->text('limitations')->nullable();
            $table->string('decision', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_assessments');

        Schema::table('interview_questionnaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('version');
        });

        Schema::table('interview_answers', function (Blueprint $table) {
            $table->dropColumn(['origin', 'evidence_missing', 'reviewer_note']);
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->dropIndex(['talent_candidate_id', 'created_at']);
            $table->dropIndex(['hiring_application_id']);
            $table->dropConstrainedForeignId('hiring_application_id');
            $table->dropConstrainedForeignId('talent_candidate_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['transcript_source', 'reviewed_at', 'deleted_at']);
        });
    }
};
