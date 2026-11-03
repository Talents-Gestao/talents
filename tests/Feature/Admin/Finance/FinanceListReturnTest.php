<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Enums\FinanceBankAccountType;
use App\Enums\FinancePayableStatus;
use App\Enums\FinanceReceivableStatus;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\FinanceBankAccount;
use App\Models\FinancePayable;
use App\Models\FinanceReceivable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Toda gravação do Financeiro volta para a lista com a query atual (busca, aba, origem,
 * ordenação, agrupamento, página) — pelo modal da lista ou pela página Editar/Novo.
 */
class FinanceListReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FinanceBankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->bank = FinanceBankAccount::query()->create([
            'name' => 'Banco',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_receivable_saves_return_to_the_filtered_list(): void
    {
        $receivable = $this->receivable();
        $list = '/admin/financeiro/contas-a-receber?q=%5BTESTE%20JIRA%5D&status=paid&sort=paid_at&group=sale&page=2';
        $this->visitList($list);

        // Editar (modal da lista ou página Editar).
        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), $this->receivablePayload(['title' => 'Novo título']))
            ->assertSessionHasNoErrors()
            ->assertRedirect($list);

        // Novo recebimento.
        $this->actingAs($this->admin)
            ->post(route('admin.financeiro.contas-a-receber.store'), $this->receivablePayload())
            ->assertRedirect($list);

        // Remover.
        $this->actingAs($this->admin)
            ->delete(route('admin.financeiro.contas-a-receber.destroy', $receivable))
            ->assertRedirect($list);
    }

    public function test_payable_saves_return_to_the_filtered_list(): void
    {
        $payable = FinancePayable::query()->create([
            'title' => 'Aluguel',
            'amount_cents' => 1_000,
            'due_date' => '2026-08-05',
            'status' => FinancePayableStatus::Pending,
            'created_by' => $this->admin->id,
        ]);
        $list = '/admin/financeiro/contas-a-pagar?q=Aluguel&status=pending&sort=due_date&page=1';
        $this->visitList($list);

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), $this->payablePayload(['title' => 'Aluguel sala']))
            ->assertSessionHasNoErrors()
            ->assertRedirect($list);
        $this->actingAs($this->admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), $this->payablePayload())
            ->assertRedirect($list);
        $this->actingAs($this->admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                ...$this->payablePayload(),
                'is_recurring' => true,
                'recurring_months' => 2,
            ])
            ->assertRedirect($list);
        $this->actingAs($this->admin)
            ->delete(route('admin.financeiro.contas-a-pagar.destroy', $payable))
            ->assertRedirect($list);
    }

    public function test_receivables_and_payables_remember_their_own_list(): void
    {
        $receivable = $this->receivable();
        $this->visitList('/admin/financeiro/contas-a-receber?q=receita');
        $this->visitList('/admin/financeiro/contas-a-pagar?q=aluguel');

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), $this->receivablePayload())
            ->assertRedirect('/admin/financeiro/contas-a-receber?q=receita');
    }

    public function test_without_a_remembered_list_it_returns_to_the_plain_list(): void
    {
        $receivable = $this->receivable();

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), $this->receivablePayload())
            ->assertRedirect(route('admin.financeiro.contas-a-receber.index'));
    }

    public function test_a_tampered_return_value_never_leaves_the_list(): void
    {
        $receivable = $this->receivable();

        foreach (['https://evil.example/phish', '//evil.example', '/admin/financeiro/contas-a-pagar?q=x', '/admin/financeiro/contas-a-receber-falsa'] as $tampered) {
            $this->actingAs($this->admin)
                ->withSession(['finance.list_return.admin.financeiro.contas-a-receber.index' => $tampered])
                ->put(route('admin.financeiro.contas-a-receber.update', $receivable), $this->receivablePayload())
                ->assertRedirect(route('admin.financeiro.contas-a-receber.index'));
        }
    }

    public function test_installment_save_from_the_list_goes_back_to_the_same_filtered_url(): void
    {
        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-VOLTA',
            'client_name' => '[TESTE JIRA] Volta',
            'total_cents' => 10_000,
            'installments_count' => 1,
            'payment_method' => 'pix',
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
            'created_by' => $this->admin->id,
            'seller_id' => $this->admin->id,
        ]);
        $installment = $sale->installments()->create([
            'number' => 1,
            'amount_cents' => 10_000,
            'due_date' => '2026-08-05',
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);
        // O navegador está em :8080 (Docker local); o retorno usa o Referer, com a porta.
        $list = 'http://localhost:8080/admin/financeiro/contas-a-receber?q=%5BTESTE%20JIRA%5D&status=paid&sort=paid_at';

        $this->actingAs($this->admin)
            ->withHeaders(['Referer' => $list])
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                'due_date' => '2026-08-06',
                'amount_reais' => '100.00',
                'method' => 'pix',
                'status' => CommercialSaleInstallment::STATUS_PENDENTE,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($list);
    }

    private function visitList(string $uri): void
    {
        $this->actingAs($this->admin)->get($uri)->assertOk();
    }

    private function receivable(): FinanceReceivable
    {
        return FinanceReceivable::query()->create([
            'title' => 'Receita',
            'amount_cents' => 5_000,
            'due_date' => '2026-08-05',
            'status' => FinanceReceivableStatus::Pending,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function receivablePayload(array $overrides = []): array
    {
        return [
            'title' => 'Receita',
            'amount_reais' => '50.00',
            'due_date' => '2026-08-05',
            'status' => 'pending',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payablePayload(array $overrides = []): array
    {
        return [
            'title' => 'Aluguel',
            'amount_reais' => '10.00',
            'due_date' => '2026-08-05',
            'status' => 'pending',
            ...$overrides,
        ];
    }
}
