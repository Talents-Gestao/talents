<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\CommercialCommission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommissionController extends Controller
{
    public function update(Request $request, CommercialCommission $commission): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                CommercialCommission::STATUS_A_PAGAR,
                CommercialCommission::STATUS_PAGA,
            ])],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $update = [
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $commission->notes,
        ];

        if ($data['status'] === CommercialCommission::STATUS_PAGA) {
            $update['paid_at'] = isset($data['paid_at'])
                ? \Carbon\Carbon::parse($data['paid_at'])
                : now();
        } else {
            $update['paid_at'] = null;
        }

        $commission->update($update);

        return redirect()
            ->route('admin.financeiro.vendas.show', $commission->sale_id)
            ->with('success', 'Comissão atualizada.');
    }
}
