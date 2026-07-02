<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('event_kind')->nullable();
            $table->timestamp('published_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'published_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('company_notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['company_notice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_notice_reads');
        Schema::dropIfExists('company_notices');
    }
};
