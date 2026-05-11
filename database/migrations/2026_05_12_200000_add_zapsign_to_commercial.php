<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_settings', function (Blueprint $table) {
            $table->text('zapsign_api_token')->nullable()->after('default_prazo_dias');
            $table->string('zapsign_api_base_url', 255)->nullable()->after('zapsign_api_token');
            $table->boolean('zapsign_send_automatic_email')->default(true)->after('zapsign_api_base_url');
        });

        Schema::table('commercial_contracts', function (Blueprint $table) {
            $table->string('zapsign_document_token', 64)->nullable()->after('generated_at');
            $table->string('zapsign_status', 32)->nullable()->after('zapsign_document_token');
            $table->timestamp('zapsign_sent_at')->nullable()->after('zapsign_status');
            $table->text('zapsign_primary_sign_url')->nullable()->after('zapsign_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_settings', function (Blueprint $table) {
            $table->dropColumn(['zapsign_api_token', 'zapsign_api_base_url', 'zapsign_send_automatic_email']);
        });

        Schema::table('commercial_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'zapsign_document_token',
                'zapsign_status',
                'zapsign_sent_at',
                'zapsign_primary_sign_url',
            ]);
        });
    }
};
