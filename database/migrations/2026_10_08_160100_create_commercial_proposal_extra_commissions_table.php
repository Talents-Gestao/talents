<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_proposal_extra_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('commercial_proposals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('percent', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['proposal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_proposal_extra_commissions');
    }
};
