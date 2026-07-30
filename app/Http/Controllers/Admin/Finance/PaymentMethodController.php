<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinancePaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        $methods = FinancePaymentMethod::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (FinancePaymentMethod $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'slug' => $m->slug,
                'is_active' => $m->is_active,
                'sort_order' => $m->sort_order,
            ]);

        return Inertia::render('Admin/Finance/PaymentMethods/Index', [
            'methods' => $methods,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Finance/PaymentMethods/Form', [
            'mode' => 'create',
            'method' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        FinancePaymentMethod::query()->create($data);

        return redirect()
            ->route('admin.financeiro.formas-pagamento.index')
            ->with('success', 'Forma de pagamento cadastrada.');
    }

    public function edit(FinancePaymentMethod $paymentMethod): Response
    {
        return Inertia::render('Admin/Finance/PaymentMethods/Form', [
            'mode' => 'edit',
            'method' => [
                'id' => $paymentMethod->id,
                'name' => $paymentMethod->name,
                'slug' => $paymentMethod->slug,
                'is_active' => $paymentMethod->is_active,
                'sort_order' => $paymentMethod->sort_order,
            ],
        ]);
    }

    public function update(Request $request, FinancePaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $this->validated($request, $paymentMethod->id);

        $paymentMethod->update($data);

        return redirect()
            ->route('admin.financeiro.formas-pagamento.index')
            ->with('success', 'Forma de pagamento atualizada.');
    }

    public function destroy(FinancePaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->payables()->exists()) {
            return back()->with('error', 'Não é possível excluir: há contas a pagar vinculadas. Desative a forma em vez de excluir.');
        }

        $paymentMethod->delete();

        return redirect()
            ->route('admin.financeiro.formas-pagamento.index')
            ->with('success', 'Forma de pagamento removida.');
    }

    /**
     * @return array{name: string, slug: string, is_active: bool, sort_order: int}
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('finance_payment_methods', 'slug')->ignore($ignoreId),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $name = trim($data['name']);
        $slug = isset($data['slug']) && trim((string) $data['slug']) !== ''
            ? Str::slug((string) $data['slug'])
            : Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
