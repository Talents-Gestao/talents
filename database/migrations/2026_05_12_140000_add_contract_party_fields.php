<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_settings', function (Blueprint $table) {
            $table->text('company_representative_line')->nullable()->after('company_email');
            $table->string('company_forum_city_state', 255)->nullable()->after('company_representative_line');
        });

        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->string('client_address')->nullable()->after('client_phone');
            $table->string('client_representative')->nullable()->after('client_address');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_settings', function (Blueprint $table) {
            $table->dropColumn(['company_representative_line', 'company_forum_city_state']);
        });

        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->dropColumn(['client_address', 'client_representative']);
        });
    }
};
