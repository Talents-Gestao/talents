<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiring_process_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_process_id')->constrained('hiring_processes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['hiring_process_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_process_comments');
    }
};
