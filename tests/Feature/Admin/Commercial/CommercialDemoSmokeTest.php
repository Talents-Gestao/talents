<?php

namespace Tests\Feature\Admin\Commercial;

use App\Models\CommercialCommission;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\User;
use Database\Seeders\CommercialDemoSeeder;
use Database\Seeders\CommercialSellersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommercialDemoSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CommercialSellersSeeder::class);

        $this->admin = User::factory()->superAdmin()->create([
            'email' => 'admin@talents.local',
            'is_owner' => true,
        ]);

        $this->seed(CommercialDemoSeeder::class);
    }

    public function test_demo_seeder_creates_all_proposals_and_sales(): void
    {
        $this->assertSame(10, CommercialProposal::query()->where('code', 'like', 'PROP-DEMO-%')->count());
        $this->assertSame(5, CommercialSale::query()->where('code', 'like', 'VENDA-DEMO-%')->count());
        $this->assertSame(5, CommercialCommission::query()->count());
        $this->assertSame(4, CommercialCommission::query()->where('status', CommercialCommission::STATUS_A_PAGAR)->count());
        $this->assertSame(1, CommercialCommission::query()->where('status', CommercialCommission::STATUS_PAGA)->count());
    }

    public function test_demo_open_proposals_form_fifo_queue(): void
    {
        $queueCodes = CommercialProposal::query()
            ->where('code', 'like', 'PROP-DEMO-%')
            ->where('is_closed', false)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('code')
            ->all();

        $this->assertSame([
            'PROP-DEMO-0001',
            'PROP-DEMO-0002',
            'PROP-DEMO-0003',
            'PROP-DEMO-0004',
        ], $queueCodes);

        $withoutSeller = CommercialProposal::query()->where('code', 'PROP-DEMO-0003')->first();
        $this->assertNotNull($withoutSeller);
        $this->assertNull($withoutSeller->seller_id);
    }

    public function test_demo_convertible_proposal_is_closed_without_sale(): void
    {
        $proposal = CommercialProposal::query()->where('code', 'PROP-DEMO-0005')->first();

        $this->assertNotNull($proposal);
        $this->assertTrue($proposal->is_closed);
        $this->assertFalse($proposal->sale()->exists());
    }

    public function test_demo_sales_have_expected_payment_states(): void
    {
        $this->assertSaleState('VENDA-DEMO-0001', CommercialSale::STATUS_QUITADA, 3, 3, 0);
        $this->assertSaleState('VENDA-DEMO-0002', CommercialSale::STATUS_PARCIAL, 3, 1, 2);
        $this->assertSaleState('VENDA-DEMO-0003', CommercialSale::STATUS_ABERTA, 2, 0, 2);
        $this->assertSaleState('VENDA-DEMO-0004', CommercialSale::STATUS_QUITADA, 1, 1, 0);
        $this->assertSaleState('VENDA-DEMO-0005', CommercialSale::STATUS_ABERTA, 4, 0, 4);

        $overdue = CommercialSaleInstallment::query()
            ->whereHas('sale', fn ($q) => $q->where('code', 'VENDA-DEMO-0003'))
            ->where('number', 1)
            ->first();

        $this->assertNotNull($overdue);
        $this->assertSame(CommercialSaleInstallment::STATUS_PENDENTE, $overdue->status);
        $this->assertTrue($overdue->due_date->isPast());

        $futureInstallments = CommercialSaleInstallment::query()
            ->whereHas('sale', fn ($q) => $q->where('code', 'VENDA-DEMO-0005'))
            ->where('status', CommercialSaleInstallment::STATUS_PENDENTE)
            ->get();

        $this->assertCount(4, $futureInstallments);
        $this->assertTrue($futureInstallments->every(fn (CommercialSaleInstallment $i) => $i->due_date->isFuture()));
    }

    public function test_demo_commissions_have_expected_status_and_order(): void
    {
        $paid = CommercialCommission::query()
            ->whereHas('sale', fn ($q) => $q->where('code', 'VENDA-DEMO-0001'))
            ->first();

        $this->assertNotNull($paid);
        $this->assertSame(CommercialCommission::STATUS_PAGA, $paid->status);
        $this->assertSame(100_000, $paid->amount_cents);

        $pendingCodes = CommercialCommission::query()
            ->where('status', CommercialCommission::STATUS_A_PAGAR)
            ->orderBy('created_at')
            ->orderBy('id')
            ->with('sale:id,code')
            ->get()
            ->map(fn (CommercialCommission $c) => $c->sale?->code)
            ->all();

        $this->assertSame([
            'VENDA-DEMO-0003',
            'VENDA-DEMO-0002',
            'VENDA-DEMO-0004',
            'VENDA-DEMO-0005',
        ], $pendingCodes);

        $quitadaComComissaoAberta = CommercialSale::query()
            ->where('code', 'VENDA-DEMO-0004')
            ->with('commission')
            ->first();

        $this->assertSame(CommercialSale::STATUS_QUITADA, $quitadaComComissaoAberta?->status);
        $this->assertSame(CommercialCommission::STATUS_A_PAGAR, $quitadaComComissaoAberta?->commission?->status);
    }

    public function test_demo_proposal_queue_page_matches_seeded_data(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin)
            ->get(route('admin.comercial.propostas.index', [
                'status' => 'abertas',
                'ordenacao' => 'fila',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Comercial/Propostas/Index')
                ->where('queue_total', 4)
                ->has('queue', 4)
                ->where('queue.0.code', 'PROP-DEMO-0001')
                ->where('queue.0.queue_position', 1)
                ->where('queue.3.code', 'PROP-DEMO-0004')
                ->where('queue.3.queue_position', 4)
                ->has('proposals.data', 4)
                ->where('proposals.data.0.code', 'PROP-DEMO-0001')
                ->where('proposals.data.3.code', 'PROP-DEMO-0004')
            );
    }

    public function test_demo_commissions_index_page_matches_seeded_data(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin)
            ->get(route('admin.financeiro.comissoes.index', ['status' => 'a_pagar']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Financeiro/Comissoes/Index')
                ->where('summary.pending_count', 4)
                ->where('summary.pending_cents', 665_000)
                ->has('commissions.data', 4)
                ->where('commissions.data.0.sale.code', 'VENDA-DEMO-0003')
                ->where('commissions.data.3.sale.code', 'VENDA-DEMO-0005')
            );
    }

    public function test_demo_finance_dashboard_lists_overdue_and_upcoming_installments(): void
    {
        $this->withoutVite();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.financeiro.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Financeiro/Dashboard')
                ->where('kpis.commissions_pending_cents', 665_000)
            );

        $props = $response->original->getData()['page']['props'];

        $overdueSaleCodes = collect($props['overdueInstallments'])
            ->pluck('sale.code')
            ->unique()
            ->values()
            ->all();

        $this->assertContains('VENDA-DEMO-0003', $overdueSaleCodes);

        $upcomingSaleCodes = collect($props['upcomingInstallments'])
            ->pluck('sale.code')
            ->unique()
            ->values()
            ->all();

        $this->assertContains('VENDA-DEMO-0005', $upcomingSaleCodes);
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(CommercialDemoSeeder::class);

        $this->assertSame(10, CommercialProposal::query()->where('code', 'like', 'PROP-DEMO-%')->count());
        $this->assertSame(5, CommercialSale::query()->where('code', 'like', 'VENDA-DEMO-%')->count());
        $this->assertSame(5, CommercialCommission::query()->count());
    }

    private function assertSaleState(
        string $saleCode,
        string $expectedStatus,
        int $totalInstallments,
        int $paidInstallments,
        int $pendingInstallments,
    ): void {
        $sale = CommercialSale::query()
            ->where('code', $saleCode)
            ->with('installments')
            ->first();

        $this->assertNotNull($sale, "Venda {$saleCode} não encontrada.");
        $this->assertSame($expectedStatus, $sale->status);
        $this->assertCount($totalInstallments, $sale->installments);

        $paid = $sale->installments->where('status', CommercialSaleInstallment::STATUS_PAGO)->count();
        $pending = $sale->installments->where('status', CommercialSaleInstallment::STATUS_PENDENTE)->count();

        $this->assertSame($paidInstallments, $paid, "{$saleCode}: parcelas pagas.");
        $this->assertSame($pendingInstallments, $pending, "{$saleCode}: parcelas pendentes.");
    }
}
