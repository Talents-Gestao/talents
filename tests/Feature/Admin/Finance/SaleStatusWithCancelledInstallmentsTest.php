<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Models\CommercialCommission;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Parcelas canceladas não entram no total nem no pago da venda.
 */
class SaleStatusWithCancelledInstallmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_with_cancelled_installment_and_others_paid_is_quitada(): void
    {
        $sale = $this->saleWithInstallments(['pago', 'pago', 'cancelado']);

        $sale->recalculateStatus();

        $this->assertSame(CommercialSale::STATUS_QUITADA, $sale->fresh()->status);
    }

    public function test_sale_with_cancelled_paid_and_pending_installments_is_parcial(): void
    {
        $sale = $this->saleWithInstallments(['pago', 'pendente', 'cancelado']);

        $sale->recalculateStatus();

        $this->assertSame(CommercialSale::STATUS_PARCIAL, $sale->fresh()->status);
    }

    public function test_sale_with_all_installments_cancelled_is_not_quitada(): void
    {
        $sale = $this->saleWithInstallments(['cancelado', 'cancelado'], CommercialSale::STATUS_PARCIAL);

        $sale->recalculateStatus();

        $this->assertSame(CommercialSale::STATUS_ABERTA, $sale->fresh()->status);
    }

    public function test_cancelled_sale_status_is_not_recalculated(): void
    {
        $sale = $this->saleWithInstallments(['pago', 'cancelado'], CommercialSale::STATUS_CANCELADA);

        $sale->recalculateStatus();

        $this->assertSame(CommercialSale::STATUS_CANCELADA, $sale->fresh()->status);
    }

    public function test_cancelling_last_pending_installment_settles_sale_and_reduces_total(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithInstallments(['pago', 'pendente'], CommercialSale::STATUS_PARCIAL);
        $pending = $sale->installments()->where('status', 'pendente')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.financeiro.vendas.show', $sale))
            ->patch(route('admin.financeiro.parcelas.pagamento', $pending), [
                'status' => CommercialSaleInstallment::STATUS_CANCELADO,
                'notes' => 'Desconto negociado',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.financeiro.vendas.show', $sale));

        $fresh = $sale->fresh();
        $this->assertSame(CommercialSale::STATUS_QUITADA, $fresh->status);
        $this->assertSame(10_000, (int) $fresh->total_cents);
    }

    public function test_reactivating_cancelled_installment_restores_total_and_status(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithInstallments(['pago', 'cancelado'], CommercialSale::STATUS_QUITADA, 10_000);
        $cancelled = $sale->installments()->where('status', 'cancelado')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.financeiro.parcelas.pagamento', $cancelled), [
                'status' => CommercialSaleInstallment::STATUS_PENDENTE,
            ])
            ->assertSessionDoesntHaveErrors();

        $fresh = $sale->fresh();
        $this->assertSame(CommercialSale::STATUS_PARCIAL, $fresh->status);
        $this->assertSame(20_000, (int) $fresh->total_cents);
    }

    public function test_installment_update_excludes_cancelled_from_sale_total(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $sale = $this->saleWithInstallments(['pago', 'pendente', 'pendente'], CommercialSale::STATUS_PARCIAL);
        $target = $sale->installments()->where('number', 3)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.financeiro.parcelas.update', $target), [
                'due_date' => $target->due_date->toDateString(),
                'amount_reais' => '100.00',
                'method' => 'pix',
                'status' => CommercialSaleInstallment::STATUS_CANCELADO,
                'notes' => '',
            ])
            ->assertSessionDoesntHaveErrors();

        $fresh = $sale->fresh();
        $this->assertSame(20_000, (int) $fresh->total_cents);
        $this->assertSame(CommercialSale::STATUS_PARCIAL, $fresh->status);
    }

    public function test_recalculate_command_is_dry_run_by_default(): void
    {
        $sale = $this->saleWithInstallments(['pago', 'cancelado'], CommercialSale::STATUS_PARCIAL);

        $this->artisan('commercial:recalculate-sale-statuses')
            ->expectsOutputToContain('1 venda(s) com ajuste; 1 passariam a «quitada».')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $fresh = $sale->fresh();
        $this->assertSame(CommercialSale::STATUS_PARCIAL, $fresh->status);
        $this->assertSame(20_000, (int) $fresh->total_cents);
    }

    public function test_recalculate_command_applies_with_flag(): void
    {
        $affected = $this->saleWithInstallments(['pago', 'cancelado'], CommercialSale::STATUS_PARCIAL);
        $untouched = $this->saleWithInstallments(['pago', 'pendente'], CommercialSale::STATUS_PARCIAL);
        $cancelledSale = $this->saleWithInstallments(['pago', 'cancelado'], CommercialSale::STATUS_CANCELADA);

        $this->artisan('commercial:recalculate-sale-statuses', ['--apply' => true])
            ->expectsOutputToContain('1 venda(s) e 0 comissão(ões) atualizada(s).')
            ->assertSuccessful();

        $this->assertSame(CommercialSale::STATUS_QUITADA, $affected->fresh()->status);
        $this->assertSame(10_000, (int) $affected->fresh()->total_cents);
        $this->assertSame(CommercialSale::STATUS_PARCIAL, $untouched->fresh()->status);
        $this->assertSame(20_000, (int) $untouched->fresh()->total_cents);
        $this->assertSame(CommercialSale::STATUS_CANCELADA, $cancelledSale->fresh()->status);
    }

    public function test_recalculate_command_aligns_commission_ceiling(): void
    {
        // Legado: total gravado inclui a parcela cancelada; comissão de 10% sobre R$ 200,00.
        $sale = $this->saleWithInstallments(['pago', 'cancelado'], CommercialSale::STATUS_PARCIAL);
        $commission = CommercialCommission::query()->create([
            'sale_id' => $sale->id,
            'base_cents' => 20_000,
            'percent' => 10,
            'amount_cents' => 2_000,
            'status' => CommercialCommission::STATUS_A_PAGAR,
        ]);

        $this->artisan('commercial:recalculate-sale-statuses')
            ->expectsOutputToContain('1 comissão(ões) com ajuste.')
            ->assertSuccessful();
        $this->assertSame(2_000, (int) $commission->fresh()->amount_cents);

        $this->artisan('commercial:recalculate-sale-statuses', ['--apply' => true])
            ->expectsOutputToContain('1 venda(s) e 1 comissão(ões) atualizada(s).')
            ->assertSuccessful();

        $fresh = $commission->fresh();
        $this->assertSame(10_000, (int) $fresh->base_cents);
        $this->assertSame(1_000, (int) $fresh->amount_cents);
    }

    public function test_recalculate_command_reports_nothing_when_consistent(): void
    {
        $this->saleWithInstallments(['pago', 'pendente'], CommercialSale::STATUS_PARCIAL);

        $this->artisan('commercial:recalculate-sale-statuses')
            ->expectsOutput('Nenhuma venda precisa de ajuste.')
            ->assertSuccessful();
    }

    /**
     * Parcelas de R$ 100,00 cada; o total gravado inclui todas (como antes da correção), salvo $totalCents.
     *
     * @param  list<string>  $statuses
     */
    private function saleWithInstallments(
        array $statuses,
        string $saleStatus = CommercialSale::STATUS_ABERTA,
        ?int $totalCents = null,
    ): CommercialSale {
        static $seq = 0;
        $seq++;

        $sale = CommercialSale::query()->create([
            'code' => sprintf('VENDA-2026-C%04d', $seq),
            'proposal_id' => null,
            'client_name' => "Cliente {$seq}",
            'total_cents' => $totalCents ?? count($statuses) * 10_000,
            'payment_method' => 'pix',
            'installments_count' => count($statuses),
            'status' => $saleStatus,
            'sold_at' => now(),
        ]);

        foreach ($statuses as $index => $status) {
            $isPaid = $status === CommercialSaleInstallment::STATUS_PAGO;
            CommercialSaleInstallment::query()->create([
                'sale_id' => $sale->id,
                'number' => $index + 1,
                'amount_cents' => 10_000,
                'due_date' => now()->addMonths($index)->toDateString(),
                'method' => 'pix',
                'status' => $status,
                'paid_at' => $isPaid ? now() : null,
                'paid_amount_cents' => $isPaid ? 10_000 : null,
            ]);
        }

        return $sale->fresh('installments');
    }
}
