<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('rhid_base_url')->nullable()->after('complaints_public_token');
            $table->string('rhid_email')->nullable()->after('rhid_base_url');
            $table->text('rhid_password')->nullable()->after('rhid_email');
            $table->string('rhid_domain')->nullable()->after('rhid_password');
        });

        Schema::create('rhid_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 120);
            $table->string('endpoint', 512)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request_summary')->nullable();
            $table->json('response_summary')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rhid_audit_logs');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'rhid_base_url',
                'rhid_email',
                'rhid_password',
                'rhid_domain',
            ]);
        });
    }
};
