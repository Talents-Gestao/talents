<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_monthly_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_employee_id')->nullable()->constrained('company_employees')->nullOnDelete();
            $table->string('person_name');
            $table->string('category', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('photo_path')->nullable();
            $table->string('photo_disk')->default('public');
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'year', 'month']);
            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_monthly_highlights');
    }
};
