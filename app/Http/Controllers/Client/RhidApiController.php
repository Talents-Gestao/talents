<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\RhidApiException;
use App\Exceptions\RhidDomainChoiceRequiredException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Rhid\RhidAuthService;
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
            'companies' => ['nullable', 'array'],
            'companies.*' => ['integer'],
            'costcenters' => ['nullable', 'array'],
            'costcenters.*' => ['integer'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['integer'],
            'personroles' => ['nullable', 'array'],
            'personroles.*' => ['integer'],
            'people' => ['nullable', 'array'],
            'people.*' => ['integer'],
            'shifts' => ['nullable', 'array'],
            'shifts.*' => ['integer'],
            'justificationTypes' => ['nullable', 'array'],
            'justificationTypes.*' => ['integer'],
        ]);

        $payload['ini'] = $this->formatJustificationListDateForRhidApi((string) $payload['ini']);
        $payload['fim'] = $this->formatJustificationListDateForRhidApi((string) $payload['fim']);

        $payload = $this->normalizeJustificationListPayloadForRhid($payload);

        return $this->jsonOrError(fn () => $compliance->listJustifications($company, $request->user(), $payload));
    }

    /**
     * Alinha o corpo ao que o servico .NET do RHID costuma esperar (DataTables + listas nao-nulas).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeJustificationListPayloadForRhid(array $payload): array
    {
        $payload['draw'] = (int) config('rhid.justification_list_draw', 0);
        $payload['page'] = (int) ($payload['page'] ?? 0);
        $payload['maxSize'] = (int) ($payload['maxSize'] ?? 100);

        $listKeys = ['companies', 'costcenters', 'departments', 'personroles', 'people', 'shifts', 'justificationTypes'];
        foreach ($listKeys as $key) {
            if (! isset($payload[$key]) || ! is_array($payload[$key])) {
                $payload[$key] = [];
            }
        }

        $defaultCompanyId = config('rhid.justification_list_default_company_id');
        if ($defaultCompanyId !== null && $payload['companies'] === []) {
            $payload['companies'] = [(int) $defaultCompanyId];
        }

        return $payload;
    }

    /**
     * Converte yyyyMMdd validado para o formato esperado pelo POST justification.svc/list.
     *
     * @see config('rhid.justification_list_ini_fim_format') iso|compact|br
     */
    protected function formatJustificationListDateForRhidApi(string $yyyymmdd): string
    {
        $y = (int) substr($yyyymmdd, 0, 4);
        $m = (int) substr($yyyymmdd, 4, 2);
        $d = (int) substr($yyyymmdd, 6, 2);
        if (! checkdate($m, $d, $y)) {
            return $yyyymmdd;
        }

        $format = strtolower((string) config('rhid.justification_list_ini_fim_format', 'iso'));

        return match ($format) {
            'br', 'pt-br', 'pt_br' => sprintf('%02d/%02d/%04d', $d, $m, $y),
            'compact', 'yyyymmdd', 'ymd' => $yyyymmdd,
            default => sprintf('%04d-%02d-%02d', $y, $m, $d),
        };
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

    /**
     * Lista departamentos no customerdb RHID (GET customerdb/department.svc/a — ver documentacao API Control iD).
     */
    public function listDepartments(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);
        $query = $request->validate([
            'page' => ['nullable', 'integer', 'min:0'],
            'maxSize' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return $this->jsonOrError(fn () => $compliance->listDepartments($company, $request->user(), $query));
    }

    /**
     * Lista cargos (person role) no customerdb RHID.
     */
    public function listPersonRoles(Request $request, RhidComplianceService $compliance): JsonResponse|Response
    {
        $company = $this->company($request);
        $query = $request->validate([
            'page' => ['nullable', 'integer', 'min:0'],
            'maxSize' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return $this->jsonOrError(fn () => $compliance->listPersonRoles($company, $request->user(), $query));
    }

    /**
     * Detalhe de um colaborador (GET customerdb/person.svc/a/{id}).
     */
    public function showPerson(Request $request, RhidComplianceService $compliance, int $id): JsonResponse|Response
    {
        $company = $this->company($request);

        return $this->jsonOrError(fn () => $compliance->getPersonDetail($company, $request->user(), $id));
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

    public function downloadReport(Request $request, RhidReportService $reports, RhidAuthService $auth): JsonResponse|Response
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

        $body = $r->body();
        $wantInlineHtml = $request->boolean('inline') && strtoupper($data['format']) === 'HTML';

        if ($wantInlineHtml) {
            if ($body === '') {
                return response()->json(['message' => 'Arquivo HTML vazio retornado pelo RHID.'], 422);
            }
            $body = $this->injectRhidHtmlBaseForPreview($body, $auth->baseUrl($company));

            return response($body, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="rhid-espelho.html"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        }

        $filename = 'rhid-'.$data['guid'].'.'.strtolower($data['format']);
        if ($data['format'] === 'PDF2') {
            $filename = 'rhid-'.$data['guid'].'.pdf';
        }

        return response($body, 200, [
            'Content-Type' => $r->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * O HTML do espelho costuma referenciar CSS/JS com URLs relativas ao host do RHID.
     * Dentro de um iframe (blob ou mesmo dominio do Talents), sem <base> a pagina fica em branco.
     */
    private function injectRhidHtmlBaseForPreview(string $html, string $rhidBaseUrl): string
    {
        if (preg_match('/<base\s[^>]*\bhref\s*=/i', $html)) {
            return $html;
        }
        $base = rtrim($rhidBaseUrl, '/').'/';
        $tag = '<base href="'.htmlspecialchars($base, ENT_QUOTES, 'UTF-8').'">';
        if (preg_match('/<head\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);

            return substr($html, 0, $pos).$tag.substr($html, $pos);
        }
        if (preg_match('/<html\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);

            return substr($html, 0, $pos).'<head><meta charset="UTF-8">'.$tag.'</head>'.substr($html, $pos);
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'.$tag.'</head><body>'.$html.'</body></html>';
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
