<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hiring_processes', function (Blueprint $table) {
            $table->foreignId('commercial_proposal_id')
                ->nullable()
                ->after('company_id')
                ->constrained('commercial_proposals')
                ->nullOnDelete();

            $table->unique('commercial_proposal_id');
        });
    }

    public function down(): void
    {
        Schema::table('hiring_processes', function (Blueprint $table) {
            $table->dropUnique(['commercial_proposal_id']);
            $table->dropConstrainedForeignId('commercial_proposal_id');
        });
    }
};
