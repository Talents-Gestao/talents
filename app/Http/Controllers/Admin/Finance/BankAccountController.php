<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Enums\FinanceBankAccountType;
use App\Http\Controllers\Controller;
use App\Models\FinanceBankAccount;
use App\Support\Finance\FinanceBankAccountBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly FinanceBankAccountBalance $balances,
    ) {}

    public function index(Request $request): Response
    {
        $query = FinanceBankAccount::query()
            ->with('createdBy:id,name')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('bank_name', 'like', $term)
                    ->orWhere('account_number', 'like', $term);
            });
        }

        if ($request->boolean('only_active')) {
            $query->where('is_active', true);
        }

        $accounts = $query->paginate(20)->withQueryString()->through(fn (FinanceBankAccount $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'bank_name' => $a->bank_name,
            'agency' => $a->agency,
            'account_number' => $a->account_number,
            'type' => $a->type->value,
            'type_label' => $a->type->label(),
            'initial_balance_cents' => $a->initial_balance_cents,
            'current_balance_cents' => $this->balances->currentBalanceCents($a),
            'initial_balance_at' => $a->initial_balance_at?->toDateString(),
            'is_active' => $a->is_active,
            'sort_order' => $a->sort_order,
            'notes' => $a->notes,
        ]);

        return Inertia::render('Admin/Finance/BankAccounts/Index', [
            'accounts' => $accounts,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'only_active' => $request->boolean('only_active'),
            ],
            'summary' => [
                'active_count' => FinanceBankAccount::query()->where('is_active', true)->count(),
                'active_balance_cents' => $this->balances->activeAccountsTotalCents(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Finance/BankAccounts/Form', [
            'mode' => 'create',
            'account' => null,
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        FinanceBankAccount::query()->create([
            ...$data,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.financeiro.contas-bancarias.index')
            ->with('success', 'Conta bancária cadastrada.');
    }

    public function edit(FinanceBankAccount $bank_account): Response
    {
        return Inertia::render('Admin/Finance/BankAccounts/Form', [
            'mode' => 'edit',
            'account' => [
                'id' => $bank_account->id,
                'name' => $bank_account->name,
                'bank_name' => $bank_account->bank_name,
                'agency' => $bank_account->agency,
                'account_number' => $bank_account->account_number,
                'type' => $bank_account->type->value,
                'initial_balance_reais' => number_format($bank_account->initial_balance_cents / 100, 2, '.', ''),
                'initial_balance_at' => $bank_account->initial_balance_at?->toDateString(),
                'is_active' => $bank_account->is_active,
                'sort_order' => $bank_account->sort_order,
                'notes' => $bank_account->notes,
            ],
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(Request $request, FinanceBankAccount $bank_account): RedirectResponse
    {
        $bank_account->update($this->validated($request));

        return redirect()
            ->route('admin.financeiro.contas-bancarias.index')
            ->with('success', 'Conta bancária atualizada.');
    }

    public function destroy(FinanceBankAccount $bank_account): RedirectResponse
    {
        if ($bank_account->receivables()->exists()
            || $bank_account->payables()->exists()
            || $bank_account->saleInstallments()->exists()) {
            return back()->with(
                'error',
                'Não é possível excluir: existem lançamentos vinculados. Desative a conta em vez de excluir.',
            );
        }

        $bank_account->delete();

        return redirect()
            ->route('admin.financeiro.contas-bancarias.index')
            ->with('success', 'Conta bancária removida.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (FinanceBankAccountType $t) => ['value' => $t->value, 'label' => $t->label()],
            FinanceBankAccountType::all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'agency' => ['nullable', 'string', 'max:32'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'type' => ['required', Rule::enum(FinanceBankAccountType::class)],
            'initial_balance_reais' => ['nullable', 'numeric'],
            'initial_balance_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $balanceReais = isset($data['initial_balance_reais']) && $data['initial_balance_reais'] !== ''
            ? (float) $data['initial_balance_reais']
            : 0.0;

        return [
            'name' => trim($data['name']),
            'bank_name' => filled($data['bank_name'] ?? null) ? trim((string) $data['bank_name']) : null,
            'agency' => filled($data['agency'] ?? null) ? trim((string) $data['agency']) : null,
            'account_number' => filled($data['account_number'] ?? null) ? trim((string) $data['account_number']) : null,
            'type' => FinanceBankAccountType::from($data['type']),
            'initial_balance_cents' => (int) round($balanceReais * 100),
            'initial_balance_at' => $data['initial_balance_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ];
    }
}
