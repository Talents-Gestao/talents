<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondsWithRhidJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdatePunchScheduleSettingsRequest;
use App\Jobs\ProcessRhidEspelhoBatchJob;
use App\Jobs\ProcessRhidEspelhoParseJob;
use App\Models\Company;
use App\Models\RhidEspelhoBatch;
use App\Models\RhidEspelhoImport;
use App\Services\Rhid\EspelhoPdfIngestService;
use App\Services\Rhid\PunchScheduleSettingsService;
use App\Services\Rhid\RhidEspelhoService;
use App\Services\Rhid\RhidPersonSchedulePreferenceService;
use App\Services\Rhid\RhidReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Configuração operacional do ponto no Admin: horários, relatórios RHID e importação de espelho.
 */
class PontoEspelhoController extends Controller
{
    use RespondsWithRhidJson;

    public function punchScheduleShow(Company $company, PunchScheduleSettingsService $schedule): JsonResponse
    {
        $this->assertCompanyActive($company);

        return response()->json(['settings' => $schedule->getForCompany($company)]);
    }

    public function punchScheduleUpdate(
        UpdatePunchScheduleSettingsRequest $request,
        Company $company,
        PunchScheduleSettingsService $schedule,
    ): JsonResponse {
        $this->assertCompanyActive($company);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $schedule->saveForCompany($company, $validated);

        return response()->json(['settings' => $schedule->getForCompany($company)]);
    }

    public function batchSchedulePreferences(
        Request $request,
        Company $company,
        RhidPersonSchedulePreferenceService $prefs,
    ): JsonResponse {
        $this->assertCompanyRhidReady($company);

        $v = $request->validate([
            'id_people' => ['required', 'array', 'min:1', 'max:500'],
            'id_people.*' => ['integer', 'min:1'],
            'use_second_lunch_interval' => ['required', 'boolean'],
        ]);

        $n = $prefs->setBatch($company, $v['id_people'], $v['use_second_lunch_interval']);

        return response()->json([
            'ok' => true,
            'updated' => $n,
        ]);
    }

    public function startReport(Request $request, Company $company, RhidReportService $reports): JsonResponse|Response
    {
        $this->assertCompanyRhidReady($company);

        /** @var array<string, mixed> $body */
        $body = $request->all();

        return $this->rhidJsonOrError(fn () => $reports->startPontoReport($company, $request->user(), $body));
    }

    public function reportStatus(Request $request, Company $company, RhidReportService $reports): JsonResponse|Response
    {
        $this->assertCompanyRhidReady($company);

        $guid = $request->validate(['guid' => ['required', 'string']])['guid'];

        return $this->rhidJsonOrError(fn () => $reports->guidStatus($company, $request->user(), $guid));
    }

    public function downloadReport(Request $request, Company $company, RhidReportService $reports): JsonResponse|Response
    {
        $this->assertCompanyRhidReady($company);

        $data = $request->validate([
            'guid' => ['required', 'string'],
            'format' => ['required', 'string', 'max:20'],
        ]);

        try {
            $r = $reports->downloadSaveFile($company, $request->user(), $data['format'], $data['guid']);
        } catch (\App\Exceptions\RhidApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($r->failed()) {
            return response()->json(['message' => 'Falha ao baixar arquivo no RHID.'], 422);
        }

        $body = $r->body();
        $filename = 'rhid-'.$data['guid'].'.'.strtolower($data['format']);
        if ($data['format'] === 'PDF2') {
            $filename = 'rhid-'.$data['guid'].'.pdf';
        }

        return response($body, 200, [
            'Content-Type' => $r->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function storeEspelhoPdf(Request $request, Company $company, RhidEspelhoService $espelho): JsonResponse|Response
    {
        $this->assertCompanyRhidReady($company);

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
        } catch (\App\Exceptions\RhidApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'import' => $this->espelhoImportSummary($import),
        ]);
    }

    public function startEspelhoBatch(Request $request, Company $company): JsonResponse
    {
        $this->assertCompanyRhidReady($company);

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

    public function showEspelhoBatch(Company $company, int $batch): JsonResponse
    {
        $this->assertCompanyActive($company);

        $row = RhidEspelhoBatch::query()
            ->where('company_id', $company->id)
            ->whereKey($batch)
            ->firstOrFail();

        return response()->json([
            'batch' => $this->espelhoBatchPayload($row),
        ]);
    }

    public function listEspelhoImports(Request $request, Company $company): JsonResponse
    {
        $this->assertCompanyActive($company);

        $q = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->orderByDesc('id');

        if ($request->filled('id_person')) {
            $q->where('id_person', (int) $request->query('id_person'));
        }

        $pageSize = min(50, max(5, (int) $request->query('per_page', 15)));

        return response()->json($q->paginate($pageSize));
    }

    public function showEspelhoImport(Company $company, int $import): JsonResponse
    {
        $this->assertCompanyActive($company);

        $row = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->whereKey($import)
            ->with(['days' => fn ($q) => $q->orderBy('ref_date')])
            ->firstOrFail();

        return response()->json([
            'import' => $this->espelhoImportDetail($row),
        ]);
    }

    public function reparseEspelhoImport(Company $company, int $import): JsonResponse
    {
        $this->assertCompanyActive($company);

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

    public function syncParseEspelhoImport(
        Company $company,
        int $import,
        EspelhoPdfIngestService $ingest,
    ): JsonResponse {
        $this->assertCompanyActive($company);

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

    public function downloadEspelhoImportFile(Company $company, int $import): JsonResponse|StreamedResponse
    {
        $this->assertCompanyActive($company);

        $row = RhidEspelhoImport::query()
            ->where('company_id', $company->id)
            ->whereKey($import)
            ->firstOrFail();

        $disk = Storage::disk('local');
        if (! $disk->exists($row->storage_path)) {
            return response()->json(['message' => 'Arquivo não encontrado.'], 404);
        }

        $name = basename($row->storage_path);

        return $disk->response($row->storage_path, $name, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function assertCompanyActive(Company $company): void
    {
        abort_unless($company->is_active, 404);
    }

    private function assertCompanyRhidReady(Company $company): void
    {
        $this->assertCompanyActive($company);
        abort_unless($company->rhidConfigured(), 422, 'Integração RHID não configurada para esta empresa.');
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
