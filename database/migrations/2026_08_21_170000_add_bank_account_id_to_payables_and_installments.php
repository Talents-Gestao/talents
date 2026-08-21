<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_payables', function (Blueprint $table) {
            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('payment_method_id')
                ->constrained('finance_bank_accounts')
                ->nullOnDelete();
        });

        Schema::table('commercial_sale_installments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('method')
                ->constrained('finance_bank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_sale_installments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('finance_payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};
