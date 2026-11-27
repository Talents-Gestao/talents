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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Vencimento em Contas a pagar e Contas a receber (inclusive parcela): só o dia (Y-m-d) e
 * ano entre 2000 e 2100. Digitar «6» no ano do <input type="date"> gravava 0006-10-01.
 */
class FinanceDueDateValidationTest extends TestCase
{
    use RefreshDatabase;

    private const RANGE_MESSAGE = 'O vencimento deve estar entre 01/01/2000 e 31/12/2100.';

    private User $admin;

    private FinanceBankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-20 12:00:00', 'America/Sao_Paulo'));

        $this->admin = User::factory()->superAdmin()->create(['is_owner' => true, 'is_commercial' => true]);
        $this->bank = FinanceBankAccount::query()->create([
            'name' => 'Banco Principal',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidDueDates(): array
    {
        return [
            'ano 0006' => ['0006-10-01', self::RANGE_MESSAGE],
            'antes de 2000' => ['1999-12-31', self::RANGE_MESSAGE],
            'depois de 2100' => ['2101-01-01', self::RANGE_MESSAGE],
            'formato brasileiro' => ['01/10/2026', 'Informe um vencimento válido.'],
            'com horário' => ['2026-10-01 10:00:00', 'Informe um vencimento válido.'],
        ];
    }

    #[DataProvider('invalidDueDates')]
    public function test_payable_store_rejects_invalid_due_date(string $dueDate, string $message): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                ...$this->payablePayload(),
                'due_date' => $dueDate,
            ])
            ->assertSessionHasErrors(['due_date' => $message]);

        $this->assertSame(0, FinancePayable::query()->count());
    }

    #[DataProvider('invalidDueDates')]
    public function test_payable_update_rejects_invalid_due_date(string $dueDate, string $message): void
    {
        $payable = FinancePayable::query()->create([
            'title' => 'Aluguel',
            'amount_cents' => 10_000,
            'due_date' => '2026-08-10',
            'status' => FinancePayableStatus::Pending,
            'is_recurring' => false,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-pagar.update', $payable), [
                ...$this->payablePayload(),
                'due_date' => $dueDate,
            ])
            ->assertSessionHasErrors(['due_date' => $message]);

        $this->assertSame('2026-08-10', $payable->fresh()->due_date->toDateString());
    }

    #[DataProvider('invalidDueDates')]
    public function test_receivable_store_rejects_invalid_due_date(string $dueDate, string $message): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.financeiro.contas-a-receber.store'), [
                ...$this->receivablePayload(),
                'due_date' => $dueDate,
            ])
            ->assertSessionHasErrors(['due_date' => $message]);

        $this->assertSame(0, FinanceReceivable::query()->count());
    }

    #[DataProvider('invalidDueDates')]
    public function test_receivable_update_rejects_invalid_due_date(string $dueDate, string $message): void
    {
        $receivable = FinanceReceivable::query()->create([
            'title' => 'Receita manual',
            'amount_cents' => 5_000,
            'due_date' => '2026-08-05',
            'status' => FinanceReceivableStatus::Pending,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.financeiro.contas-a-receber.update', $receivable), [
                ...$this->receivablePayload(),
                'due_date' => $dueDate,
            ])
            ->assertSessionHasErrors(['due_date' => $message]);

        $this->assertSame('2026-08-05', $receivable->fresh()->due_date->toDateString());
    }

    #[DataProvider('invalidDueDates')]
    public function test_installment_update_rejects_invalid_due_date(string $dueDate, string $message): void
    {
        $installment = $this->pendingInstallment();

        $this->actingAs($this->admin)
            ->patch(route('admin.financeiro.parcelas.update', $installment), [
                'due_date' => $dueDate,
                'amount_reais' => '100.00',
                'method' => 'pix',
                'status' => CommercialSaleInstallment::STATUS_PENDENTE,
            ])
            ->assertSessionHasErrors(['due_date' => $message]);

        $this->assertSame('2026-08-05', $installment->fresh()->due_date->toDateString());
    }

    public function test_due_date_limits_are_accepted(): void
    {
        foreach (['2000-01-01', '2100-12-31'] as $dueDate) {
            $this->actingAs($this->admin)
                ->post(route('admin.financeiro.contas-a-pagar.store'), [
                    ...$this->payablePayload(),
                    'due_date' => $dueDate,
                ])
                ->assertSessionHasNoErrors();

            $this->actingAs($this->admin)
                ->post(route('admin.financeiro.contas-a-receber.store'), [
                    ...$this->receivablePayload(),
                    'due_date' => $dueDate,
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertEqualsCanonicalizing(
            ['2000-01-01', '2100-12-31'],
            FinancePayable::query()->pluck('due_date')->map->toDateString()->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['2000-01-01', '2100-12-31'],
            FinanceReceivable::query()->pluck('due_date')->map->toDateString()->all(),
        );
    }

    public function test_missing_due_date_keeps_the_required_message(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.financeiro.contas-a-pagar.store'), [
                ...$this->payablePayload(),
                'due_date' => '',
            ])
            ->assertSessionHasErrors(['due_date' => 'Informe o vencimento.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payablePayload(): array
    {
        return [
            'title' => 'Aluguel',
            'amount_reais' => '100.00',
            'due_date' => '2026-08-10',
            'status' => FinancePayableStatus::Pending->value,
            'bank_account_id' => $this->bank->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receivablePayload(): array
    {
        return [
            'title' => 'Receita manual',
            'amount_reais' => '50.00',
            'due_date' => '2026-08-05',
            'status' => FinanceReceivableStatus::Pending->value,
            'bank_account_id' => $this->bank->id,
        ];
    }

    private function pendingInstallment(): CommercialSaleInstallment
    {
        $sale = CommercialSale::query()->create([
            'code' => 'VENDA-VENC-1',
            'client_name' => 'Cliente Vencimento',
            'total_cents' => 10_000,
            'installments_count' => 1,
            'payment_method' => 'pix',
            'status' => CommercialSale::STATUS_ABERTA,
            'sold_at' => now(),
            'created_by' => $this->admin->id,
            'seller_id' => $this->admin->id,
        ]);

        return $sale->installments()->create([
            'number' => 1,
            'amount_cents' => 10_000,
            'due_date' => '2026-08-05',
            'method' => 'pix',
            'status' => CommercialSaleInstallment::STATUS_PENDENTE,
        ]);
    }
}
