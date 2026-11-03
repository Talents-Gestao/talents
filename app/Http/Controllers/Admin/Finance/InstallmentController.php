<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Finance\ChangeFinancePaymentDate;
use App\Actions\Finance\RegisterFinancePayment;
use App\Http\Controllers\Controller;
use App\Models\CommercialSale;
use App\Models\CommercialSaleInstallment;
use App\Support\Finance\FinancePaymentDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstallmentController extends Controller
{
    public function __construct(
        private readonly RegisterFinancePayment $registerPayment,
        private readonly ChangeFinancePaymentDate $changePaymentDate,
    ) {}

    public function registerPayment(Request $request, CommercialSaleInstallment $installment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                CommercialSaleInstallment::STATUS_PENDENTE,
                CommercialSaleInstallment::STATUS_PAGO,
                CommercialSaleInstallment::STATUS_CANCELADO,
            ])],
            'paid_at' => FinancePaymentDate::rules(),
            'payment_date_reason' => FinancePaymentDate::reasonRules(),
            'paid_amount_cents' => ['nullable', 'integer', 'min:0'],
            'bank_account_id' => [
                // Exigida só na baixa nova: parcela já paga sem conta (legado) pode ter a data corrigida.
                Rule::requiredIf(fn () => $request->input('status') === CommercialSaleInstallment::STATUS_PAGO
                    && ! $installment->isPaid()),
                'nullable',
                'exists:finance_bank_accounts,id',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'bank_account_id.required' => 'Selecione a conta de destino/recebimento.',
            ...FinancePaymentDate::messages(),
        ]);

        $wasPaid = $installment->isPaid();
        $isPaid = $data['status'] === CommercialSaleInstallment::STATUS_PAGO;

        // Mesma regra da baixa manual e da conta a pagar: cancelada não recebe baixa direta.
        // Para receber, reative a parcela pela edição.
        if ($isPaid && $installment->status === CommercialSaleInstallment::STATUS_CANCELADO) {
            return back()->with('error', 'Não é possível receber uma parcela cancelada.');
        }

        $cancellationChanged = ($installment->status === CommercialSaleInstallment::STATUS_CANCELADO)
            !== ($data['status'] === CommercialSaleInstallment::STATUS_CANCELADO);
        $paidAmountCents = $isPaid
            ? $this->assertPaidAmountMatchesInstallment(
                $installment,
                isset($data['paid_amount_cents']) ? (int) $data['paid_amount_cents'] : null,
            )
            : null;

        $update = ['notes' => $data['notes'] ?? $installment->notes];

        // O comprovante só é trocado quando vem um arquivo novo.
        if ($request->hasFile('receipt')) {
            if ($installment->receipt_path && Storage::disk('local')->exists($installment->receipt_path)) {
                Storage::disk('local')->delete($installment->receipt_path);
            }

            $update['receipt_path'] = $request->file('receipt')->store(
                "commercial-receipts/{$installment->sale_id}",
                'local'
            );
        }

        DB::transaction(function () use ($request, $installment, $data, $update, $wasPaid, $isPaid, $paidAmountCents, $cancellationChanged): void {
            if (! $isPaid) {
                $installment->update([
                    ...$update,
                    'status' => $data['status'],
                    'paid_at' => null,
                    'paid_amount_cents' => null,
                    'bank_account_id' => null,
                ]);
                $installment->sale->recalculateStatus();
            } elseif ($wasPaid) {
                $installment->update([...$update, 'bank_account_id' => $this->bankAccountFor($installment, $data)]);

                // Já baixada: só corrige a data (auditada), sem reenviar o aviso.
                if (filled($data['paid_at'] ?? null)) {
                    $this->changePaymentDate->handle(
                        $installment,
                        FinancePaymentDate::toPaidAt($data['paid_at']),
                        $request->user(),
                        $data['payment_date_reason'] ?? null,
                    );
                }
            } else {
                $installment->update([...$update, 'bank_account_id' => (int) $data['bank_account_id']]);
                $this->registerPayment->handle(
                    $installment,
                    FinancePaymentDate::toPaidAt($data['paid_at'] ?? null),
                    (int) $data['bank_account_id'],
                    paidAmountCents: $paidAmountCents,
                    actor: $request->user(),
                );
            }

            // Cancelar/reativar parcela muda o valor da venda (canceladas não entram no total).
            // Na mesma transação da baixa: status e total da venda nunca ficam divergentes.
            if ($cancellationChanged) {
                $this->syncSaleTotalFromInstallments($installment);
            }
        });

        return redirect()
            ->back()
            ->with('success', 'Parcela atualizada.');
    }

    public function update(Request $request, CommercialSaleInstallment $installment): RedirectResponse
    {
        $data = $request->validate([
            'due_date' => ['required', 'date'],
            'amount_reais' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['pix', 'boleto', 'cartao'])],
            'status' => ['required', Rule::in([
                CommercialSaleInstallment::STATUS_PENDENTE,
                CommercialSaleInstallment::STATUS_PAGO,
                CommercialSaleInstallment::STATUS_CANCELADO,
            ])],
            'bank_account_id' => [
                // Exigida só na baixa nova: parcela já paga sem conta (legado) pode ter a data corrigida.
                Rule::requiredIf(fn () => $request->input('status') === CommercialSaleInstallment::STATUS_PAGO
                    && ! $installment->isPaid()),
                'nullable',
                'exists:finance_bank_accounts,id',
            ],
            'paid_at' => FinancePaymentDate::rules(),
            'payment_date_reason' => FinancePaymentDate::reasonRules(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'due_date.required' => 'Informe o vencimento.',
            'amount_reais.required' => 'Informe o valor.',
            'method.required' => 'Selecione o método.',
            'status.required' => 'Selecione o status.',
            'bank_account_id.required' => 'Selecione a conta de destino/recebimento.',
            ...FinancePaymentDate::messages(),
        ]);

        $amountCents = (int) round(((float) $data['amount_reais']) * 100);
        $wasPaid = $installment->isPaid();

        if ($wasPaid) {
            $amountCents = (int) $installment->amount_cents;
        }

        if ($amountCents < 1) {
            throw ValidationException::withMessages([
                'amount_reais' => 'Informe um valor maior que zero.',
            ]);
        }

        $update = [
            'due_date' => $data['due_date'],
            'amount_cents' => $amountCents,
            'method' => $data['method'],
            'notes' => $data['notes'] ?? null,
        ];

        DB::transaction(function () use ($request, $installment, $data, $update, $wasPaid, $amountCents): void {
            if ($data['status'] !== CommercialSaleInstallment::STATUS_PAGO) {
                $installment->update([
                    ...$update,
                    'status' => $data['status'],
                    'paid_at' => null,
                    'paid_amount_cents' => null,
                    'bank_account_id' => null,
                ]);
            } elseif ($wasPaid) {
                $installment->update([...$update, 'bank_account_id' => $this->bankAccountFor($installment, $data)]);

                if (filled($data['paid_at'] ?? null)) {
                    $this->changePaymentDate->handle(
                        $installment,
                        FinancePaymentDate::toPaidAt($data['paid_at']),
                        $request->user(),
                        $data['payment_date_reason'] ?? null,
                    );
                }
            } else {
                $installment->update($update);
                $this->registerPayment->handle(
                    $installment,
                    FinancePaymentDate::toPaidAt($data['paid_at'] ?? null),
                    (int) $data['bank_account_id'],
                    paidAmountCents: $amountCents,
                    actor: $request->user(),
                );
            }

            $this->syncSaleTotalFromInstallments($installment);
            $installment->sale->recalculateStatus();
        });

        return redirect()
            ->back()
            ->with('success', 'Parcela atualizada.');
    }

    public function receipt(CommercialSaleInstallment $installment): StreamedResponse
    {
        if (! $installment->receipt_path || ! Storage::disk('local')->exists($installment->receipt_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($installment->receipt_path);
    }

    private function assertPaidAmountMatchesInstallment(
        CommercialSaleInstallment $installment,
        ?int $paidAmountCents,
    ): int {
        $amountCents = (int) $installment->amount_cents;
        if ($amountCents < 1) {
            throw ValidationException::withMessages([
                'paid_amount_cents' => 'Não é possível quitar uma parcela sem valor.',
            ]);
        }

        $paid = $paidAmountCents ?? $amountCents;
        if ($paid < 1) {
            throw ValidationException::withMessages([
                'paid_amount_cents' => 'Informe um valor pago maior que zero.',
            ]);
        }

        if ($paid > $amountCents) {
            throw ValidationException::withMessages([
                'paid_amount_cents' => 'O valor pago não pode ser maior que o valor da parcela.',
            ]);
        }

        if ($paid !== $amountCents) {
            throw ValidationException::withMessages([
                'paid_amount_cents' => 'O valor pago deve ser igual ao valor da parcela.',
            ]);
        }

        return $paid;
    }

    /**
     * Parcela já paga: conta informada ou, sem ela, a atual (inclusive nula, no legado).
     *
     * @param  array<string, mixed>  $data
     */
    private function bankAccountFor(CommercialSaleInstallment $installment, array $data): ?int
    {
        return filled($data['bank_account_id'] ?? null)
            ? (int) $data['bank_account_id']
            : $installment->bank_account_id;
    }

    /**
     * Parcelas canceladas não compõem o valor da venda (mesma regra de CommercialSale::recalculateStatus).
     */
    private function syncSaleTotalFromInstallments(CommercialSaleInstallment $installment): void
    {
        $sale = $installment->sale;
        $sale->update([
            'total_cents' => CommercialSale::totalFromInstallments($sale->installments()->get()),
        ]);
    }
}
