<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos parciais de comissão (Fase 1: comissão proporcional ao recebido).
 *
 * - commercial_commission_payments: um registro por repasse; exclusão lógica (deleted_at/deleted_by)
 *   guarda quem desfez um lançamento.
 * - commercial_commissions.paid_cents: cache da soma dos pagamentos ativos.
 * - Legado: comissão «paga» ganha um pagamento com amount_cents/paid_at existentes (paid_by nulo).
 *
 * Idempotente (guards de tabela/coluna e de pagamento já migrado) e reversível.
 */
return new class extends Migration
{
    private const LEGACY_NOTE = 'Migrado do status «paga» anterior aos pagamentos parciais.';

    public function up(): void
    {
        if (! Schema::hasTable('commercial_commission_payments')) {
            Schema::create('commercial_commission_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('commission_id')->constrained('commercial_commissions')->restrictOnDelete();
                $table->unsignedBigInteger('amount_cents');
                $table->timestamp('paid_at');
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

                $table->index(['commission_id', 'deleted_at']);
            });
        }

        if (! Schema::hasColumn('commercial_commissions', 'paid_cents')) {
            Schema::table('commercial_commissions', function (Blueprint $table) {
                $table->unsignedBigInteger('paid_cents')->default(0)->after('amount_cents');
            });
        }

        $this->migrateLegacyPaidCommissions();
    }

    public function down(): void
    {
        // Código anterior só conhece a_pagar/paga: parcial volta a «a_pagar».
        if (Schema::hasColumn('commercial_commissions', 'paid_cents')) {
            DB::table('commercial_commissions')
                ->where('status', 'parcial')
                ->update(['status' => 'a_pagar', 'paid_at' => null]);

            Schema::table('commercial_commissions', function (Blueprint $table) {
                $table->dropColumn('paid_cents');
            });
        }

        Schema::dropIfExists('commercial_commission_payments');
    }

    private function migrateLegacyPaidCommissions(): void
    {
        $now = now();

        DB::table('commercial_commissions')
            ->where('status', 'paga')
            ->orderBy('id')
            ->chunkById(200, function ($commissions) use ($now): void {
                foreach ($commissions as $commission) {
                    $alreadyMigrated = DB::table('commercial_commission_payments')
                        ->where('commission_id', $commission->id)
                        ->exists();

                    if (! $alreadyMigrated && (int) $commission->amount_cents > 0) {
                        DB::table('commercial_commission_payments')->insert([
                            'commission_id' => $commission->id,
                            'amount_cents' => (int) $commission->amount_cents,
                            'paid_at' => $commission->paid_at ?? $commission->updated_at ?? $now,
                            'paid_by' => null,
                            'notes' => self::LEGACY_NOTE,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $paidCents = (int) DB::table('commercial_commission_payments')
                        ->where('commission_id', $commission->id)
                        ->whereNull('deleted_at')
                        ->sum('amount_cents');

                    DB::table('commercial_commissions')
                        ->where('id', $commission->id)
                        ->update(['paid_cents' => $paidCents]);
                }
            });
    }
};
