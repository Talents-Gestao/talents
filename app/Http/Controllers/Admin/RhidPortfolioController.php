<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Rhid\RhidAdminPortfolioMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RhidPortfolioController extends Controller
{
    public function companyMetrics(Request $request, Company $company, RhidAdminPortfolioMetricsService $metrics): JsonResponse
    {
        $refresh = $request->boolean('refresh');

        return response()->json(
            $metrics->metricsForCompany($company, $request->user(), $refresh),
        );
    }
}
