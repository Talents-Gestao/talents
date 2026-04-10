<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\RhidApiException;
use App\Exceptions\RhidDomainChoiceRequiredException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Rhid\RhidComplianceService;
use App\Services\Rhid\RhidDeviceService;
use App\Services\Rhid\RhidMonitoringService;
use App\Services\Rhid\RhidReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RhidApiController extends Controller
{
    protected function company(Request $request): Company
    {
        return $request->user()->company()->firstOrFail();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return JsonResponse|(\Illuminate\Http\Response)
     */
    protected function jsonOrError(callable $callback): JsonResponse|Response
    {
        $jsonFlags = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;
        if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $jsonFlags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }

        try {
            return response()->json($callback(), 200, [], $jsonFlags);
        } catch (RhidDomainChoiceRequiredException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'needs_domain' => true,
                'domains' => $e->listCustomer,
            ], 422, [], $jsonFlags);
        } catch (RhidApiException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'payload' => $e->payload,
            ], 422, [], $jsonFlags);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Falha na integracao com o RHID. Verifique os logs do servidor ou tente novamente.',
                'debug_message' => config('app.debug') ? $e->getMessage() : null,
                'debug_type' => config('app.debug') ? $e::class : null,
            ], 500, [], $jsonFlags);
        }
    }

    public function justificationTypes(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);

        return $this->jsonOrError(fn () => $compliance->listJustificationTypes($company, $request->user(), $request->query()));
    }

    public function alertTypes(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);

        return $this->jsonOrError(fn () => $compliance->listAlertTypes($company, $request->user(), $request->query()));
    }

    public function listJustifications(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);
        $payload = $request->validate([
            'ini' => ['required', 'string'],
            'fim' => ['required', 'string'],
            'page' => ['nullable', 'integer', 'min:0'],
            'maxSize' => ['nullable', 'integer', 'min:1', 'max:500'],
            'companies' => ['nullable', 'array'],
            'companies.*' => ['integer'],
            'costcenters' => ['nullable', 'array'],
            'departments' => ['nullable', 'array'],
            'personroles' => ['nullable', 'array'],
            'people' => ['nullable', 'array'],
            'shifts' => ['nullable', 'array'],
            'justificationTypes' => ['nullable', 'array'],
        ]);

        return $this->jsonOrError(fn () => $compliance->listJustifications($company, $request->user(), $payload));
    }

    public function storeJustification(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return $this->jsonOrError(fn () => $compliance->createJustification($company, $request->user(), $payload));
    }

    public function massJustification(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);
        /** @var array<string, mixed> $payload */
        $payload = $request->validate([
            'idJustificationType' => ['required', 'integer'],
            'justificativa' => ['required', 'string'],
            'inicio' => ['required', 'string'],
            'fim' => ['required', 'string'],
            'minutesDiurno' => ['nullable', 'integer'],
            'minutesNoturno' => ['nullable', 'integer'],
            'selectedIdPerson' => ['required', 'array', 'min:1'],
            'selectedIdPerson.*' => ['integer'],
        ]);

        return $this->jsonOrError(fn () => $compliance->massJustification($company, $request->user(), $payload));
    }

    public function destroyJustification(Request $request, RhidComplianceService $compliance, int $id): JsonResponse|Response
    {
        $company = $this->company($request);

        return $this->jsonOrError(fn () => $compliance->deleteJustification($company, $request->user(), $id));
    }

    public function personBankHours(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);
        $query = $request->validate([
            'date' => ['required'],
            'companies' => ['nullable', 'integer'],
            'costcenters' => ['nullable', 'integer'],
            'departments' => ['nullable', 'integer'],
            'people' => ['nullable', 'array'],
            'people.*' => ['integer'],
            'personroles' => ['nullable', 'integer'],
        ]);

        $query = array_filter(
            $query,
            static fn ($v) => $v !== null && $v !== '' && $v !== []
        );

        return $this->jsonOrError(fn () => [
            'date' => (string) $query['date'],
            'rows' => $compliance->personBankHours($company, $request->user(), $query),
            'source' => 'person_banco_horas',
        ]);
    }

    public function allPersonBankHours(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);
        $validated = $request->validate([
            'date' => ['required'],
            'listPageSize' => ['nullable', 'integer', 'min:1', 'max:500'],
            'bankChunk' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return $this->jsonOrError(fn () => $compliance->allPersonBankHoursAggregated(
            $company,
            $request->user(),
            (string) $validated['date'],
            (int) ($validated['listPageSize'] ?? 200),
            (int) ($validated['bankChunk'] ?? 50),
        ));
    }

    public function listPeople(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);
        $query = $request->validate([
            'page' => ['nullable', 'integer', 'min:0'],
            'maxSize' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return $this->jsonOrError(fn () => $compliance->listPersons($company, $request->user(), $query));
    }

    public function massPersonShift(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);
        $items = $request->validate([
            '*.idPerson' => ['required', 'integer'],
            '*.idShift' => ['required', 'integer'],
            '*.startStr' => ['required', 'string'],
            '*.endStr' => ['required', 'string'],
        ]);

        return $this->jsonOrError(fn () => $compliance->massPersonShift($company, $request->user(), array_values($items)));
    }

    public function startReport(Request $request, RhidReportService $reports): JsonResponse|Response
    {
        $company = $this->company($request);
        /** @var array<string, mixed> $body */
        $body = $request->all();

        return $this->jsonOrError(fn () => $reports->startPontoReport($company, $request->user(), $body));
    }

    public function reportStatus(Request $request, RhidReportService $reports): JsonResponse|Response
    {
        $company = $this->company($request);
        $guid = $request->validate(['guid' => ['required', 'string']])['guid'];

        return $this->jsonOrError(fn () => $reports->guidStatus($company, $request->user(), $guid));
    }

    public function downloadReport(Request $request, RhidReportService $reports): JsonResponse|Response
    {
        $company = $this->company($request);
        $data = $request->validate([
            'guid' => ['required', 'string'],
            'format' => ['required', 'string', 'max:20'],
        ]);

        try {
            $r = $reports->downloadSaveFile($company, $request->user(), $data['format'], $data['guid']);
        } catch (RhidApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($r->failed()) {
            return response()->json(['message' => 'Falha ao baixar arquivo no RHID.'], 422);
        }

        $filename = 'rhid-'.$data['guid'].'.'.strtolower($data['format']);
        if ($data['format'] === 'PDF2') {
            $filename = 'rhid-'.$data['guid'].'.pdf';
        }

        return response($r->body(), 200, [
            'Content-Type' => $r->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportAfd(Request $request, RhidReportService $reports): JsonResponse|Response
    {
        $company = $this->company($request);
        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:afd,afd671'],
            'ini' => ['required'],
            'fim' => ['required'],
            'idCompany' => ['nullable', 'integer'],
            'idDepartament' => ['nullable', 'integer'],
            'idCostCenter' => ['nullable', 'integer'],
            'idPerson' => ['nullable', 'integer'],
            'body' => ['nullable', 'string'],
        ]);

        $query = [
            'tipo' => $validated['tipo'],
            'ini' => $validated['ini'],
            'fim' => $validated['fim'],
        ];
        foreach (['idCompany', 'idDepartament', 'idCostCenter', 'idPerson'] as $k) {
            if (array_key_exists($k, $validated) && $validated[$k] !== null) {
                $query[$k] = $validated[$k];
            }
        }

        $raw = $validated['body'] ?? '[99000001]';

        try {
            $r = $reports->exportAfd($company, $request->user(), $query, $raw);
        } catch (RhidApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($r->failed()) {
            return response()->json(['message' => 'Falha ao exportar AFD.'], 422);
        }

        $ext = $validated['tipo'] === 'afd671' ? 'zip' : 'txt';
        $filename = 'afd-'.$validated['ini'].'-'.$validated['fim'].'.'.$ext;

        return response($r->body(), 200, [
            'Content-Type' => $r->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function lastPunches(Request $request, RhidMonitoringService $monitoring): JsonResponse|Response
    {
        $company = $this->company($request);

        return $this->jsonOrError(fn () => $monitoring->ultimasMarcacoes($company, $request->user()));
    }

    public function devices(Request $request, RhidDeviceService $devices): JsonResponse|Response
    {
        $company = $this->company($request);

        return $this->jsonOrError(fn () => $devices->list($company, $request->user(), $request->query()));
    }

    public function storeDevice(Request $request, RhidDeviceService $devices): JsonResponse|Response
    {
        $company = $this->company($request);
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return $this->jsonOrError(fn () => $devices->create($company, $request->user(), $payload));
    }

    public function updateDevice(Request $request, RhidDeviceService $devices): JsonResponse|Response
    {
        $company = $this->company($request);
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return $this->jsonOrError(fn () => $devices->update($company, $request->user(), $payload));
    }

    public function showDevice(Request $request, RhidDeviceService $devices, int $id): JsonResponse|Response
    {
        $company = $this->company($request);

        return $this->jsonOrError(fn () => $devices->show($company, $request->user(), $id));
    }

    public function destroyDevice(Request $request, RhidDeviceService $devices, int $id): JsonResponse|Response
    {
        $company = $this->company($request);

        return $this->jsonOrError(fn () => $devices->delete($company, $request->user(), $id));
    }

    public function enableIdCloud(Request $request, RhidDeviceService $devices, int $id): JsonResponse|Response
    {
        $company = $this->company($request);

        try {
            $r = $devices->enableIdCloud($company, $request->user(), $id);
        } catch (RhidApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => $r->successful(),
            'body' => $r->body(),
            'status' => $r->status(),
        ]);
    }

    public function forceResyncAll(Request $request, RhidDeviceService $devices): JsonResponse|Response
    {
        $company = $this->company($request);

        try {
            $r = $devices->forceResyncAll($company, $request->user());
        } catch (RhidApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => $r->successful(),
            'status' => $r->status(),
        ]);
    }
}
