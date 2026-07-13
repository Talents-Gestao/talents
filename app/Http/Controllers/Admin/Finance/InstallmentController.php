<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Notices\PublishCommercialNotice;
use App\Http\Controllers\Controller;
use App\Models\CommercialSaleInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            $update['paid_at'] = isset($data['paid_at'])
                ? \Carbon\Carbon::parse($data['paid_at'])
                : now();
            $update['paid_amount_cents'] = (int) ($data['paid_amount_cents'] ?? $installment->amount_cents);
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

    public function receipt(CommercialSaleInstallment $installment): StreamedResponse
    {
        if (! $installment->receipt_path || ! Storage::disk('local')->exists($installment->receipt_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($installment->receipt_path);
    }
}
