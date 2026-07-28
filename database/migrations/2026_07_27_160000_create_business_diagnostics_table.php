<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_diagnostics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('company_name');
            $table->string('cnpj', 18)->nullable();
            $table->string('segment', 120)->nullable();
            $table->string('employee_count', 60)->nullable();
            $table->string('responsible_name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->text('company_history')->nullable();
            $table->text('biggest_challenge')->nullable();
            $table->unsignedTinyInteger('hr_maturity')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('email');
            $table->index('company_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_diagnostics');
    }
};
