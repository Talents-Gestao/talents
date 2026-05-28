<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Services\CommercialPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'svc_pesquisas' => ['boolean'],
            'svc_profiler' => ['boolean'],
            'svc_devolutiva' => ['nullable', Rule::in(['individual', 'grupo'])],
            'svc_nr1' => ['boolean'],
            'svc_nr1_implantacao_modo' => ['nullable', Rule::in(['online', 'presencial'])],
            'svc_contratacao' => ['boolean'],
            'svc_contratacao_salario_cents' => ['nullable', 'integer', 'min:0'],
            'svc_direcionamento' => ['boolean'],
            'svc_palestras' => ['boolean'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catalog_products' => ['nullable', 'array'],
            'catalog_products.*.product_id' => ['required', 'integer'],
            'catalog_products.*.enabled' => ['boolean'],
            'catalog_products.*.modality' => ['nullable', 'string', 'max:64'],
            'catalog_products.*.salary_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $this->pricing->calculate($data);

        return response()->json($result);
    }
}
