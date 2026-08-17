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

    public function create(): Response
    {
        return Inertia::render('Admin/Finance/Sales/Form', [
            'sellers' => \App\Models\User::query()
                ->where('is_commercial', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'paymentMethodOptions' => [
                ['value' => 'pix', 'label' => 'PIX'],
                ['value' => 'boleto', 'label' => 'Boleto'],
                ['value' => 'cartao', 'label' => 'Cartão'],
                ['value' => 'misto', 'label' => 'Misto'],
            ],
        ]);
    }

    public function storeManual(Request $request): RedirectResponse
    {
        $isMisto = $request->input('payment_method') === 'misto';

        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_cnpj' => ['nullable', 'string', 'max:18'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:32'],
            'seller_id' => ['nullable', 'integer', 'exists:users,id'],
            'total_reais' => ['required', 'numeric', 'min:0.01'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_method' => ['required', Rule::in(['pix', 'boleto', 'cartao', 'misto'])],
            'installments_count' => [
                Rule::excludeIf($isMisto),
                Rule::requiredIf(! $isMisto),
                'integer',
                'min:1',
                'max:60',
            ],
            'first_due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mix_parts' => [
                Rule::excludeIf(! $isMisto),
                Rule::requiredIf($isMisto),
                'array',
                'min:2',
                'max:60',
            ],
            'mix_parts.*.method' => [
                Rule::requiredIf($isMisto),
                Rule::in(['pix', 'boleto', 'cartao']),
            ],
            'mix_parts.*.percent' => [
                Rule::requiredIf($isMisto),
                'numeric',
                'gt:0',
                'lte:100',
            ],
        ], [
            'client_name.required' => 'Informe o nome do cliente.',
            'total_reais.required' => 'Informe o valor total.',
            'payment_method.required' => 'Selecione a forma de pagamento.',
            'first_due_date.required' => 'Informe a data do primeiro vencimento.',
            'installments_count.required' => 'Informe o número de parcelas.',
        ]);

        $payload = [
            ...$data,
            'total_cents' => (int) round(((float) $data['total_reais']) * 100),
            'commission_percent' => (float) ($data['commission_percent'] ?? 0),
            'seller_id' => filled($data['seller_id'] ?? null) ? (int) $data['seller_id'] : null,
        ];
        unset($payload['total_reais']);

        $sale = $this->conversion->createManual($payload, $request->user()?->id);

        $actor = $request->user();
        $saleId = (int) $sale->id;
        $saleCode = (string) $sale->code;

        dispatch(function () use ($saleId, $actor): void {
            $sale = CommercialSale::query()->find($saleId);
            if (! $sale) {
                return;
            }
            app(PublishCommercialNotice::class)->saleCreated($sale, $actor);
        })->afterResponse();

        return redirect()
            ->route('admin.financeiro.vendas.show', $sale)
            ->with('success', "Venda {$saleCode} criada manualmente.");
    }

    public function store(Request $request, CommercialProposal $proposal): RedirectResponse
    {
        $isRecurring = $proposal->isRecurringService();
        $isMisto = ! $isRecurring && $request->input('payment_method') === 'misto';

        $data = $request->validate([
            'payment_method' => [
                'required',
                Rule::in($isRecurring ? ['pix', 'boleto', 'cartao'] : ['pix', 'boleto', 'cartao', 'misto']),
            ],
            'installments_count' => [
                Rule::excludeIf($isRecurring || $isMisto),
                Rule::requiredIf(! $isRecurring && ! $isMisto),
                'integer',
                'min:1',
                'max:60',
            ],
            // Permite vencimento passado (venda/parcelas retroativas).
            'first_due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mix_parts' => [
                Rule::excludeIf($isRecurring || ! $isMisto),
                Rule::requiredIf($isMisto),
                'array',
                'min:2',
                'max:60',
            ],
            'mix_parts.*.method' => [
                Rule::requiredIf($isMisto),
                Rule::in(['pix', 'boleto', 'cartao']),
            ],
            'mix_parts.*.percent' => [
                Rule::requiredIf($isMisto),
                'numeric',
                'gt:0',
                'lte:100',
            ],
        ], [
            'payment_method.required' => 'Selecione a forma de pagamento.',
            'payment_method.in' => $isRecurring
                ? 'Propostas recorrentes aceitam apenas PIX, boleto ou cartão.'
                : 'Forma de pagamento inválida.',
            'installments_count.required' => 'Informe o número de parcelas.',
            'installments_count.integer' => 'O número de parcelas deve ser um inteiro.',
            'installments_count.min' => 'O número de parcelas deve ser pelo menos 1.',
            'installments_count.max' => 'O número de parcelas não pode ser maior que 60.',
            'first_due_date.required' => 'Informe a data do primeiro vencimento.',
            'first_due_date.date' => 'Informe uma data válida para o primeiro vencimento.',
            'notes.max' => 'As observações não podem ter mais de 2000 caracteres.',
            'mix_parts.required' => 'Informe a composição do pagamento misto.',
            'mix_parts.min' => 'Informe pelo menos 2 partes na composição.',
            'mix_parts.max' => 'A composição não pode ter mais de 60 partes.',
            'mix_parts.*.method.required' => 'Selecione a forma de cada parte.',
            'mix_parts.*.method.in' => 'Cada parte deve ser PIX, boleto ou cartão.',
            'mix_parts.*.percent.required' => 'Informe o percentual de cada parte.',
            'mix_parts.*.percent.numeric' => 'O percentual de cada parte deve ser numérico.',
            'mix_parts.*.percent.gt' => 'Cada percentual deve ser maior que zero.',
            'mix_parts.*.percent.lte' => 'Cada percentual não pode ser maior que 100.',
        ]);

        $sale = $this->conversion->convert($proposal, $data, $request->user()?->id);

        $actor = $request->user();
        $saleId = (int) $sale->id;
        $saleCode = (string) $sale->code;

        // Não bloquear o redirect Inertia com a publicação do aviso.
        dispatch(function () use ($saleId, $actor): void {
            $sale = CommercialSale::query()->find($saleId);
            if (! $sale) {
                return;
            }
            app(PublishCommercialNotice::class)->saleCreated($sale, $actor);
        })->afterResponse();

        return redirect()
            ->route('admin.comercial.propostas.index')
            ->with('success', "Venda {$saleCode} criada a partir da proposta {$proposal->code}.")
            ->with('sale_id', $saleId)
            ->with('sale_code', $saleCode);
    }
}
