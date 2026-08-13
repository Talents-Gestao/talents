<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Enums\FinanceReceivableStatus;
use App\Http\Controllers\Controller;
use App\Models\FinanceBankAccount;
use App\Models\FinancePaymentMethod;
use App\Models\FinanceReceivable;
use App\Support\Finance\FinanceReceivableLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableController extends Controller
{
    public function __construct(
        private readonly FinanceReceivableLedger $ledger,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'origin' => $request->string('origin')->toString(),
        ];

        return Inertia::render('Admin/Finance/Receivables/Index', [
            'items' => $this->ledger->paginate($filters),
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'originOptions' => [
                ['value' => 'sale', 'label' => 'Vendas (parcelas)'],
                ['value' => 'manual', 'label' => 'Manuais'],
            ],
            ...$this->formOptions(),
            'installmentMethodOptions' => [
                ['value' => 'pix', 'label' => 'PIX'],
                ['value' => 'boleto', 'label' => 'Boleto'],
                ['value' => 'cartao', 'label' => 'Cartão'],
            ],
            'installmentStatusOptions' => [
                ['value' => 'pendente', 'label' => 'Pendente'],
                ['value' => 'pago', 'label' => 'Pago'],
                ['value' => 'cancelado', 'label' => 'Cancelado'],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Finance/Receivables/Form', [
            'mode' => 'create',
            'receivable' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        FinanceReceivable::query()->create([
            ...$data,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.financeiro.contas-a-receber.index')
            ->with('success', 'Conta a receber cadastrada.');
    }

    public function edit(FinanceReceivable $receivable): Response
    {
        return Inertia::render('Admin/Finance/Receivables/Form', [
            'mode' => 'edit',
            'receivable' => [
                'id' => $receivable->id,
                'title' => $receivable->title,
                'payer_name' => $receivable->payer_name,
                'amount_reais' => number_format($receivable->amount_cents / 100, 2, '.', ''),
                'due_date' => $receivable->due_date?->toDateString(),
                'status' => $receivable->status->value,
                'payment_method_id' => $receivable->payment_method_id,
                'bank_account_id' => $receivable->bank_account_id,
                'notes' => $receivable->notes,
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, FinanceReceivable $receivable): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['status'] === FinanceReceivableStatus::Paid && $receivable->paid_at === null) {
            $data['paid_at'] = now();
            $data['paid_amount_cents'] = $data['amount_cents'];
        }

        if ($data['status'] !== FinanceReceivableStatus::Paid) {
            $data['paid_at'] = null;
            $data['paid_amount_cents'] = null;
        }

        $receivable->update($data);

        return redirect()
            ->route('admin.financeiro.contas-a-receber.index')
            ->with('success', 'Conta a receber atualizada.');
    }

    public function destroy(FinanceReceivable $receivable): RedirectResponse
    {
        $receivable->delete();

        return redirect()
            ->route('admin.financeiro.contas-a-receber.index')
            ->with('success', 'Conta a receber removida.');
    }

    public function markPaid(Request $request, FinanceReceivable $receivable): RedirectResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['nullable', 'exists:finance_payment_methods,id'],
            'bank_account_id' => ['nullable', 'exists:finance_bank_accounts,id'],
        ]);

        if ($receivable->status === FinanceReceivableStatus::Cancelled) {
            return back()->with('error', 'Não é possível receber uma conta cancelada.');
        }

        $receivable->markPaid(
            paidAmountCents: $receivable->amount_cents,
            paymentMethodId: isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            bankAccountId: isset($data['bank_account_id']) ? (int) $data['bank_account_id'] : null,
        );

        return back()->with('success', 'Conta marcada como recebida.');
    }

    /**
     * @return array{
     *   statusOptions: list<array{value: string, label: string}>,
     *   paymentMethods: list<array{id: int, name: string}>,
     *   bankAccounts: list<array{id: int, name: string}>
     * }
     */
    private function formOptions(): array
    {
        return [
            'statusOptions' => $this->statusOptions(),
            'paymentMethods' => FinancePaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (FinancePaymentMethod $m) => $m->only(['id', 'name']))
                ->values()
                ->all(),
            'bankAccounts' => FinanceBankAccount::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (FinanceBankAccount $a) => $a->only(['id', 'name']))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (FinanceReceivableStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            FinanceReceivableStatus::all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'amount_reais' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'status' => ['required', Rule::enum(FinanceReceivableStatus::class)],
            'payment_method_id' => ['nullable', 'exists:finance_payment_methods,id'],
            'bank_account_id' => ['nullable', 'exists:finance_bank_accounts,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return [
            'title' => trim($data['title']),
            'payer_name' => filled($data['payer_name'] ?? null) ? trim((string) $data['payer_name']) : null,
            'amount_cents' => (int) round(((float) $data['amount_reais']) * 100),
            'due_date' => $data['due_date'],
            'status' => FinanceReceivableStatus::from($data['status']),
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
