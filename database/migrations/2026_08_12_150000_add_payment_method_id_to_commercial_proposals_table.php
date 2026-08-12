<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liga propostas às formas de pagamento do Financeiro.
 *
 * Backfill (string antiga → slug financeiro):
 * - pix → pix
 * - boleto → boleto
 * - credito / debito → cartao (Financeiro tem um único «Cartão»)
 * - sem match → payment_method_id null (re-seleção no edit)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('finance_payment_methods')
                ->nullOnDelete();
            $table->string('payment_method_label')->nullable()->after('payment_method_id');
        });

        $methodsBySlug = DB::table('finance_payment_methods')
            ->pluck('id', 'slug');

        $legacyToSlug = [
            'pix' => 'pix',
            'boleto' => 'boleto',
            'credito' => 'cartao',
            'debito' => 'cartao',
            'cartao' => 'cartao',
        ];

        $labelsBySlug = DB::table('finance_payment_methods')
            ->pluck('name', 'slug');

        foreach ($legacyToSlug as $legacy => $slug) {
            $methodId = $methodsBySlug[$slug] ?? null;
            if ($methodId === null) {
                continue;
            }

            DB::table('commercial_proposals')
                ->where('payment_method', $legacy)
                ->update([
                    'payment_method_id' => $methodId,
                    'payment_method' => $slug,
                    'payment_method_label' => $labelsBySlug[$slug] ?? $slug,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('commercial_proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('payment_method_label');
        });
    }
};
