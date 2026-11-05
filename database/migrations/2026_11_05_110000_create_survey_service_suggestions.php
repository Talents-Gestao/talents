<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->timestamp('service_suggestions_generated_at')->nullable()->after('action_measures_generated_at');
        });

        Schema::create('survey_service_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commercial_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->text('rationale');
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['survey_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_service_suggestions');

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('service_suggestions_generated_at');
        });
    }
};
