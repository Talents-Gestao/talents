<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\RhidApiException;
use App\Exceptions\RhidDomainChoiceRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdatePunchScheduleSettingsRequest;
use App\Jobs\ProcessRhidEspelhoBatchJob;
use App\Jobs\ProcessRhidEspelhoParseJob;
use App\Models\Company;
use App\Models\RhidEspelhoBatch;
use App\Models\RhidEspelhoImport;
use App\Services\Rhid\EspelhoPdfIngestService;
use App\Services\Rhid\EspelhoScheduleAdherenceService;
use App\Services\Rhid\PunchScheduleSettingsService;
use App\Services\Rhid\RhidAuthService;
use App\Services\Rhid\RhidComplianceService;
use App\Services\Rhid\RhidDeviceService;
use App\Services\Rhid\RhidEspelhoService;
use App\Services\Rhid\RhidMonitoringService;
use App\Services\Rhid\RhidReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            /** 1 = ativos, 2 = inativos — repassado ao GET person.svc/a quando suportado */
            'status' => ['nullable', 'integer', 'in:1,2'],
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

        $body = $this->unwrapRhidSaveFilePayload((string) $r->body());
        $wantInlineHtml = $request->boolean('inline') && strtoupper($data['format']) === 'HTML';

        if ($wantInlineHtml) {
            if (trim($body) === '') {
                return response()->json([
                    'message' => 'Arquivo HTML vazio retornado pelo RHID ao baixar o espelho. '
                        .'Aguarde alguns segundos apos 100% e tente de novo, ou use Download.',
                    'rhid_status' => $r->status(),
                ], 422);
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
     * Alguns tenants devolvem o arquivo dentro de um envelope JSON em vez de HTML cru.
     */
    private function unwrapRhidSaveFilePayload(string $body): string
    {
        $t = trim($body);
        if ($t === '' || $t[0] !== '{') {
            return $body;
        }
        $j = json_decode($t, true);
        if (! is_array($j)) {
            return $body;
        }
        foreach (['html', 'Html', 'HTML', 'content', 'Content', 'file', 'File'] as $k) {
            if (isset($j[$k]) && is_string($j[$k]) && trim($j[$k]) !== '') {
                return $j[$k];
            }
        }
        if (isset($j['d']) && is_string($j['d']) && trim($j['d']) !== '') {
            return $j['d'];
        }

        return $body;
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

    public function punchScheduleSettings(Request $request, PunchScheduleSettingsService $schedule): JsonResponse
    {
        $company = $this->company($request);
        $settings = $schedule->getForCompany($company);

        return response()->json(['settings' => $settings]);
    }

    public function updatePunchScheduleSettings(UpdatePunchScheduleSettingsRequest $request, PunchScheduleSettingsService $schedule): JsonResponse
    {
        $company = $this->company($request);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $schedule->saveForCompany($company, $validated);

        return response()->json(['settings' => $schedule->getForCompany($company)]);
    }

    public function espelhoScheduleAdherence(Request $request, EspelhoScheduleAdherenceService $adherence): JsonResponse
    {
        $company = $this->company($request);
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
                'message' => 'Periodo maximo de '.EspelhoScheduleAdherenceService::MAX_RANGE_DAYS.' dias.',
            ], 422);
        }

        $idPerson = isset($v['id_person']) ? (int) $v['id_person'] : null;

        return response()->json($adherence->aggregateForCompany($company, $ini, $fim, $idPerson));
    }

    public function espelhoScheduleAdherenceMarks(Request $request, EspelhoScheduleAdherenceService $adherence): JsonResponse
    {
        $company = $this->company($request);
        $v = $request->validate([
            'ini' => ['required', 'date'],
            'fim' => ['required', 'date', 'after_or_equal:ini'],
            'id_person' => ['required', 'integer', 'min:1'],
        ]);

        $ini = Carbon::parse($v['ini'])->startOfDay();
        $fim = Carbon::parse($v['fim'])->startOfDay();
        $span = $ini->diffInDays($fim) + 1;
        if ($span > EspelhoScheduleAdherenceService::MAX_RANGE_DAYS) {
            return response()->json([
                'message' => 'Periodo maximo de '.EspelhoScheduleAdherenceService::MAX_RANGE_DAYS.' dias.',
            ], 422);
        }

        return response()->json($adherence->personMarksForAdherencePeriod(
            $company,
            $ini,
            $fim,
            (int) $v['id_person'],
        ));
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

    /**
     * Grava o PDF do espelho no storage e enfileira o parse Python (apos GUID em 100%).
     */
    public function storeEspelhoPdf(Request $request, RhidEspelhoService $espelho): JsonResponse|Response
    {
        $company = $this->company($request);
        $data = $request->validate([
            'guid' => ['required', 'string', 'max:64'],
            'id_person' => ['required', 'integer', 'min:1'],
            'ini' => ['required', 'string'],
            'fim' => ['required', 'string'],
        ]);

        try {
            $import = $espelho->storePdfFromGuid(
                $company,
                $request->user(),
                $data['guid'],
                (int) $data['id_person'],
                $data['ini'],
                $data['fim'],
            );
        } catch (RhidApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'import' => $this->espelhoImportSummary($import),
        ]);
    }

    /**
     * Inicia importacao em lote (fila): continua com a aba fechada se o worker estiver ativo.
     */
    public function startEspelhoBatch(Request $request): JsonResponse|Response
    {
        $company = $this->company($request);
        $data = $request->validate([
            'person_ids' => ['required', 'array', 'min:1', 'max:500'],
            'person_ids.*' => ['integer', 'min:1'],
            'ini' => ['required', 'string', 'regex:/^\d{8}$/'],
            'fim' => ['required', 'string', 'regex:/^\d{8}$/'],
            'rhid_status' => ['nullable', 'string', 'in:1,2'],
            'list_columns' => ['nullable', 'array'],
            'list_columns.*' => ['string', 'max:64'],
            'filters' => ['nullable', 'array'],
            'filters.list_company_str' => ['nullable', 'array'],
            'filters.list_company_str.*' => ['integer'],
            'filters.list_department_str' => ['nullable', 'array'],
            'filters.list_department_str.*' => ['integer'],
            'filters.list_cost_center_str' => ['nullable', 'array'],
            'filters.list_cost_center_str.*' => ['integer'],
            'filters.list_person_role_str' => ['nullable', 'array'],
            'filters.list_person_role_str.*' => ['integer'],
            'filters.list_shift_str' => ['nullable', 'array'],
            'filters.list_shift_str.*' => ['integer'],
        ]);

        $personIds = array_values(array_unique(array_map('intval', $data['person_ids'])));

        $meta = [
            'person_ids' => $personIds,
            'ini' => $data['ini'],
            'fim' => $data['fim'],
            'rhid_status' => $data['rhid_status'] ?? null,
            'list_columns' => $data['list_columns'] ?? null,
            'filters' => array_filter($data['filters'] ?? [], static fn ($v) => $v !== null && $v !== []),
        ];

        $batch = RhidEspelhoBatch::query()->create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'total' => count($personIds),
            'processed' => 0,
            'succeeded' => 0,
            'skipped' => 0,
            'current_id_person' => null,
            'skipped_person_ids' => [],
            'meta_json' => $meta,
            'message' => null,
        ]);

        ProcessRhidEspelhoBatchJob::dispatch($batch->id);

        return response()->json([
            'batch' => $this->espelhoBatchPayload($batch),
        ]);
    }

    public function showEspelhoBatch(Request $request, int $batch): JsonResponse|Response
    {
        $company = $this->company($request);
        $row = RhidEspelhoBatch::query()
            ->where('company_id', $company->id)
            ->whereKey($batch)
            ->firstOrFail();

        return response()->json([
            'batch' => $this->espelhoBatchPayload($row),
        ]);
    }

    public function listEspelhoImports(Request $request): JsonResponse|Response
    {
        $company = $this->company($request);
        $q = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->orderByDesc('id');

        if ($request->filled('id_person')) {
            $q->where('id_person', (int) $request->query('id_person'));
        }

        $pageSize = min(50, max(5, (int) $request->query('per_page', 15)));

        return response()->json($q->paginate($pageSize));
    }

    public function showEspelhoImport(Request $request, int $import): JsonResponse|Response
    {
        $company = $this->company($request);
        $row = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->whereKey($import)
            ->with(['days' => fn ($q) => $q->orderBy('ref_date')])
            ->firstOrFail();

        return response()->json([
            'import' => $this->espelhoImportDetail($row),
        ]);
    }

    public function reparseEspelhoImport(Request $request, int $import): JsonResponse|Response
    {
        $company = $this->company($request);
        $row = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->whereKey($import)
            ->firstOrFail();

        ProcessRhidEspelhoParseJob::dispatch($row->id);

        return response()->json([
            'message' => 'Reprocessamento enfileirado.',
            'import_id' => $row->id,
        ]);
    }

    public function syncParseEspelhoImport(Request $request, int $import, EspelhoPdfIngestService $ingest): JsonResponse|Response
    {
        $company = $this->company($request);
        $row = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->whereKey($import)
            ->firstOrFail();

        $ingest->parseAndPersist($row);
        $row->refresh();

        return response()->json([
            'import' => $this->espelhoImportDetail($row->load(['days' => fn ($q) => $q->orderBy('ref_date')])),
        ]);
    }

    public function downloadEspelhoImportFile(Request $request, int $import): JsonResponse|Response|StreamedResponse
    {
        $company = $this->company($request);
        $row = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->whereKey($import)
            ->firstOrFail();

        $disk = Storage::disk('local');
        if (! $disk->exists($row->storage_path)) {
            return response()->json(['message' => 'Arquivo nao encontrado.'], 404);
        }

        $name = basename($row->storage_path);

        return $disk->response($row->storage_path, $name, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function espelhoBatchPayload(RhidEspelhoBatch $b): array
    {
        $total = max(0, (int) $b->total);
        $processed = max(0, (int) $b->processed);
        $remaining = max(0, $total - $processed);

        return [
            'id' => $b->id,
            'status' => $b->status,
            'total' => $total,
            'processed' => $processed,
            'remaining' => $remaining,
            'succeeded' => (int) $b->succeeded,
            'skipped' => (int) $b->skipped,
            'current_id_person' => $b->current_id_person,
            'message' => $b->message,
            'skipped_person_ids' => $b->skipped_person_ids ?? [],
            'updated_at' => $b->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function espelhoImportSummary(RhidEspelhoImport $import): array
    {
        return [
            'id' => $import->id,
            'id_person' => $import->id_person,
            'period_ini' => $import->period_ini->format('Y-m-d'),
            'period_fim' => $import->period_fim->format('Y-m-d'),
            'guid' => $import->guid,
            'parse_status' => $import->parse_status,
            'parse_error' => $import->parse_error,
            'parsed_at' => $import->parsed_at?->toIso8601String(),
            'created_at' => $import->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function espelhoImportDetail(RhidEspelhoImport $import): array
    {
        $base = $this->espelhoImportSummary($import);
        $base['days'] = $import->relationLoaded('days')
            ? $import->days->map(fn ($d) => [
                'id' => $d->id,
                'ref_date' => $d->ref_date->format('Y-m-d'),
                'row_json' => $d->row_json,
            ])->values()->all()
            : [];

        return $base;
    }
}
