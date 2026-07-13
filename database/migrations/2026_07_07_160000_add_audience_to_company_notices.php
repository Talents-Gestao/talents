<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_notices', function (Blueprint $table) {
            $table->string('audience')->default('company')->after('id');
            // Avisos internos da Talents não pertencem a nenhuma empresa.
            $table->unsignedBigInteger('company_id')->nullable()->change();
        });

        Schema::table('company_notices', function (Blueprint $table) {
            $table->index(['audience', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('company_notices', function (Blueprint $table) {
            $table->dropIndex(['audience', 'published_at']);
            $table->dropColumn('audience');
        });
    }
};
