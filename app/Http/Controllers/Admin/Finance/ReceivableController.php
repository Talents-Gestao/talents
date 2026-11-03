<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Finance\ChangeFinancePaymentDate;
use App\Actions\Finance\RegisterFinancePayment;
use App\Enums\FinanceReceivableStatus;
use App\Http\Controllers\Controller;
use App\Models\FinanceBankAccount;
use App\Models\FinancePaymentMethod;
use App\Models\FinanceReceivable;
use App\Support\Finance\FinanceListReturn;
use App\Support\Finance\FinanceListSort;
use App\Support\Finance\FinancePaymentDate;
use App\Support\Finance\FinanceReceivableLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReceivableController extends Controller
{
    public function __construct(
        private readonly FinanceReceivableLedger $ledger,
        private readonly RegisterFinancePayment $registerPayment,
        private readonly ChangeFinancePaymentDate $changePaymentDate,
    ) {}

    public function index(Request $request): Response
    {
        FinanceListReturn::remember($request, FinanceListReturn::RECEIVABLES);

        $filters = [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'origin' => $request->string('origin')->toString(),
            'sort' => FinanceListSort::fromRequest($request),
            'group' => $request->string('group')->toString() === FinanceReceivableLedger::GROUP_SALE
                ? FinanceReceivableLedger::GROUP_SALE
                : '',
        ];

        return Inertia::render('Admin/Finance/Receivables/Index', [
            'items' => $this->ledger->paginate($filters),
            'filters' => $filters,
            'sortOptions' => FinanceListSort::receivableOptions(),
            'statusOptions' => $this->statusOptions(),
            'originOptions' => [
                ['value' => 'sale', 'label' => 'Vendas (parcelas)'],
                ['value' => 'manual', 'label' => 'Manuais'],
            ],
            ...$this->formOptions(),
            'today' => FinancePaymentDate::today(),
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
            'today' => FinancePaymentDate::today(),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $payment] = $this->validated($request);
        $paid = $data['status'] === FinanceReceivableStatus::Paid;

        DB::transaction(function () use ($request, $data, $payment, $paid): void {
            $receivable = FinanceReceivable::query()->create([
                ...$data,
                'status' => $paid ? FinanceReceivableStatus::Pending : $data['status'],
                'created_by' => $request->user()?->id,
            ]);

            if ($paid) {
                $this->registerPayment->handle(
                    $receivable,
                    FinancePaymentDate::toPaidAt($payment['paid_at']),
                    $data['bank_account_id'],
                    $data['payment_method_id'] !== null ? (int) $data['payment_method_id'] : null,
                    $data['amount_cents'],
                    $request->user(),
                );
            }
        });

        return FinanceListReturn::redirect($request, FinanceListReturn::RECEIVABLES)
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
                'paid_at' => $receivable->paid_at?->copy()->setTimezone(FinancePaymentDate::TIMEZONE)->toDateString(),
                'payment_method_id' => $receivable->payment_method_id,
                'bank_account_id' => $receivable->bank_account_id,
                'notes' => $receivable->notes,
            ],
            'today' => FinancePaymentDate::today(),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, FinanceReceivable $receivable): RedirectResponse
    {
        [$data, $payment] = $this->validated($request, $receivable);
        $wasPaid = $receivable->isPaid();

        DB::transaction(function () use ($request, $receivable, $data, $payment, $wasPaid): void {
            if ($data['status'] !== FinanceReceivableStatus::Paid) {
                $receivable->update([...$data, 'paid_at' => null, 'paid_amount_cents' => null]);

                return;
            }

            if ($wasPaid) {
                // Sem conta no formulário: mantém a atual (inclusive nula, no legado).
                $receivable->update([...$data, 'bank_account_id' => $data['bank_account_id'] ?? $receivable->bank_account_id]);

                if (filled($payment['paid_at'])) {
                    $this->changePaymentDate->handle(
                        $receivable,
                        FinancePaymentDate::toPaidAt($payment['paid_at']),
                        $request->user(),
                        $payment['reason'],
                    );
                }

                return;
            }

            $receivable->update([...$data, 'status' => $receivable->status]);
            $this->registerPayment->handle(
                $receivable,
                FinancePaymentDate::toPaidAt($payment['paid_at']),
                $data['bank_account_id'],
                $data['payment_method_id'] !== null ? (int) $data['payment_method_id'] : null,
                $data['amount_cents'],
                $request->user(),
            );
        });

        return FinanceListReturn::redirect($request, FinanceListReturn::RECEIVABLES)
            ->with('success', 'Conta a receber atualizada.');
    }

    public function destroy(Request $request, FinanceReceivable $receivable): RedirectResponse
    {
        $receivable->delete();

        return FinanceListReturn::redirect($request, FinanceListReturn::RECEIVABLES)
            ->with('success', 'Conta a receber removida.');
    }

    public function markPaid(Request $request, FinanceReceivable $receivable): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => FinancePaymentDate::rules(),
            'payment_method_id' => ['nullable', 'exists:finance_payment_methods,id'],
            'bank_account_id' => ['required', 'exists:finance_bank_accounts,id'],
        ], [
            'bank_account_id.required' => 'Selecione a conta de destino/recebimento.',
            ...FinancePaymentDate::messages(),
        ]);

        if ($receivable->status === FinanceReceivableStatus::Cancelled) {
            return back()->with('error', 'Não é possível receber uma conta cancelada.');
        }

        if ($receivable->isPaid()) {
            return back()->with('error', 'Esta conta já foi recebida. Para corrigir a data, use Editar.');
        }

        $this->registerPayment->handle(
            $receivable,
            FinancePaymentDate::toPaidAt($data['paid_at'] ?? null),
            (int) $data['bank_account_id'],
            isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            $receivable->amount_cents,
            $request->user(),
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
     * @return array{0: array<string, mixed>, 1: array{paid_at: ?string, reason: ?string}}
     */
    private function validated(Request $request, ?FinanceReceivable $existing = null): array
    {
        $status = FinanceReceivableStatus::tryFrom((string) $request->input('status'));
        // Conta exigida só na baixa nova: já recebida sem conta (legado) pode ter a data corrigida.
        $requiresBank = $status === FinanceReceivableStatus::Paid && ! ($existing?->isPaid() ?? false);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'amount_reais' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'status' => ['required', Rule::enum(FinanceReceivableStatus::class)],
            'payment_method_id' => ['nullable', 'exists:finance_payment_methods,id'],
            'bank_account_id' => [
                $requiresBank ? 'required' : 'nullable',
                'exists:finance_bank_accounts,id',
            ],
            'paid_at' => FinancePaymentDate::rules(),
            'payment_date_reason' => FinancePaymentDate::reasonRules(),
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'bank_account_id.required' => 'Selecione a conta de destino/recebimento.',
            ...FinancePaymentDate::messages(),
        ]);

        return [
            [
                'title' => trim($data['title']),
                'payer_name' => filled($data['payer_name'] ?? null) ? trim((string) $data['payer_name']) : null,
                'amount_cents' => (int) round(((float) $data['amount_reais']) * 100),
                'due_date' => $data['due_date'],
                'status' => FinanceReceivableStatus::from($data['status']),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'bank_account_id' => isset($data['bank_account_id']) ? (int) $data['bank_account_id'] : null,
                'notes' => $data['notes'] ?? null,
            ],
            [
                'paid_at' => $data['paid_at'] ?? null,
                'reason' => $data['payment_date_reason'] ?? null,
            ],
        ];
    }
}
