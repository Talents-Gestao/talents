<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('workspace_type', 32);
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 32);
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'workspace_type', 'company_id']);
            $table->index(['workspace_type', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_workspaces');
    }
};
