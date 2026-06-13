<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_sales', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('proposal_id')->unique()->constrained('commercial_proposals')->cascadeOnDelete();
            $table->string('client_name');
            $table->string('client_cnpj', 18)->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone', 32)->nullable();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('commission_cents')->default(0);
            $table->string('payment_method', 16)->default('pix');
            $table->unsignedSmallInteger('installments_count')->default(1);
            $table->string('status', 16)->default('aberta');
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'sold_at']);
            $table->index('seller_id');
        });

        Schema::create('commercial_sale_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('commercial_sales')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->unsignedBigInteger('amount_cents');
            $table->date('due_date');
            $table->string('method', 16)->default('pix');
            $table->string('status', 16)->default('pendente');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_amount_cents')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['sale_id', 'number']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('commercial_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('commercial_sales')->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('base_cents')->default(0);
            $table->decimal('percent', 5, 2)->default(0);
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('status', 16)->default('a_pagar');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_commissions');
        Schema::dropIfExists('commercial_sale_installments');
        Schema::dropIfExists('commercial_sales');
    }
};
