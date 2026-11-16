<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comissão paga antes do recebimento e devolução pela colaboradora.
 *
 * - commercial_commission_payments.advance_cents: parte do pagamento acima do liberável no momento
 *   (0 nos pagamentos existentes: nada muda nos dados antigos).
 * - commercial_commission_refunds: devolução cobrada da colaboradora (teto abaixo do pago ou
 *   cliente que não pagou), com o que já voltou, o que virou perda e o que foi cancelado.
 * - commercial_commission_refund_entries: histórico com autoria (devolução registrada na conta,
 *   perda assumida, cancelamento automático).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commercial_commission_payments', 'advance_cents')) {
            Schema::table('commercial_commission_payments', function (Blueprint $table) {
                $table->unsignedInteger('advance_cents')->default(0);
            });
        }

        if (! Schema::hasTable('commercial_commission_refunds')) {
            Schema::create('commercial_commission_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commission_id')->constrained('commercial_commissions')->cascadeOnDelete();
                $table->string('origin', 32);
                $table->unsignedInteger('amount_cents');
                $table->unsignedInteger('returned_cents')->default(0);
                $table->unsignedInteger('written_off_cents')->default(0);
                $table->unsignedInteger('cancelled_cents')->default(0);
                $table->string('status', 16)->default('pending');
                $table->text('justification')->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['commission_id', 'status']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('commercial_commission_refund_entries')) {
            Schema::create('commercial_commission_refund_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('refund_id')->constrained('commercial_commission_refunds')->cascadeOnDelete();
                $table->string('type', 16);
                $table->unsignedInteger('amount_cents');
                $table->timestamp('occurred_at');
                $table->foreignId('bank_account_id')->nullable()->constrained('finance_bank_accounts')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['refund_id', 'type']);
                $table->index(['bank_account_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_commission_refund_entries');
        Schema::dropIfExists('commercial_commission_refunds');

        if (Schema::hasColumn('commercial_commission_payments', 'advance_cents')) {
            Schema::table('commercial_commission_payments', function (Blueprint $table) {
                $table->dropColumn('advance_cents');
            });
        }
    }
};
