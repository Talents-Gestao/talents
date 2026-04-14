<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rhid_espelho_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('id_person');
            $table->date('period_ini');
            $table->date('period_fim');
            $table->string('guid', 64);
            $table->string('storage_path', 512);
            $table->string('file_hash', 64)->nullable();
            $table->string('source', 32)->default('api');
            $table->string('parse_status', 16)->default('pending');
            $table->text('parse_error')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->longText('raw_extract_json')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'id_person', 'period_ini', 'period_fim']);
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('rhid_espelho_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('rhid_espelho_imports')->cascadeOnDelete();
            $table->date('ref_date');
            $table->json('row_json');
            $table->timestamps();

            $table->unique(['import_id', 'ref_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rhid_espelho_days');
        Schema::dropIfExists('rhid_espelho_imports');
    }
};
