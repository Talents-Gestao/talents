<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_methodology', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('methodology_form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('step_number')->default(2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('methodology_form_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('methodology_form_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('methodology_form_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('methodology_form_section_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('type', 32)->default('scale'); // scale | text
            $table->boolean('is_required')->default(true);
            $table->unsignedTinyInteger('scale_min')->default(0);
            $table->unsignedTinyInteger('scale_max')->default(5);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('company_methodology_form_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('methodology_form_template_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'methodology_form_template_id'], 'company_methodology_template_unique');
        });

        Schema::create('methodology_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('methodology_form_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->uuid('public_token')->unique();
            $table->string('status', 32)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('collect_email')->default(false);
            $table->timestamps();
        });

        Schema::create('methodology_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('methodology_survey_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('session_token', 64);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('methodology_survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('methodology_survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('methodology_form_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('value_numeric')->nullable();
            $table->text('value_text')->nullable();
            $table->timestamps();

            $table->unique(['methodology_survey_response_id', 'methodology_form_question_id'], 'methodology_survey_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('methodology_survey_answers');
        Schema::dropIfExists('methodology_survey_responses');
        Schema::dropIfExists('methodology_surveys');
        Schema::dropIfExists('company_methodology_form_template');
        Schema::dropIfExists('methodology_form_questions');
        Schema::dropIfExists('methodology_form_sections');
        Schema::dropIfExists('methodology_form_templates');
        Schema::dropIfExists('company_methodology');
    }
};
