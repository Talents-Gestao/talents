<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_proposal_product_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_proposal_id')
                ->constrained('commercial_proposals')
                ->cascadeOnDelete();
            $table->foreignId('commercial_product_id')
                ->constrained('commercial_products')
                ->restrictOnDelete();
            $table->json('options')->nullable();
            $table->string('label_snapshot');
            $table->string('detail_snapshot')->nullable();
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->timestamps();

            $table->unique(
                ['commercial_proposal_id', 'commercial_product_id'],
                'commercial_proposal_product_unique',
            );
        });

        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->unsignedBigInteger('total_catalog_products_cents')->default(0)->after('total_palestras_cents');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->dropColumn('total_catalog_products_cents');
        });

        Schema::dropIfExists('commercial_proposal_product_lines');
    }
};
