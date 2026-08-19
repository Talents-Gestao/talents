<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Notices\PublishCommercialNotice;
use App\Http\Controllers\Controller;
use App\Models\CommercialSaleInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstallmentController extends Controller
{
    public function __construct(
        private readonly PublishCommercialNotice $notices,
    ) {}

    public function registerPayment(Request $request, CommercialSaleInstallment $installment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                CommercialSaleInstallment::STATUS_PENDENTE,
                CommercialSaleInstallment::STATUS_PAGO,
                CommercialSaleInstallment::STATUS_CANCELADO,
            ])],
            'paid_at' => ['nullable', 'date'],
            'paid_amount_cents' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $update = [
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $installment->notes,
        ];

        if ($data['status'] === CommercialSaleInstallment::STATUS_PAGO) {
            $paidAmountCents = $this->assertPaidAmountMatchesInstallment(
                $installment,
                isset($data['paid_amount_cents']) ? (int) $data['paid_amount_cents'] : null,
            );
            $update['paid_at'] = isset($data['paid_at'])
                ? \Carbon\Carbon::parse($data['paid_at'])
                : now();
            $update['paid_amount_cents'] = $paidAmountCents;
        } else {
            $update['paid_at'] = null;
            $update['paid_amount_cents'] = null;
        }

        if ($request->hasFile('receipt')) {
            if ($installment->receipt_path && Storage::disk('local')->exists($installment->receipt_path)) {
                Storage::disk('local')->delete($installment->receipt_path);
            }

            $update['receipt_path'] = $request->file('receipt')->store(
                "commercial-receipts/{$installment->sale_id}",
                'local'
            );
        }

        $wasPaid = $installment->getOriginal('status') === CommercialSaleInstallment::STATUS_PAGO;

        $installment->update($update);

        $installment->sale->recalculateStatus();

        if ($data['status'] === CommercialSaleInstallment::STATUS_PAGO && ! $wasPaid) {
            $this->notices->installmentPaid($installment->fresh('sale'), $request->user());
        }

        return redirect()
            ->route('admin.financeiro.vendas.show', $installment->sale_id)
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
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'due_date.required' => 'Informe o vencimento.',
            'amount_reais.required' => 'Informe o valor.',
            'method.required' => 'Selecione o método.',
            'status.required' => 'Selecione o status.',
        ]);

        $amountCents = (int) round(((float) $data['amount_reais']) * 100);
        $wasPaid = $installment->status === CommercialSaleInstallment::STATUS_PAGO;

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
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ];

        if ($data['status'] === CommercialSaleInstallment::STATUS_PAGO) {
            $update['paid_at'] = $installment->paid_at ?? now();
            $update['paid_amount_cents'] = $amountCents;
        } else {
            $update['paid_at'] = null;
            $update['paid_amount_cents'] = null;
        }

        $installment->update($update);
        $this->syncSaleTotalFromInstallments($installment);
        $installment->sale->recalculateStatus();

        if ($data['status'] === CommercialSaleInstallment::STATUS_PAGO && ! $wasPaid) {
            $this->notices->installmentPaid($installment->fresh('sale'), $request->user());
        }

        return redirect()
            ->route('admin.financeiro.contas-a-receber.index')
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

    private function syncSaleTotalFromInstallments(CommercialSaleInstallment $installment): void
    {
        $sale = $installment->sale;
        $sale->update([
            'total_cents' => (int) $sale->installments()->sum('amount_cents'),
        ]);
    }
}
