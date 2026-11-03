<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Finance\ChangeFinancePaymentDate;
use App\Actions\Finance\RegisterFinancePayment;
use App\Enums\FinancePayableStatus;
use App\Http\Controllers\Controller;
use App\Models\FinanceBankAccount;
use App\Models\FinancePayable;
use App\Models\FinancePaymentMethod;
use App\Support\Finance\FinanceListReturn;
use App\Support\Finance\FinanceListSort;
use App\Support\Finance\FinancePaymentDate;
use App\Support\Finance\MonthlyRecurrenceDates;
use App\Support\Finance\RelativePagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayableController extends Controller
{
    public function __construct(
        private readonly RegisterFinancePayment $registerPayment,
        private readonly ChangeFinancePaymentDate $changePaymentDate,
    ) {}

    public function index(Request $request): Response
    {
        FinanceListReturn::remember($request, FinanceListReturn::PAYABLES);

        $sort = FinanceListSort::fromRequest($request);

        $query = FinancePayable::query()
            ->with(['paymentMethod:id,name', 'bankAccount:id,name', 'createdBy:id,name']);

        FinanceListSort::applyToQuery($query, $sort);

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

        $payables = $query->paginate(20)
            ->withPath(RelativePagination::path($request))
            ->withQueryString()
            ->through(fn (FinancePayable $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'supplier_name' => $p->supplier_name,
                'amount_cents' => $p->amount_cents,
                'due_date' => $p->due_date?->toDateString(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'paid_at' => self::paidDay($p),
                'payment_method' => $p->paymentMethod?->only(['id', 'name']),
                'payment_method_id' => $p->payment_method_id,
                'bank_account' => $p->bankAccount?->only(['id', 'name']),
                'bank_account_id' => $p->bank_account_id,
                'is_recurring' => (bool) $p->is_recurring,
                'recurring_label' => $p->recurringLabel(),
                'can_mark_paid' => $p->status === FinancePayableStatus::Pending,
            ]);

        return Inertia::render('Admin/Finance/Payables/Index', [
            'payables' => $payables,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
                'sort' => $sort,
            ],
            'sortOptions' => FinanceListSort::payableOptions(),
            'statusOptions' => $this->statusOptions(),
            'today' => FinancePaymentDate::today(),
            ...$this->formOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Finance/Payables/Form', [
            'mode' => 'create',
            'payable' => null,
            'today' => FinancePaymentDate::today(),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, allowRecurrence: true);
        $payment = $this->paymentInput($request);
        $isRecurring = (bool) $data['is_recurring'];
        $months = (int) ($data['recurring_months'] ?? 0);
        unset($data['is_recurring'], $data['recurring_months']);

        $data['created_by'] = $request->user()?->id;

        if (! $isRecurring) {
            $paid = $data['status'] === FinancePayableStatus::Paid;

            DB::transaction(function () use ($request, $data, $payment, $paid): void {
                $payable = FinancePayable::query()->create([
                    ...$data,
                    'status' => $paid ? FinancePayableStatus::Pending : $data['status'],
                    'is_recurring' => false,
                    'recurring_months' => null,
                    'recurring_index' => null,
                    'recurring_group_id' => null,
                ]);

                if ($paid) {
                    $this->registerPayment->handle(
                        $payable,
                        FinancePaymentDate::toPaidAt($payment['paid_at']),
                        $data['bank_account_id'],
                        $data['payment_method_id'] !== null ? (int) $data['payment_method_id'] : null,
                        $data['amount_cents'],
                        $request->user(),
                    );
                }
            });

            return FinanceListReturn::redirect($request, FinanceListReturn::PAYABLES)
                ->with('success', 'Conta a pagar cadastrada.');
        }

        $groupId = (string) Str::uuid();
        $dates = MonthlyRecurrenceDates::dueDates($data['due_date'], $months);

        $paid = $data['status'] === FinancePayableStatus::Paid;

        DB::transaction(function () use ($request, $data, $payment, $paid, $dates, $months, $groupId): void {
            foreach ($dates as $index => $due) {
                $payable = FinancePayable::query()->create([
                    ...$data,
                    'status' => $paid ? FinancePayableStatus::Pending : $data['status'],
                    'due_date' => $due->toDateString(),
                    'is_recurring' => true,
                    'recurring_months' => $months,
                    'recurring_index' => $index + 1,
                    'recurring_group_id' => $groupId,
                ]);

                // Série criada já como paga: todo item pago tem paid_at =
                // min(data informada ou vencimento da ocorrência, hoje).
                if ($paid) {
                    $this->registerPayment->handle(
                        $payable,
                        FinancePaymentDate::toPaidAt(FinancePaymentDate::notAfterToday(
                            $payment['paid_at'] ?? $due->toDateString(),
                        )),
                        $data['bank_account_id'],
                        $data['payment_method_id'] !== null ? (int) $data['payment_method_id'] : null,
                        $data['amount_cents'],
                        $request->user(),
                    );
                }
            }
        });

        return FinanceListReturn::redirect($request, FinanceListReturn::PAYABLES)
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
                'paid_at' => self::paidDay($payable),
                'payment_method_id' => $payable->payment_method_id,
                'bank_account_id' => $payable->bank_account_id,
                'notes' => $payable->notes,
                'is_recurring' => (bool) $payable->is_recurring,
                'recurring_months' => $payable->recurring_months,
                'recurring_index' => $payable->recurring_index,
                'recurring_label' => $payable->recurringLabel(),
            ],
            'today' => FinancePaymentDate::today(),
            ...$this->formOptions($payable->bank_account_id),
        ]);
    }

    public function update(Request $request, FinancePayable $payable): RedirectResponse
    {
        $data = $this->validated(
            $request,
            allowRecurrence: false,
            existingBankAccountId: $payable->bank_account_id,
            alreadyPaid: $payable->isPaid(),
        );
        $payment = $this->paymentInput($request);
        $wasPaid = $payable->isPaid();

        DB::transaction(function () use ($request, $payable, $data, $payment, $wasPaid): void {
            if ($data['status'] !== FinancePayableStatus::Paid) {
                $payable->update([...$data, 'paid_at' => null, 'paid_amount_cents' => null]);

                return;
            }

            if ($wasPaid) {
                // Sem conta no formulário: mantém a atual (inclusive nula, no legado).
                $payable->update([...$data, 'bank_account_id' => $data['bank_account_id'] ?? $payable->bank_account_id]);

                if (filled($payment['paid_at'])) {
                    $this->changePaymentDate->handle(
                        $payable,
                        FinancePaymentDate::toPaidAt($payment['paid_at']),
                        $request->user(),
                        $payment['reason'],
                    );
                }

                return;
            }

            $payable->update([...$data, 'status' => $payable->status]);
            $this->registerPayment->handle(
                $payable,
                FinancePaymentDate::toPaidAt($payment['paid_at']),
                $data['bank_account_id'],
                $data['payment_method_id'] !== null ? (int) $data['payment_method_id'] : null,
                $data['amount_cents'],
                $request->user(),
            );
        });

        return FinanceListReturn::redirect($request, FinanceListReturn::PAYABLES)
            ->with('success', 'Conta a pagar atualizada.');
    }

    public function destroy(Request $request, FinancePayable $payable): RedirectResponse
    {
        $payable->delete();

        return FinanceListReturn::redirect($request, FinanceListReturn::PAYABLES)
            ->with('success', 'Conta a pagar removida.');
    }

    public function markPaid(Request $request, FinancePayable $payable): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => FinancePaymentDate::rules(),
            'payment_method_id' => ['nullable', 'exists:finance_payment_methods,id'],
            'bank_account_id' => ['required', 'exists:finance_bank_accounts,id'],
        ], [
            'bank_account_id.required' => 'Selecione a conta de origem do pagamento.',
            ...FinancePaymentDate::messages(),
        ]);

        if ($payable->status === FinancePayableStatus::Cancelled) {
            return back()->with('error', 'Não é possível pagar uma conta cancelada.');
        }

        if ($payable->isPaid()) {
            return back()->with('error', 'Esta conta já foi paga. Para corrigir a data, use Editar.');
        }

        $this->registerPayment->handle(
            $payable,
            FinancePaymentDate::toPaidAt($data['paid_at'] ?? null),
            (int) $data['bank_account_id'],
            isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            $payable->amount_cents,
            $request->user(),
        );

        return back()->with('success', 'Conta marcada como paga.');
    }

    /**
     * Dia do pagamento no calendário de São Paulo (Y-m-d), como o usuário informou.
     */
    private static function paidDay(FinancePayable $payable): ?string
    {
        return $payable->paid_at?->copy()->setTimezone(FinancePaymentDate::TIMEZONE)->toDateString();
    }

    /**
     * @return array{
     *   statusOptions: list<array{value: string, label: string}>,
     *   paymentMethods: list<array{id: int, name: string}>,
     *   bankAccounts: list<array{id: int, name: string}>
     * }
     */
    private function formOptions(?int $includeBankAccountId = null): array
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
            'bankAccounts' => $this->activeBankAccounts($includeBankAccountId),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function activeBankAccounts(?int $includeId = null): array
    {
        return FinanceBankAccount::query()
            ->where(function ($q) use ($includeId) {
                $q->where('is_active', true);
                if ($includeId !== null) {
                    $q->orWhere('id', $includeId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FinanceBankAccount $a) => $a->only(['id', 'name']))
            ->values()
            ->all();
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
     * Data de pagamento e motivo (já validados em validated()).
     *
     * @return array{paid_at: ?string, reason: ?string}
     */
    private function paymentInput(Request $request): array
    {
        return [
            'paid_at' => filled($request->input('paid_at')) ? (string) $request->input('paid_at') : null,
            'reason' => filled($request->input('payment_date_reason'))
                ? (string) $request->input('payment_date_reason')
                : null,
        ];
    }

    /**
     * Pendente: conta bancária opcional. Ao pagar (status pago): conta de origem obrigatória.
     *
     * @return array{
     *   title: string,
     *   supplier_name: ?string,
     *   amount_cents: int,
     *   due_date: string,
     *   status: FinancePayableStatus,
     *   payment_method_id: ?int,
     *   bank_account_id: ?int,
     *   notes: ?string,
     *   is_recurring?: bool,
     *   recurring_months?: int|null
     * }
     */
    private function validated(
        Request $request,
        bool $allowRecurrence,
        ?int $existingBankAccountId = null,
        bool $alreadyPaid = false,
    ): array {
        if ($allowRecurrence && ! $request->boolean('is_recurring')) {
            $request->merge(['recurring_months' => null]);
        }

        $status = FinancePayableStatus::tryFrom((string) $request->input('status'));
        // Conta exigida só na baixa nova: já paga sem conta (legado) pode ter a data corrigida.
        $requiresBank = $status === FinancePayableStatus::Paid && ! $alreadyPaid;

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'amount_reais' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'status' => ['required', Rule::enum(FinancePayableStatus::class)],
            'payment_method_id' => ['nullable', 'exists:finance_payment_methods,id'],
            'bank_account_id' => [
                $requiresBank ? 'required' : 'nullable',
                'exists:finance_bank_accounts,id',
            ],
            'paid_at' => FinancePaymentDate::rules(),
            'payment_date_reason' => FinancePaymentDate::reasonRules(),
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
            'bank_account_id.required' => 'Selecione a conta de origem do pagamento.',
            'recurring_months.required' => 'Informe a duração em meses da recorrência.',
            'recurring_months.min' => 'A duração deve ser de pelo menos 2 meses.',
            'recurring_months.max' => 'A duração não pode ser maior que 60 meses.',
            ...FinancePaymentDate::messages(),
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
            'bank_account_id' => isset($data['bank_account_id']) ? (int) $data['bank_account_id'] : null,
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
