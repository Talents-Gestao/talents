<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_settings', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('pdf_aceite_texto');
            $table->string('company_cnpj', 32)->nullable()->after('company_name');
            $table->string('company_address')->nullable()->after('company_cnpj');
            $table->string('company_city_state')->nullable()->after('company_address');
            $table->string('company_phone', 64)->nullable()->after('company_city_state');
            $table->string('company_email')->nullable()->after('company_phone');
            $table->text('default_payment_terms')->nullable()->after('company_email');
            $table->unsignedSmallInteger('default_prazo_dias')->nullable()->after('default_payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_cnpj',
                'company_address',
                'company_city_state',
                'company_phone',
                'company_email',
                'default_payment_terms',
                'default_prazo_dias',
            ]);
        });
    }
};
