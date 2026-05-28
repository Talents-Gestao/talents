<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Enums\CommercialProductPricingType;
use App\Http\Controllers\Controller;
use App\Models\CommercialProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);
        $data['slug'] = CommercialProduct::uniqueSlug($data['name']);

        CommercialProduct::query()->create($data);

        return $this->redirectBack('Produto cadastrado.');
    }

    public function update(Request $request, CommercialProduct $product): RedirectResponse
    {
        $data = $this->validateProduct($request, $product);
        if ($product->name !== $data['name']) {
            $data['slug'] = CommercialProduct::uniqueSlug($data['name'], $product->id);
        }

        $product->update($data);

        return $this->redirectBack('Produto atualizado.');
    }

    public function destroy(CommercialProduct $product): RedirectResponse
    {
        if ($product->proposalLines()->exists()) {
            return $this->redirectBack('Este produto já foi usado em propostas e não pode ser excluído. Desative-o.', error: true);
        }

        $product->delete();

        return $this->redirectBack('Produto removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request, ?CommercialProduct $product = null): array
    {
        $types = array_column(CommercialProductPricingType::cases(), 'value');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pricing_type' => ['required', Rule::in($types)],
            'pricing_config' => ['required', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);

        $data['pricing_config'] = $this->normalizePricingConfig(
            CommercialProductPricingType::from($data['pricing_type']),
            $data['pricing_config'],
        );
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizePricingConfig(CommercialProductPricingType $type, array $config): array
    {
        return match ($type) {
            CommercialProductPricingType::Fixed => [
                'amount_cents' => max(0, (int) ($config['amount_cents'] ?? 0)),
            ],
            CommercialProductPricingType::PerEmployee => [
                'cents_per_employee' => max(0, (int) ($config['cents_per_employee'] ?? 0)),
            ],
            CommercialProductPricingType::TieredPerEmployee => [
                'tier1_max' => max(1, (int) ($config['tier1_max'] ?? 5)),
                'tier1_cents' => max(0, (int) ($config['tier1_cents'] ?? 0)),
                'tier2_max' => max(1, (int) ($config['tier2_max'] ?? 10)),
                'tier2_cents' => max(0, (int) ($config['tier2_cents'] ?? 0)),
                'tier3_max' => max(1, (int) ($config['tier3_max'] ?? 20)),
                'tier3_cents' => max(0, (int) ($config['tier3_cents'] ?? 0)),
                'tier4_cents' => max(0, (int) ($config['tier4_cents'] ?? 0)),
            ],
            CommercialProductPricingType::FixedModality => [
                'modalities' => collect($config['modalities'] ?? [])
                    ->map(fn ($m) => [
                        'key' => (string) ($m['key'] ?? ''),
                        'label' => (string) ($m['label'] ?? ''),
                        'cents' => max(0, (int) ($m['cents'] ?? 0)),
                    ])
                    ->filter(fn ($m) => $m['key'] !== '' && $m['label'] !== '')
                    ->values()
                    ->all(),
            ],
            CommercialProductPricingType::SalaryTimesEmployees => [],
            CommercialProductPricingType::ThresholdMultiplier => [
                'base_cents' => max(0, (int) ($config['base_cents'] ?? 0)),
                'threshold_employees' => max(0, (int) ($config['threshold_employees'] ?? 30)),
                'multiplier' => max(1, (int) ($config['multiplier'] ?? 2)),
            ],
        };
    }

    private function redirectBack(string $message, bool $error = false): RedirectResponse
    {
        return redirect()
            ->to(route('admin.comercial.settings.edit').'?tab=produtos')
            ->with($error ? 'error' : 'success', $message);
    }
}
