<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead convertido em empresa cliente: guarda a empresa criada e quando (impede conversão dupla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_interest_submissions', function (Blueprint $table) {
            $table->foreignId('converted_company_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('companies')
                ->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('landing_interest_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_company_id');
            $table->dropColumn('converted_at');
        });
    }
};
