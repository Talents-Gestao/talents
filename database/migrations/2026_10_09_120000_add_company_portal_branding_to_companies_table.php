<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('portal_slug', 40)->nullable()->unique();
            $table->boolean('portal_enabled')->default(false);
            $table->string('brand_name')->nullable();
            $table->string('brand_primary_color', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['portal_slug']);
            $table->dropColumn([
                'portal_slug',
                'portal_enabled',
                'brand_name',
                'brand_primary_color',
            ]);
        });
    }
};
