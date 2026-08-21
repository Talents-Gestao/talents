<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Finance;

use App\Enums\FinanceBankAccountType;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Models\FinanceBankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallmentPaymentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_payment_marks_installment_as_paid(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-INST-PAY',
            'client_name' => 'Cliente Parcela',
            'employee_count' => 5,
            'total_final_cents' => 225_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.converter', $proposal), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => '2026-08-07',
        ])->assertRedirect();

        $sale = CommercialSale::query()->where('proposal_id', $proposal->id)->firstOrFail();
        $installment = $sale->installments()->firstOrFail();
        $account = $this->bankAccount($admin);

        $response = $this->actingAs($admin)
            ->from(route('admin.financeiro.vendas.show', $sale->id))
            ->patch(
                route('admin.financeiro.parcelas.pagamento', $installment),
                [
                    'status' => 'pago',
                    'paid_at' => '2026-08-12',
                    'paid_amount_cents' => 225_000,
                    'bank_account_id' => $account->id,
                    'notes' => '',
                ],
            );

        $response
            ->assertRedirect(route('admin.financeiro.vendas.show', $sale->id))
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $installment->refresh();
        $this->assertSame('pago', $installment->status);
        $this->assertSame(225_000, (int) $installment->paid_amount_cents);
        $this->assertSame('2026-08-12', $installment->paid_at?->toDateString());
    }

    public function test_register_payment_accepts_multipart_like_inertia_form_data(): void
    {
        $this->withoutVite();
        Storage::fake('local');

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-INST-FD',
            'client_name' => 'Cliente FormData',
            'employee_count' => 5,
            'total_final_cents' => 225_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.converter', $proposal), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => '2026-08-07',
        ])->assertRedirect();

        $sale = CommercialSale::query()->where('proposal_id', $proposal->id)->firstOrFail();
        $installment = $sale->installments()->firstOrFail();
        $account = $this->bankAccount($admin);

        // Simula o browser: POST multipart + _method=PATCH (PHP não parseia multipart em PATCH).
        $response = $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-a-receber.index'))
            ->post(
                route('admin.financeiro.parcelas.pagamento', $installment),
                [
                    '_method' => 'patch',
                    'status' => 'pago',
                    'paid_at' => '2026-08-12',
                    'paid_amount_cents' => '225000',
                    'bank_account_id' => $account->id,
                    'notes' => '',
                    'receipt' => UploadedFile::fake()->image('comprovante.jpg'),
                ],
            );

        $response
            ->assertRedirect(route('admin.financeiro.contas-a-receber.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('pago', $installment->fresh()->status);
        $this->assertNotNull($installment->fresh()->receipt_path);
    }

    public function test_register_payment_patch_multipart_without_spoof_loses_payload(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $proposal = CommercialProposal::query()->create([
            'code' => 'PROP-INST-PATCH',
            'client_name' => 'Cliente Patch Multipart',
            'employee_count' => 5,
            'total_final_cents' => 225_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.converter', $proposal), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => '2026-08-07',
        ])->assertRedirect();

        $installment = CommercialSale::query()
            ->where('proposal_id', $proposal->id)
            ->firstOrFail()
            ->installments()
            ->firstOrFail();

        // Reproduz o bug: PATCH + multipart (corpo vazio no PHP) → validação falha.
        $this->actingAs($admin)
            ->call(
                'PATCH',
                route('admin.financeiro.parcelas.pagamento', $installment),
                [],
                [],
                [
                    'receipt' => UploadedFile::fake()->image('comprovante.jpg'),
                ],
                [
                    'CONTENT_TYPE' => 'multipart/form-data; boundary=----test',
                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
                ],
            )
            ->assertSessionHasErrors(['status']);

        $this->assertSame('pendente', $installment->fresh()->status);
    }

    public function test_register_payment_rejects_zero_amount(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $installment = $this->convertedInstallment($admin, 'PROP-INST-ZERO');
        $account = $this->bankAccount($admin);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.vendas.show', $installment->sale_id))
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => 'pago',
                'paid_at' => '2026-08-12',
                'paid_amount_cents' => 0,
                'bank_account_id' => $account->id,
            ])
            ->assertRedirect(route('admin.financeiro.vendas.show', $installment->sale_id))
            ->assertSessionHasErrors('paid_amount_cents');

        $this->assertSame('pendente', $installment->fresh()->status);
    }

    public function test_register_payment_rejects_amount_above_installment(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $installment = $this->convertedInstallment($admin, 'PROP-INST-OVER');
        $account = $this->bankAccount($admin);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.vendas.show', $installment->sale_id))
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => 'pago',
                'paid_at' => '2026-08-12',
                'paid_amount_cents' => 300_000,
                'bank_account_id' => $account->id,
            ])
            ->assertRedirect(route('admin.financeiro.vendas.show', $installment->sale_id))
            ->assertSessionHasErrors('paid_amount_cents');

        $this->assertSame('pendente', $installment->fresh()->status);
    }

    public function test_register_payment_from_receivables_marks_sale_installment_paid(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $installment = $this->convertedInstallment($admin, 'PROP-INST-CR');
        $account = $this->bankAccount($admin);

        $this->actingAs($admin)
            ->from(route('admin.financeiro.contas-a-receber.index'))
            ->patch(route('admin.financeiro.parcelas.pagamento', $installment), [
                'status' => 'pago',
                'paid_at' => '2026-08-12',
                'paid_amount_cents' => 225_000,
                'bank_account_id' => $account->id,
            ])
            ->assertRedirect(route('admin.financeiro.contas-a-receber.index'))
            ->assertSessionHas('success');

        $this->assertSame('pago', $installment->fresh()->status);
    }

    public function test_admin_can_update_sale_commercial_fields(): void
    {
        $this->withoutVite();

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $seller = User::factory()->create([
            'is_commercial' => true,
            'is_active' => true,
            'name' => 'Vendedor Edit',
        ]);
        $installment = $this->convertedInstallment($admin, 'PROP-SALE-EDIT');
        $sale = $installment->sale;

        $this->actingAs($admin)
            ->get(route('admin.financeiro.vendas.edit', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Finance/Sales/Form')
                ->where('mode', 'edit')
                ->where('sale.code', $sale->code));

        $this->actingAs($admin)
            ->put(route('admin.financeiro.vendas.update', $sale), [
                'client_name' => 'Cliente Atualizado',
                'client_cnpj' => '12.345.678/0001-90',
                'client_email' => 'novo@cliente.test',
                'client_phone' => '11999990000',
                'seller_id' => $seller->id,
                'sold_at' => '2026-08-15',
                'notes' => 'Nota editada',
            ])
            ->assertRedirect(route('admin.financeiro.vendas.show', $sale))
            ->assertSessionHas('success');

        $sale->refresh();
        $this->assertSame('Cliente Atualizado', $sale->client_name);
        $this->assertSame($seller->id, (int) $sale->seller_id);
        $this->assertSame('2026-08-15', $sale->sold_at?->toDateString());
        $this->assertSame('Nota editada', $sale->notes);
        $this->assertSame(225_000, (int) $sale->total_cents);
    }

    private function convertedInstallment(User $admin, string $code): CommercialSaleInstallment
    {
        $proposal = CommercialProposal::query()->create([
            'code' => $code,
            'client_name' => 'Cliente Parcela',
            'employee_count' => 5,
            'total_final_cents' => 225_000,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.comercial.propostas.converter', $proposal), [
            'payment_method' => 'pix',
            'installments_count' => 1,
            'first_due_date' => '2026-08-07',
        ])->assertRedirect();

        return CommercialSale::query()
            ->where('proposal_id', $proposal->id)
            ->firstOrFail()
            ->installments()
            ->firstOrFail();
    }

    private function bankAccount(User $admin): FinanceBankAccount
    {
        return FinanceBankAccount::query()->create([
            'name' => 'Conta Teste',
            'type' => FinanceBankAccountType::Checking,
            'initial_balance_cents' => 0,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }
}
