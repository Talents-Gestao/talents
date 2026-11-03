<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Models\CommercialCommission;
use App\Models\CommercialSale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommissionPaymentsLegacyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_paid_commission_gets_one_payment_and_rerun_is_idempotent(): void
    {
        $sale = $this->sale();
        $paidAt = now()->subDays(10)->startOfSecond();
        $paid = $this->legacyCommission($sale, CommercialCommission::STATUS_PAGA, 1_200, $paidAt);
        $open = $this->legacyCommission($sale, CommercialCommission::STATUS_A_PAGAR, 800);

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $payments = DB::table('commercial_commission_payments')->where('commission_id', $paid->id)->get();
        $this->assertCount(1, $payments);
        $this->assertSame(1_200, (int) $payments[0]->amount_cents);
        $this->assertNull($payments[0]->paid_by);
        $this->assertSame($paidAt->toDateTimeString(), (string) Carbon::parse($payments[0]->paid_at)->toDateTimeString());
        $this->assertSame(1_200, (int) DB::table('commercial_commissions')->where('id', $paid->id)->value('paid_cents'));

        $this->assertFalse(DB::table('commercial_commission_payments')->where('commission_id', $open->id)->exists());
        $this->assertSame(0, (int) DB::table('commercial_commissions')->where('id', $open->id)->value('paid_cents'));
    }

    public function test_migration_is_reversible(): void
    {
        $sale = $this->sale();
        $paid = $this->legacyCommission($sale, CommercialCommission::STATUS_PAGA, 1_200, now());
        $partial = $this->legacyCommission($sale, CommercialCommission::STATUS_PARCIAL, 900);

        $migration = $this->migration();
        $migration->down();

        $this->assertFalse(Schema::hasTable('commercial_commission_payments'));
        $this->assertFalse(Schema::hasColumn('commercial_commissions', 'paid_cents'));
        $this->assertSame(CommercialCommission::STATUS_A_PAGAR, DB::table('commercial_commissions')->where('id', $partial->id)->value('status'));
        $this->assertSame(CommercialCommission::STATUS_PAGA, DB::table('commercial_commissions')->where('id', $paid->id)->value('status'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('commercial_commission_payments'));
        $this->assertSame(1, DB::table('commercial_commission_payments')->where('commission_id', $paid->id)->count());
        $this->assertSame(1_200, (int) DB::table('commercial_commissions')->where('id', $paid->id)->value('paid_cents'));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_03_130000_create_commercial_commission_payments_table.php');
    }

    private function sale(): CommercialSale
    {
        return CommercialSale::query()->create([
            'code' => 'VENDA-2026-MIG1',
            'client_name' => 'Cliente Legado',
            'total_cents' => 12_000,
            'payment_method' => 'pix',
            'installments_count' => 1,
            'status' => CommercialSale::STATUS_QUITADA,
            'sold_at' => now(),
        ]);
    }

    /**
     * Grava como o código anterior: só status/paid_at, sem pagamento nem paid_cents.
     */
    private function legacyCommission(CommercialSale $sale, string $status, int $amountCents, $paidAt = null): CommercialCommission
    {
        $id = DB::table('commercial_commissions')->insertGetId([
            'sale_id' => $sale->id,
            'seller_id' => null,
            'base_cents' => 12_000,
            'percent' => 10,
            'amount_cents' => $amountCents,
            'paid_cents' => 0,
            'status' => $status,
            'paid_at' => $paidAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CommercialCommission::query()->findOrFail($id);
    }
}
