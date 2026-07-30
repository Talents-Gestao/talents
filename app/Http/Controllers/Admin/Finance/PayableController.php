<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Enums\FinancePayableStatus;
use App\Http\Controllers\Controller;
use App\Models\FinancePayable;
use App\Models\FinancePaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayableController extends Controller
{
    public function index(Request $request): Response
    {
        $query = FinancePayable::query()
            ->with(['paymentMethod:id,name', 'createdBy:id,name'])
            ->orderByDesc('due_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('supplier_name', 'like', $term);
            });
        }

        $payables = $query->paginate(20)->withQueryString()->through(fn (FinancePayable $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'supplier_name' => $p->supplier_name,
            'amount_cents' => $p->amount_cents,
            'due_date' => $p->due_date?->toDateString(),
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'paid_at' => $p->paid_at?->toIso8601String(),
            'payment_method' => $p->paymentMethod?->only(['id', 'name']),
        ]);

        return Inertia::render('Admin/Finance/Payables/Index', [
            'payables' => $payables,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Finance/Payables/Form', [
            'mode' => 'create',
            'payable' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        FinancePayable::query()->create([
            ...$data,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.financeiro.contas-a-pagar.index')
            ->with('success', 'Conta a pagar cadastrada.');
    }

    public function edit(FinancePayable $payable): Response
    {
        return Inertia::render('Admin/Finance/Payables/Form', [
            'mode' => 'edit',
            'payable' => [
                'id' => $payable->id,
                'title' => $payable->title,
                'supplier_name' => $payable->supplier_name,
                'amount_reais' => number_format($payable->amount_cents / 100, 2, '.', ''),
                'due_date' => $payable->due_date?->toDateString(),
                'status' => $payable->status->value,
                'payment_method_id' => $payable->payment_method_id,
                'notes' => $payable->notes,
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, FinancePayable $payable): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['status'] === FinancePayableStatus::Paid && $payable->paid_at === null) {
            $data['paid_at'] = now();
            $data['paid_amount_cents'] = $data['amount_cents'];
        }

        if ($data['status'] !== FinancePayableStatus::Paid) {
            $data['paid_at'] = null;
            $data['paid_amount_cents'] = null;
        }

        $payable->update($data);

        return redirect()
            ->route('admin.financeiro.contas-a-pagar.index')
            ->with('success', 'Conta a pagar atualizada.');
    }

    public function destroy(FinancePayable $payable): RedirectResponse
    {
        $payable->delete();

        return redirect()
            ->route('admin.financeiro.contas-a-pagar.index')
            ->with('success', 'Conta a pagar removida.');
    }

    public function markPaid(Request $request, FinancePayable $payable): RedirectResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['nullable', 'exists:finance_payment_methods,id'],
        ]);

        if ($payable->status === FinancePayableStatus::Cancelled) {
            return back()->with('error', 'Não é possível pagar uma conta cancelada.');
        }

        $payable->markPaid(
            paidAmountCents: $payable->amount_cents,
            paymentMethodId: isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
        );

        return back()->with('success', 'Conta marcada como paga.');
    }

    /**
     * @return array{statusOptions: list<array{value: string, label: string}>, paymentMethods: list<array{id: int, name: string}>}
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
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (FinancePayableStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            FinancePayableStatus::all(),
        );
    }

    /**
     * @return array{
     *   title: string,
     *   supplier_name: ?string,
     *   amount_cents: int,
     *   due_date: string,
     *   status: FinancePayableStatus,
     *   payment_method_id: ?int,
     *   notes: ?string
     * }
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'amount_reais' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'status' => ['required', Rule::enum(FinancePayableStatus::class)],
            'payment_method_id' => ['nullable', 'exists:finance_payment_methods,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return [
            'title' => trim($data['title']),
            'supplier_name' => isset($data['supplier_name']) && trim((string) $data['supplier_name']) !== ''
                ? trim((string) $data['supplier_name'])
                : null,
            'amount_cents' => (int) round(((float) $data['amount_reais']) * 100),
            'due_date' => $data['due_date'],
            'status' => FinancePayableStatus::from($data['status']),
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
