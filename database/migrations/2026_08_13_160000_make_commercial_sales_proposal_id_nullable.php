<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_sales', function (Blueprint $table) {
            $table->dropForeign(['proposal_id']);
            $table->dropUnique(['proposal_id']);
        });

        Schema::table('commercial_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('proposal_id')->nullable()->change();
        });

        Schema::table('commercial_sales', function (Blueprint $table) {
            $table->foreign('proposal_id')
                ->references('id')
                ->on('commercial_proposals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_sales', function (Blueprint $table) {
            $table->dropForeign(['proposal_id']);
        });

        // Só reverte se não houver vendas manuais (proposal_id null).
        Schema::table('commercial_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('proposal_id')->nullable(false)->change();
            $table->unique('proposal_id');
            $table->foreign('proposal_id')
                ->references('id')
                ->on('commercial_proposals')
                ->cascadeOnDelete();
        });
    }
};
