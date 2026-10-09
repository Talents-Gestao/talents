<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 64);
            $table->string('area', 64)->nullable();
            $table->longText('message');
            $table->string('response_preference', 32);
            $table->text('contact')->nullable();
            $table->string('status', 32)->default('new');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_suggestions');
    }
};
