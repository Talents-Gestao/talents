<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Services\CommercialPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreviewController extends Controller
{
    public function __construct(
        private readonly CommercialPricingService $pricing,
    ) {}

    /**
     * Recalcula um breakdown sem persistir nada. Útil quando o frontend
     * quiser revalidar contra o backend (single source of truth).
     */
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catalog_products' => ['nullable', 'array'],
            'catalog_products.*.product_id' => ['required', 'integer'],
            'catalog_products.*.enabled' => ['boolean'],
            'catalog_products.*.modality' => ['nullable', 'string', 'max:64'],
            'catalog_products.*.salary_cents' => ['nullable', 'integer', 'min:0'],
            'catalog_products.*.rate_mode' => ['nullable', 'string', 'max:16'],
            'catalog_products.*.units' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'catalog_products.*.custom_cents' => ['nullable', 'integer', 'min:0'],
            'catalog_products.*.adjustment' => ['nullable', 'string', 'max:16'],
            'catalog_products.*.discount_type' => ['nullable', 'string', 'max:16'],
            'catalog_products.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catalog_products.*.discount_value_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $this->pricing->calculate($data);

        return response()->json($result);
    }
}
