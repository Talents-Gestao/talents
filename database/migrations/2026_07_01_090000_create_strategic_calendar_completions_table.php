<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_calendar_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategic_calendar_item_id')
                ->constrained('strategic_calendar_items')
                ->cascadeOnDelete();
            $table->date('occurs_on');
            $table->timestamp('completed_at');
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['company_id', 'strategic_calendar_item_id', 'occurs_on'],
                'strategic_calendar_completion_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_calendar_completions');
    }
};
