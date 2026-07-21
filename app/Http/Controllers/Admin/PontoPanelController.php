<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondsWithRhidJson;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Rhid\EspelhoScheduleAdherenceService;
use App\Services\Rhid\RhidAdminPortfolioMetricsService;
use App\Services\Rhid\RhidComplianceService;
use App\Services\Rhid\RhidMonitoringService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Gestão de ponto (Admin): hub operacional por empresa sobre a API RHID / Control iD.
 */
class PontoPanelController extends Controller
{
    use RespondsWithRhidJson;

    public function index(): Response
    {
        $companies = Company::query()
            ->where('is_active', true)
            ->whereNotNull('rhid_email')
            ->whereNotNull('rhid_password')
            ->orderBy('name')
            ->get(['id', 'name', 'segment', 'rhid_base_url', 'rhid_email', 'rhid_domain']);

        return Inertia::render('Admin/Ponto/Index', [
            'companies' => $companies,
            'segments' => $companies->pluck('segment')->filter()->unique()->sort()->values(),
        ]);
    }

    public function companyMetrics(Request $request, Company $company, RhidAdminPortfolioMetricsService $metrics): JsonResponse
    {
        $this->assertCompanyRhidReady($company);

        $refresh = $request->boolean('refresh');

        return response()->json($metrics->metricsForCompany($company, $request->user(), $refresh));
    }

    public function lastPunches(Request $request, Company $company, RhidMonitoringService $monitoring): JsonResponse|SymfonyResponse
    {
        $this->assertCompanyRhidReady($company);

        return $this->rhidJsonOrError(
            fn () => $monitoring->ultimasMarcacoes($company, $request->user()),
        );
    }

    public function people(Request $request, Company $company, RhidComplianceService $compliance): JsonResponse|SymfonyResponse
    {
        $this->assertCompanyRhidReady($company);

        $query = $request->validate([
            'page' => ['nullable', 'integer', 'min:0'],
            'maxSize' => ['nullable', 'integer', 'min:1', 'max:500'],
            'status' => ['nullable', 'integer', 'in:1,2'],
        ]);

        return $this->rhidJsonOrError(
            fn () => $compliance->listPersons($company, $request->user(), $query),
        );
    }

    public function scheduleAdherence(
        Request $request,
        Company $company,
        EspelhoScheduleAdherenceService $adherence,
    ): JsonResponse {
        $this->assertCompanyRhidReady($company);

        $v = $request->validate([
            'ini' => ['required', 'date'],
            'fim' => ['required', 'date', 'after_or_equal:ini'],
            'id_person' => ['nullable', 'integer', 'min:1'],
        ]);

        $ini = Carbon::parse($v['ini'])->startOfDay();
        $fim = Carbon::parse($v['fim'])->startOfDay();
        $span = $ini->diffInDays($fim) + 1;
        if ($span > EspelhoScheduleAdherenceService::MAX_RANGE_DAYS) {
            return response()->json([
                'message' => 'Período máximo de '.EspelhoScheduleAdherenceService::MAX_RANGE_DAYS.' dias.',
            ], 422);
        }

        $idPerson = isset($v['id_person']) ? (int) $v['id_person'] : null;

        return response()->json($adherence->aggregateForCompany($company, $ini, $fim, $idPerson));
    }

    public function justificationTypes(Request $request, Company $company, RhidComplianceService $compliance): JsonResponse|SymfonyResponse
    {
        $this->assertCompanyRhidReady($company);

        return $this->rhidJsonOrError(
            fn () => $compliance->listJustificationTypes($company, $request->user(), $request->query()),
        );
    }

    public function listJustifications(Request $request, Company $company, RhidComplianceService $compliance): JsonResponse|SymfonyResponse
    {
        $this->assertCompanyRhidReady($company);

        $merged = $request->all();
        foreach (['ini', 'fim'] as $key) {
            if (array_key_exists($key, $merged) && $merged[$key] !== null) {
                $merged[$key] = preg_replace('/\D/', '', (string) $merged[$key]);
            }
        }
        $request->merge($merged);

        $payload = $request->validate([
            'ini' => ['required', 'string', 'size:8', 'regex:/^\d{8}$/'],
            'fim' => ['required', 'string', 'size:8', 'regex:/^\d{8}$/'],
            'page' => ['nullable', 'integer', 'min:0'],
            'maxSize' => ['nullable', 'integer', 'min:1', 'max:500'],
            'people' => ['nullable', 'array'],
            'people.*' => ['integer'],
            'justificationTypes' => ['nullable', 'array'],
            'justificationTypes.*' => ['integer'],
        ]);

        return $this->rhidJsonOrError(
            fn () => $compliance->listJustifications($company, $request->user(), $payload),
        );
    }

    public function storeJustification(Request $request, Company $company, RhidComplianceService $compliance): JsonResponse|SymfonyResponse
    {
        $this->assertCompanyRhidReady($company);

        $payload = $request->validate([
            'idPerson' => ['required', 'integer', 'min:1'],
            'idJustificationType' => ['required', 'integer', 'min:1'],
            'justificativa' => ['required', 'string', 'max:2000'],
            'inicio' => ['required', 'string', 'regex:/^\d{8}(\d{4})?$/'],
            'fim' => ['required', 'string', 'regex:/^\d{8}(\d{4})?$/'],
            'minutesDiurno' => ['nullable', 'integer', 'min:0'],
            'minutesNoturno' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->rhidJsonOrError(
            fn () => $compliance->createJustification($company, $request->user(), $payload),
        );
    }

    public function updateJustification(Request $request, Company $company, RhidComplianceService $compliance, int $id): JsonResponse|SymfonyResponse
    {
        $this->assertCompanyRhidReady($company);

        $payload = $request->validate([
            'idPerson' => ['required', 'integer', 'min:1'],
            'idJustificationType' => ['required', 'integer', 'min:1'],
            'justificativa' => ['required', 'string', 'max:2000'],
            'inicio' => ['required', 'string', 'regex:/^\d{8}(\d{4})?$/'],
            'fim' => ['required', 'string', 'regex:/^\d{8}(\d{4})?$/'],
            'minutesDiurno' => ['nullable', 'integer', 'min:0'],
            'minutesNoturno' => ['nullable', 'integer', 'min:0'],
        ]);
        $payload['id'] = $id;

        return $this->rhidJsonOrError(
            fn () => $compliance->updateJustification($company, $request->user(), $payload),
        );
    }

    public function destroyJustification(Request $request, Company $company, RhidComplianceService $compliance, int $id): JsonResponse|SymfonyResponse
    {
        $this->assertCompanyRhidReady($company);

        return $this->rhidJsonOrError(
            fn () => $compliance->deleteJustification($company, $request->user(), $id),
        );
    }

    private function assertCompanyRhidReady(Company $company): void
    {
        abort_unless($company->is_active, 404);
        abort_unless($company->rhidConfigured(), 422, 'Integração RHID não configurada para esta empresa.');
    }
}
