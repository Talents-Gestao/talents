<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Notices\PublishCommercialNotice;
use App\Http\Controllers\Controller;
use App\Models\CommercialProposal;
use App\Models\CommercialSale;
use App\Services\Commercial\ProposalSaleConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(
        private readonly ProposalSaleConversionService $conversion,
        private readonly PublishCommercialNotice $notices,
    ) {}

    public function index(Request $request): Response
    {
        $q = CommercialSale::query()
            ->with(['seller:id,name', 'proposal:id,code'])
            ->withCount([
                'installments as pending_installments_count' => fn ($query) => $query
                    ->where('status', 'pendente'),
            ])
            ->orderByDesc('sold_at');

        if ($request->filled('search')) {
            $s = (string) $request->string('search');
            $q->where(function ($query) use ($s) {
                $query->where('client_name', 'like', '%'.$s.'%')
                    ->orWhere('code', 'like', '%'.$s.'%')
                    ->orWhere('client_cnpj', 'like', '%'.$s.'%');
            });
        }

        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }

        if ($request->filled('seller_id')) {
            $q->where('seller_id', $request->integer('seller_id'));
        }

        $sales = $q->paginate(15)->withQueryString();

        return Inertia::render('Admin/Finance/Sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['search', 'status', 'seller_id']),
            'sellers' => \App\Models\User::query()
                ->where('is_commercial', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'statusOptions' => [
                CommercialSale::STATUS_ABERTA => 'Aberta',
                CommercialSale::STATUS_PARCIAL => 'Parcial',
                CommercialSale::STATUS_QUITADA => 'Quitada',
                CommercialSale::STATUS_CANCELADA => 'Cancelada',
            ],
        ]);
    }

    public function show(CommercialSale $sale): Response
    {
        $sale->load([
            'seller:id,name,email',
            'proposal:id,code',
            'installments' => fn ($q) => $q->orderBy('number'),
            'commission.seller:id,name',
        ]);

        return Inertia::render('Admin/Finance/Sales/Show', [
            'sale' => $sale,
            'paymentMethods' => [
                'pix' => 'PIX',
                'boleto' => 'Boleto',
                'cartao' => 'Cartão',
            ],
        ]);
    }

    public function store(Request $request, CommercialProposal $proposal): RedirectResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['pix', 'boleto', 'cartao', 'misto'])],
            'installments_count' => ['required', 'integer', 'min:1', 'max:60'],
            'first_due_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $sale = $this->conversion->convert($proposal, $data, $request->user()?->id);

        $this->notices->saleCreated($sale, $request->user());

        return redirect()
            ->route('admin.financeiro.vendas.show', $sale)
            ->with('success', "Venda {$sale->code} criada a partir da proposta {$proposal->code}.");
    }
}
