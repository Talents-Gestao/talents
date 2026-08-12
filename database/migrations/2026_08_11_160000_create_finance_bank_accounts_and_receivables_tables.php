<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bank_name')->nullable();
            $table->string('agency', 32)->nullable();
            $table->string('account_number', 64)->nullable();
            $table->string('type', 32)->default('checking');
            $table->bigInteger('initial_balance_cents')->default(0);
            $table->date('initial_balance_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('finance_receivables', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('payer_name')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->date('due_date');
            $table->string('status', 32)->default('pending');
            $table->foreignId('payment_method_id')->nullable()->constrained('finance_payment_methods')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('finance_bank_accounts')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_amount_cents')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_receivables');
        Schema::dropIfExists('finance_bank_accounts');
    }
};
