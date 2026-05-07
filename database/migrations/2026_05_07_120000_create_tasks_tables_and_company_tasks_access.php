<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('tasks_access')->nullable()->after('strategic_calendar_access');
        });

        Schema::create('task_process_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_color', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('task_template_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_template_id')->constrained('task_process_templates')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('position', 12, 4)->default(0);
            $table->string('default_visibility', 16)->default('company');
            $table->boolean('allow_company_drop_in')->default(true);
            $table->timestamps();

            $table->index(['process_template_id', 'position']);
        });

        Schema::create('task_template_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_list_id')->constrained('task_template_lists')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('position', 12, 4)->default(0);
            $table->string('default_visibility', 16)->default('inherit');
            $table->unsignedSmallInteger('default_due_offset_days')->nullable();
            $table->timestamps();

            $table->index(['template_list_id', 'position']);
        });

        Schema::create('task_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_template_id')->nullable()->constrained('task_process_templates')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cover_color', 32)->nullable();
            $table->boolean('is_archived')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_archived']);
        });

        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('task_boards')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('position', 12, 4)->default(0);
            $table->string('visibility', 16)->default('company');
            $table->boolean('allow_company_drop_in')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['board_id', 'position']);
        });

        Schema::create('task_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('task_lists')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('position', 12, 4)->default(0);
            $table->string('visibility', 16)->default('inherit');
            $table->string('cover_color', 32)->nullable();
            $table->unsignedBigInteger('cover_attachment_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['list_id', 'position']);
            $table->index(['due_date']);
        });

        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('task_boards')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('color', 32);
            $table->decimal('position', 12, 4)->default(0);
            $table->timestamps();

            $table->index(['board_id', 'position']);
        });

        Schema::create('task_card_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_card_id')->constrained('task_cards')->cascadeOnDelete();
            $table->foreignId('task_label_id')->constrained('task_labels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_card_id', 'task_label_id']);
        });

        Schema::create('task_board_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('task_boards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 24)->default('editor');
            $table->timestamps();

            $table->unique(['board_id', 'user_id']);
        });

        Schema::create('task_card_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_card_id')->constrained('task_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_card_id', 'user_id']);
        });

        Schema::create('task_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_card_id')->constrained('task_cards')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('position', 12, 4)->default(0);
            $table->timestamps();

            $table->index(['task_card_id', 'position']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_checklist_id')->constrained('task_checklists')->cascadeOnDelete();
            $table->string('text');
            $table->decimal('position', 12, 4)->default(0);
            $table->boolean('is_completed')->default(false);
            $table->date('due_date')->nullable();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_checklist_id', 'position']);
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_card_id')->constrained('task_cards')->cascadeOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_card_id']);
        });

        Schema::table('task_cards', function (Blueprint $table) {
            $table->foreign('cover_attachment_id')->references('id')->on('task_attachments')->nullOnDelete();
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_card_id')->constrained('task_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->json('mentioned_user_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_card_id', 'created_at']);
        });

        Schema::create('task_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('task_boards')->cascadeOnDelete();
            $table->foreignId('task_card_id')->nullable()->constrained('task_cards')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['board_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activity_logs');
        Schema::dropIfExists('task_comments');
        Schema::table('task_cards', function (Blueprint $table) {
            $table->dropForeign(['cover_attachment_id']);
        });
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_checklists');
        Schema::dropIfExists('task_card_members');
        Schema::dropIfExists('task_board_members');
        Schema::dropIfExists('task_card_label');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('task_cards');
        Schema::dropIfExists('task_lists');
        Schema::dropIfExists('task_boards');
        Schema::dropIfExists('task_template_cards');
        Schema::dropIfExists('task_template_lists');
        Schema::dropIfExists('task_process_templates');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('tasks_access');
        });
    }
};
