<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 das comissões:
 * - finance_payables.commission_payment_id: conta a pagar já baixada gerada por um pagamento de
 *   comissão (move o saldo da conta bancária pelo caminho normal dos payables). Única por pagamento.
 *   Pagamentos antigos (sem payable) seguem como «sem conta (legado)», sem movimento retroativo.
 * - commercial_commissions.loss_notified_at: aviso de «perda assumida» disparado uma vez por entrada
 *   nessa situação (zerado quando a comissão volta a ficar dentro do teto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('finance_payables', 'commission_payment_id')) {
            Schema::table('finance_payables', function (Blueprint $table) {
                $table->foreignId('commission_payment_id')
                    ->nullable()
                    ->unique()
                    ->constrained('commercial_commission_payments')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('commercial_commissions', 'loss_notified_at')) {
            Schema::table('commercial_commissions', function (Blueprint $table) {
                $table->timestamp('loss_notified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commercial_commissions', 'loss_notified_at')) {
            Schema::table('commercial_commissions', function (Blueprint $table) {
                $table->dropColumn('loss_notified_at');
            });
        }

        if (Schema::hasColumn('finance_payables', 'commission_payment_id')) {
            Schema::table('finance_payables', function (Blueprint $table) {
                $table->dropForeign(['commission_payment_id']);
                $table->dropUnique(['commission_payment_id']);
                $table->dropColumn('commission_payment_id');
            });
        }
    }
};
