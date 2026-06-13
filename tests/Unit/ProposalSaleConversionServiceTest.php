<?php

namespace Tests\Unit;

use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Services\Commercial\ProposalSaleConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProposalSaleConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_converts_closed_proposal_into_sale_with_installments_and_commission(): void
    {
        $proposal = CommercialProposal::create([
            'code' => 'PROP-2026-0001',
            'client_name' => 'Vibe',
            'employee_count' => 70,
            'total_final_cents' => 10000,
            'commission_percent' => 10,
            'commission_cents' => 1000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $sale = app(ProposalSaleConversionService::class)->convert($proposal, [
            'payment_method' => 'cartao',
            'installments_count' => 3,
            'first_due_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->assertInstanceOf(CommercialSale::class, $sale);
        $this->assertSame('cartao', $sale->payment_method);
        $this->assertSame(3, $sale->installments_count);
        $this->assertCount(3, $sale->installments);
        $this->assertSame(10000, $sale->installments->sum('amount_cents'));
        $this->assertNotNull($sale->commission);
        $this->assertSame(1000, $sale->commission->amount_cents);
    }

    public function test_rejects_open_proposal(): void
    {
        $proposal = CommercialProposal::create([
            'code' => 'PROP-2026-0002',
            'client_name' => 'Cliente',
            'employee_count' => 1,
            'total_final_cents' => 5000,
            'is_closed' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(ProposalSaleConversionService::class)->convert($proposal, [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => now()->addDay()->toDateString(),
        ]);
    }
}
