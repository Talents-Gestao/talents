<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Enums\FinancePayableStatus;
use App\Http\Controllers\Controller;
use App\Models\FinancePayable;
use App\Models\FinancePaymentMethod;
use App\Support\Finance\MonthlyRecurrenceDates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            'is_recurring' => (bool) $p->is_recurring,
            'recurring_label' => $p->recurringLabel(),
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
        $data = $this->validated($request, allowRecurrence: true);
        $isRecurring = (bool) $data['is_recurring'];
        $months = (int) ($data['recurring_months'] ?? 0);
        unset($data['is_recurring'], $data['recurring_months']);

        $data['created_by'] = $request->user()?->id;

        if (! $isRecurring) {
            FinancePayable::query()->create([
                ...$data,
                'is_recurring' => false,
                'recurring_months' => null,
                'recurring_index' => null,
                'recurring_group_id' => null,
            ]);

            return redirect()
                ->route('admin.financeiro.contas-a-pagar.index')
                ->with('success', 'Conta a pagar cadastrada.');
        }

        $groupId = (string) Str::uuid();
        $dates = MonthlyRecurrenceDates::dueDates($data['due_date'], $months);

        DB::transaction(function () use ($data, $dates, $months, $groupId): void {
            foreach ($dates as $index => $due) {
                FinancePayable::query()->create([
                    ...$data,
                    'due_date' => $due->toDateString(),
                    'is_recurring' => true,
                    'recurring_months' => $months,
                    'recurring_index' => $index + 1,
                    'recurring_group_id' => $groupId,
                ]);
            }
        });

        return redirect()
            ->route('admin.financeiro.contas-a-pagar.index')
            ->with('success', "Série recorrente cadastrada: {$months} lançamentos mensais.");
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
                'is_recurring' => (bool) $payable->is_recurring,
                'recurring_months' => $payable->recurring_months,
                'recurring_index' => $payable->recurring_index,
                'recurring_label' => $payable->recurringLabel(),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, FinancePayable $payable): RedirectResponse
    {
        $data = $this->validated($request, allowRecurrence: false);

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
     * Recorrência só na criação: editar um item da série não regenera os demais.
     *
     * @return array{
     *   title: string,
     *   supplier_name: ?string,
     *   amount_cents: int,
     *   due_date: string,
     *   status: FinancePayableStatus,
     *   payment_method_id: ?int,
     *   notes: ?string,
     *   is_recurring?: bool,
     *   recurring_months?: int|null
     * }
     */
    private function validated(Request $request, bool $allowRecurrence): array
    {
        if ($allowRecurrence && ! $request->boolean('is_recurring')) {
            $request->merge(['recurring_months' => null]);
        }

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'amount_reais' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'status' => ['required', Rule::enum(FinancePayableStatus::class)],
            'payment_method_id' => ['nullable', 'exists:finance_payment_methods,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($allowRecurrence) {
            $rules['is_recurring'] = ['sometimes', 'boolean'];
            $rules['recurring_months'] = [
                Rule::requiredIf(fn () => $request->boolean('is_recurring')),
                'nullable',
                'integer',
                'min:2',
                'max:60',
            ];
        }

        $data = $request->validate($rules, [
            'recurring_months.required' => 'Informe a duração em meses da recorrência.',
            'recurring_months.min' => 'A duração deve ser de pelo menos 2 meses.',
            'recurring_months.max' => 'A duração não pode ser maior que 60 meses.',
        ]);

        $payload = [
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

        if ($allowRecurrence) {
            $payload['is_recurring'] = $request->boolean('is_recurring');
            $payload['recurring_months'] = $payload['is_recurring']
                ? (int) $data['recurring_months']
                : null;
        }

        return $payload;
    }
}
