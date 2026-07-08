<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Notices\PublishCommercialNotice;
use App\Http\Controllers\Controller;
use App\Models\CommercialCommission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommissionController extends Controller
{
    public function __construct(
        private readonly PublishCommercialNotice $notices,
    ) {}

    public function index(Request $request): Response
    {
        $q = CommercialCommission::query()
            ->with([
                'seller:id,name',
                'sale:id,code,client_name,client_cnpj,sold_at',
            ]);

        if ($request->filled('search')) {
            $s = (string) $request->string('search');
            $q->where(function ($query) use ($s) {
                $query->whereHas('sale', function ($saleQuery) use ($s) {
                    $saleQuery->where('client_name', 'like', '%'.$s.'%')
                        ->orWhere('code', 'like', '%'.$s.'%')
                        ->orWhere('client_cnpj', 'like', '%'.$s.'%');
                });
            });
        }

        if ($request->filled('seller_id')) {
            $q->where('seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, [CommercialCommission::STATUS_A_PAGAR, CommercialCommission::STATUS_PAGA], true)) {
                $q->where('status', $status);
            }
        }

        $summaryQuery = clone $q;

        $summary = [
            'pending_cents' => (int) (clone $summaryQuery)
                ->where('status', CommercialCommission::STATUS_A_PAGAR)
                ->sum('amount_cents'),
            'paid_cents' => (int) (clone $summaryQuery)
                ->where('status', CommercialCommission::STATUS_PAGA)
                ->sum('amount_cents'),
            'pending_count' => (int) (clone $summaryQuery)
                ->where('status', CommercialCommission::STATUS_A_PAGAR)
                ->count(),
        ];

        $commissions = $q
            ->orderByRaw("CASE WHEN status = '".CommercialCommission::STATUS_A_PAGAR."' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Financeiro/Comissoes/Index', [
            'commissions' => $commissions,
            'summary' => $summary,
            'filters' => $request->only(['search', 'seller_id', 'status']),
            'sellers' => User::query()
                ->where('is_commercial', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'statusOptions' => [
                CommercialCommission::STATUS_A_PAGAR => 'A pagar',
                CommercialCommission::STATUS_PAGA => 'Paga',
            ],
        ]);
    }

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

        $wasPaid = $commission->getOriginal('status') === CommercialCommission::STATUS_PAGA;

        $commission->update($update);

        if ($data['status'] === CommercialCommission::STATUS_PAGA && ! $wasPaid) {
            $this->notices->commissionPaid($commission->fresh(['sale', 'seller']), $request->user());
        }

        return redirect()
            ->back()
            ->with('success', 'Comissão atualizada.');
    }
}
