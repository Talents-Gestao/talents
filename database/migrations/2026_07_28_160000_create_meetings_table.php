<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->text('participants_text')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('audio_path')->nullable();
            $table->string('audio_mime')->nullable();
            $table->unsignedBigInteger('audio_size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->longText('transcript_text')->nullable();
            $table->longText('minutes_text')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
