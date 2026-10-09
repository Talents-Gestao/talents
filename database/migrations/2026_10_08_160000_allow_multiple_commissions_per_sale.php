<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_commissions', function (Blueprint $table) {
            $table->dropUnique(['sale_id']);
            $table->unique(['sale_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::table('commercial_commissions', function (Blueprint $table) {
            $table->dropUnique(['sale_id', 'seller_id']);
            $table->unique('sale_id');
        });
    }
};
