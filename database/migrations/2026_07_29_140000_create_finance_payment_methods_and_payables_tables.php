<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('finance_payment_methods')->insert([
            ['name' => 'PIX', 'slug' => 'pix', 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Boleto', 'slug' => 'boleto', 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cartão', 'slug' => 'cartao', 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transferência', 'slug' => 'transferencia', 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Dinheiro', 'slug' => 'dinheiro', 'is_active' => true, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('finance_payables', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('supplier_name')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->date('due_date');
            $table->string('status', 32)->default('pending');
            $table->foreignId('payment_method_id')->nullable()->constrained('finance_payment_methods')->nullOnDelete();
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
        Schema::dropIfExists('finance_payables');
        Schema::dropIfExists('finance_payment_methods');
    }
};
