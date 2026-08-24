<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_bank_account_id')->constrained('finance_bank_accounts')->restrictOnDelete();
            $table->foreignId('to_bank_account_id')->constrained('finance_bank_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('transferred_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['from_bank_account_id', 'transferred_at']);
            $table->index(['to_bank_account_id', 'transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_bank_transfers');
    }
};
