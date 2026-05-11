<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('proposal_id')->constrained('commercial_proposals')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('commercial_contract_templates')->nullOnDelete();
            $table->string('template_name_snapshot');
            $table->string('pdf_path');
            $table->longText('html_snapshot');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index('proposal_id');
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_contracts');
    }
};
