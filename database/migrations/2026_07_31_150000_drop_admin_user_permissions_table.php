<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admin_user_permissions');
    }

    public function down(): void
    {
        Schema::create('admin_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_workspace_id')->constrained('user_workspaces')->cascadeOnDelete();
            $table->string('module');
            $table->string('action');
            $table->timestamps();

            $table->unique(['user_workspace_id', 'module', 'action']);
        });
    }
};
