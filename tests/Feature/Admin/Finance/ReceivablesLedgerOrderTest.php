<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Enums\FinancePayableStatus;
use App\Enums\FinanceReceivableStatus;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\FinancePayable;
use App\Models\FinanceReceivable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regressão do Jira: «está juntando por cliente» e «lançamentos fora de ordem».
 * A lista de contas a receber é ordenada e paginada no banco (UNION ALL).
 */
class ReceivablesLedgerOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->superAdmin()->create(['is_owner' => true]);
    }

    public function test_due_date_order_is_global_across_sales_origins_and_pages(): void
    {
        // Vendas com vencimentos intercalados + manuais, criadas fora de ordem.
        $this->sale('VENDA-A', 'Cliente A', ['2026-01-10', '2026-03-10', '2026-05-10', '2026-07-10', '2026-09-10', '2026-11-10']);
        $this->sale('VENDA-B', 'Cliente B', ['2026-02-10', '2026-04-10', '2026-06-10', '2026-08-10', '2026-10-10', '2026-12-10']);
        $this->sale('VENDA-C', 'Cliente A', ['2026-01-20', '2026-02-20', '2026-03-20', '2026-04-20', '2026-05-20']);
        foreach (['2026-06-25', '2026-01-05', '2026-12-25', '2026-03-01', '2026-09-30', '2026-07-01', '2026-11-30'] as $i => $due) {
            $this->manual("Manual {$i}", $due);
        }

        $dates = [
            ...$this->dueDates($this->ledger(['sort' => 'due_date'])),
            ...$this->dueDates($this->ledger(['sort' => 'due_date', 'page' => 2])),
        ];

        $this->assertCount(24, $dates);
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates, 'a página 2 continua de onde a página 1 parou');
    }

    public function test_equal_dates_have_a_stable_tie_break_by_origin_then_id(): void
    {
        $sale = $this->sale('VENDA-EMPATE', 'Cliente Empate', ['2026-05-01', '2026-05-01']);
        $manualB = $this->manual('Manual B', '2026-05-01');
        $manualA = $this->manual('Manual A', '2026-05-01');

        $expected = [
            'manual-'.$manualB->id,
            'manual-'.$manualA->id,
            'sale-'.$sale->installments[0]->id,
            'sale-'.$sale->installments[1]->id,
        ];

        foreach ([1, 2, 3] as $attempt) {
            $this->ledger(['sort' => 'due_date'])->assertInertia(fn (Assert $page) => $page
                ->where('items.data', fn ($rows) => collect($rows)->pluck('id')->all() === $expected)
            );
        }
    }

    public function test_receipt_order_never_mixes_pending_between_paid_entries(): void
    {
        $sale = $this->sale('VENDA-MISTA', 'Cliente Misto', ['2026-06-01', '2026-07-01', '2026-08-01']);
        $this->markInstallmentPaid($sale->installments[0], '2026-06-03');
        $other = $this->sale('VENDA-OUTRA', 'Cliente Outro', ['2026-05-01']);
        $this->markInstallmentPaid($other->installments[0], '2026-08-15');
        $manualPaid = $this->manual('Manual pago', '2026-07-10', '2026-07-12');
        $this->manual('Manual pendente', '2026-06-20');

        $this->ledger(['sort' => 'paid_at'])->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.paid_at', '2026-08-15')
            ->where('items.data.1.id', 'manual-'.$manualPaid->id)
            ->where('items.data.2.paid_at', '2026-06-03')
            // Pendentes depois de todas as pagas, por vencimento crescente.
            ->where('items.data.3.due_date', '2026-06-20')
            ->where('items.data.3.paid_at', null)
            ->where('items.data.4.installment_id', $sale->installments[1]->id)
            ->where('items.data.5.installment_id', $sale->installments[2]->id)
        );
    }

    public function test_each_tab_opens_in_its_default_order(): void
    {
        $sale = $this->sale('VENDA-ABAS', 'Cliente Abas', ['2026-03-01', '2026-04-01', '2026-05-01']);
        $this->markInstallmentPaid($sale->installments[0], '2026-03-02');
        $this->markInstallmentPaid($sale->installments[1], '2026-04-20');

        // Recebidas: recebimento decrescente.
        $this->ledger(['status' => 'paid'])->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'paid_at')
            ->where('items.data.0.paid_at', '2026-04-20')
            ->where('items.data.1.paid_at', '2026-03-02')
        );

        // Pendentes e Todas: vencimento crescente.
        $this->ledger(['status' => 'pending'])->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'due_date')
            ->has('items.data', 1)
        );
        $this->ledger([])->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'due_date')
            ->where('items.data.0.due_date', '2026-03-01')
            ->where('items.data.2.due_date', '2026-05-01')
        );

        // O usuário ainda pode trocar a ordem dentro da aba.
        $this->ledger(['status' => 'paid', 'sort' => 'due_date'])->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'due_date')
            ->where('items.data.0.paid_at', '2026-03-02')
        );
    }

    public function test_grouped_mode_orders_groups_by_their_best_row_for_the_active_sort(): void
    {
        $older = $this->sale('VENDA-ANTIGA', 'Cliente Antigo', ['2026-01-01', '2026-02-01']);
        $recent = $this->sale('VENDA-RECENTE', 'Cliente Recente', ['2026-03-01', '2026-04-01']);
        $this->markInstallmentPaid($older->installments[1], '2026-02-01');
        $this->markInstallmentPaid($recent->installments[1], '2026-04-02');
        $this->markInstallmentPaid($recent->installments[0], '2026-03-05');

        // Por vencimento: a venda antiga vem antes (jan < mar), parcelas em ordem.
        $this->ledger(['sort' => 'due_date', 'group' => 'sale'])->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.installment_id', $older->installments[0]->id)
            ->where('items.data.1.installment_id', $older->installments[1]->id)
            ->where('items.data.2.installment_id', $recent->installments[0]->id)
            ->where('items.data.3.installment_id', $recent->installments[1]->id)
        );

        // Por recebimento: a venda com o recebimento mais recente sobe; dentro dela, mais recente primeiro.
        $this->ledger(['sort' => 'paid_at', 'group' => 'sale'])->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.installment_id', $recent->installments[1]->id)
            ->where('items.data.1.installment_id', $recent->installments[0]->id)
            ->where('items.data.2.installment_id', $older->installments[1]->id)
            ->where('items.data.3.installment_id', $older->installments[0]->id)
        );
    }

    public function test_grouped_mode_keeps_manual_entries_as_single_rows_in_order(): void
    {
        $sale = $this->sale('VENDA-G', 'Cliente G', ['2026-01-01', '2026-06-01']);
        $manual = $this->manual('Manual março', '2026-03-01');

        $this->ledger(['group' => 'sale'])->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.installment_id', $sale->installments[0]->id)
            ->where('items.data.1.installment_id', $sale->installments[1]->id)
            ->where('items.data.2.id', 'manual-'.$manual->id)
            ->where('items.total', 3)
            ->where('items.last_page', 1)
        );
    }

    public function test_filters_still_apply_in_both_modes(): void
    {
        $this->sale('VENDA-FILTRO', 'Construtora Alfa', ['2026-01-01', '2026-02-01']);
        $this->sale('VENDA-OUTRO', 'Padaria Beta', ['2026-01-15']);
        $this->manual('Consultoria Alfa', '2026-01-20');

        foreach (['', 'sale'] as $group) {
            $this->ledger(['q' => 'Alfa', 'group' => $group])->assertInertia(fn (Assert $page) => $page
                ->has('items.data', 3)
                ->where('items.total', 3)
            );
            $this->ledger(['q' => 'Alfa', 'origin' => 'manual', 'group' => $group])->assertInertia(fn (Assert $page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.source', 'manual')
            );
            $this->ledger(['status' => 'cancelled', 'group' => $group])->assertInertia(fn (Assert $page) => $page
                ->has('items.data', 0)
                ->where('items.total', 0)
            );
        }
    }

    // ---- Paginação: links ---------------------------------------------------------------------

    public function test_grouped_pagination_follows_next_and_prev_with_filters_and_variable_page_size(): void
    {
        $months = fn (array $idx) => array_map(fn ($i) => now()->startOfYear()->addMonths($i)->toDateString(), $idx);
        $block = $this->sale('VENDA-ALVO-BLOCO', 'Alvo Bloco', $months(range(0, 17)));
        $next = $this->sale('VENDA-ALVO-SEGUINTE', 'Alvo Seguinte', $months([2, 20, 21]));
        $last = $this->sale('VENDA-ALVO-FINAL', 'Alvo Final', $months([22, 23, 24]));
        $this->sale('VENDA-FORA', 'Fora do filtro', $months([1, 3]));
        $filters = ['q' => 'Alvo', 'status' => 'pending', 'origin' => 'sale', 'sort' => 'due_date', 'group' => 'sale'];

        // Simula o Docker local: navegador em :8080, nginx repassando X-Forwarded-Port 80.
        $page1 = $this->pageProps($filters);
        $this->assertSame([1, 21, 24, 2], [$page1['from'], $page1['to'], $page1['total'], $page1['last_page']]);
        $this->assertCount(21, $page1['data']);
        $this->assertNull($page1['prev_page_url']);
        $this->assertLinkKeepsFilters($page1['next_page_url'], $filters, 2);
        foreach (collect($page1['links'])->whereNotNull('url') as $link) {
            $this->assertLinkKeepsFilters($link['url'], $filters);
        }
        $this->assertSame(['1', '2'], collect($page1['links'])->pluck('label')->filter(fn ($l) => ctype_digit($l))->values()->all());

        // «Próximo» = seguir next_page_url exatamente como o Inertia faria.
        $page2 = $this->followProps($page1['next_page_url']);
        $this->assertSame([2, 22, 24, 2], [$page2['current_page'], $page2['from'], $page2['to'], $page2['last_page']]);
        $this->assertSame(array_fill(0, 3, $last->id), array_column($page2['data'], 'sale_id'));
        $this->assertNull($page2['next_page_url']);
        $this->assertLinkKeepsFilters($page2['prev_page_url'], $filters, 1);

        // «Anterior» volta à mesma página 1.
        $back = $this->followProps($page2['prev_page_url']);
        $this->assertSame(array_column($page1['data'], 'id'), array_column($back['data'], 'id'));

        // Todas as linhas exatamente uma vez; nenhuma venda dividida entre páginas.
        $ids = [...array_column($page1['data'], 'id'), ...array_column($page2['data'], 'id')];
        $this->assertCount(24, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $salesByPage = [array_unique(array_column($page1['data'], 'sale_id')), array_unique(array_column($page2['data'], 'sale_id'))];
        $this->assertSame([], array_values(array_intersect(...$salesByPage)));
        $this->assertEqualsCanonicalizing([$block->id, $next->id], array_values($salesByPage[0]));
    }

    public function test_flat_pagination_links_keep_filters_and_cover_every_row_once(): void
    {
        $this->sale('VENDA-ALVO-A', 'Alvo A', array_map(fn ($i) => now()->startOfYear()->addMonths($i)->toDateString(), range(0, 14)));
        $this->sale('VENDA-ALVO-B', 'Alvo B', array_map(fn ($i) => now()->startOfYear()->addMonths($i)->addDays(10)->toDateString(), range(0, 9)));
        $this->manual('Fora do filtro', '2026-01-01');
        $filters = ['q' => 'Alvo', 'status' => 'pending', 'sort' => 'due_date'];

        $page1 = $this->pageProps($filters);
        $this->assertSame([1, 20, 25, 2], [$page1['from'], $page1['to'], $page1['total'], $page1['last_page']]);
        $this->assertLinkKeepsFilters($page1['next_page_url'], $filters, 2);

        $page2 = $this->followProps($page1['next_page_url']);
        $this->assertSame([21, 25], [$page2['from'], $page2['to']]);
        $this->assertLinkKeepsFilters($page2['prev_page_url'], $filters, 1);

        $ids = [...array_column($page1['data'], 'id'), ...array_column($page2['data'], 'id')];
        $this->assertCount(25, array_unique($ids));
        $dates = [...array_column($page1['data'], 'due_date'), ...array_column($page2['data'], 'due_date')];
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates);
    }

    public function test_payables_pagination_links_are_relative_and_keep_filters(): void
    {
        foreach (range(1, 22) as $i) {
            FinancePayable::query()->create([
                'title' => "Alvo conta {$i}",
                'amount_cents' => 1_000,
                'due_date' => now()->startOfYear()->addDays($i)->toDateString(),
                'status' => FinancePayableStatus::Pending,
                'created_by' => $this->admin->id,
            ]);
        }
        $filters = ['q' => 'Alvo', 'status' => 'pending', 'sort' => 'due_date'];

        $payables = $this->withHeaders(['Host' => 'localhost:8080', 'X-Forwarded-Port' => '80'])
            ->actingAs($this->admin)
            ->get(route('admin.financeiro.contas-a-pagar.index', $filters, false))
            ->assertOk()
            ->viewData('page')['props']['payables'];

        $this->assertSame(2, $payables['last_page']);
        $this->assertLinkKeepsFilters($payables['next_page_url'], $filters, 2);
    }

    // ---- Helpers ----------------------------------------------------------------------------

    /**
     * Props «items» da lista, com o Host/porta do Docker local (proxy repassa a porta interna 80).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function pageProps(array $query): array
    {
        return $this->followProps(route('admin.financeiro.contas-a-receber.index', $query, false));
    }

    /**
     * @return array<string, mixed>
     */
    private function followProps(string $url): array
    {
        return $this->withHeaders(['Host' => 'localhost:8080', 'X-Forwarded-Port' => '80'])
            ->actingAs($this->admin)
            ->get($url)
            ->assertOk()
            ->viewData('page')['props']['items'];
    }

    /**
     * Link relativo (mesma origem do navegador, qualquer porta), com todos os filtros e a página certa.
     *
     * @param  array<string, string>  $filters
     */
    private function assertLinkKeepsFilters(?string $url, array $filters, ?int $page = null): void
    {
        $this->assertNotNull($url);
        $this->assertStringStartsWith('/admin/financeiro/', $url, 'link de paginação deve ser relativo');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        foreach ($filters as $key => $value) {
            $this->assertSame($value, $query[$key] ?? null, "filtro {$key} perdido em {$url}");
        }
        if ($page !== null) {
            $this->assertSame((string) $page, $query['page'] ?? null);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function ledger(array $query): TestResponse
    {
        return $this->actingAs($this->admin)
            ->get(route('admin.financeiro.contas-a-receber.index', $query))
            ->assertOk();
    }

    /**
     * @return list<string>
     */
    private function dueDates(TestResponse $response): array
    {
        return collect($response->viewData('page')['props']['items']['data'])->pluck('due_date')->all();
    }

    /**
     * @param  list<string>  $dueDates
     */
    private function sale(string $code, string $client, array $dueDates): CommercialSale
    {
        $sale = CommercialSale::query()->create([
            'code' => $code,
            'client_name' => $client,
            'total_cents' => 10_000 * count($dueDates),
            'installments_count' => count($dueDates),
            'payment_method' => 'pix',
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
            'created_by' => $this->admin->id,
            'seller_id' => $this->admin->id,
        ]);

        foreach ($dueDates as $index => $dueDate) {
            $sale->installments()->create([
                'number' => $index + 1,
                'amount_cents' => 10_000,
                'due_date' => $dueDate,
                'method' => 'pix',
                'status' => CommercialSaleInstallment::STATUS_PENDENTE,
            ]);
        }

        return $sale->load(['installments' => fn ($q) => $q->orderBy('number')]);
    }

    private function manual(string $title, string $dueDate, ?string $paidDay = null): FinanceReceivable
    {
        return FinanceReceivable::query()->create([
            'title' => $title,
            'amount_cents' => 5_000,
            'due_date' => $dueDate,
            'status' => $paidDay ? FinanceReceivableStatus::Paid : FinanceReceivableStatus::Pending,
            'paid_at' => $paidDay ? $paidDay.' 00:00:00' : null,
            'paid_amount_cents' => $paidDay ? 5_000 : null,
            'created_by' => $this->admin->id,
        ]);
    }

    private function markInstallmentPaid(CommercialSaleInstallment $installment, string $day): void
    {
        $installment->update([
            'status' => CommercialSaleInstallment::STATUS_PAGO,
            'paid_at' => $day.' 00:00:00',
            'paid_amount_cents' => $installment->amount_cents,
        ]);
    }
}
