<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PsychosocialAccessLog;
use App\Models\Survey;
use App\Services\Nr1AiAnalyzer;
use App\Services\PsychosocialDashboardBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportações da empresa: somente dados agregados, já filtrados pela política de grupo.
 * A exportação linha a linha não existe para a empresa; o dado bruto é interno da Talents
 * (Admin\SurveyRawExportController, com permissão específica e registro de auditoria).
 */
class ExportController extends Controller
{
    private const CSV_COLUMNS = ['recorte', 'setor', 'dimensao', 'n', 'indice_gravado', 'faixa_gravada', 'faixa', 'metodo', 'observacao'];

    private function companyId(Request $request): int
    {
        return (int) $request->user()->company_id;
    }

    public function json(Request $request, Survey $survey, Nr1AiAnalyzer $analyzer): JsonResponse
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        $survey->load(['template.sections.questions', 'company']);

        $this->logAccess($request, $survey, 'export_json');

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'company' => $survey->company?->name,
                'template_title' => $survey->template?->title,
            ],
            'aggregated' => $analyzer->buildAggregatedPayload($survey),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function csv(Request $request, Survey $survey, PsychosocialDashboardBuilder $builder): StreamedResponse
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        $rows = $builder->exportRows($builder->build($survey));
        $this->logAccess($request, $survey, 'export_csv');

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::CSV_COLUMNS, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (string $column) => $row[$column], self::CSV_COLUMNS), ';');
            }
            fclose($out);
        }, 'talents-export-'.$survey->id.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function logAccess(Request $request, Survey $survey, string $resource): void
    {
        PsychosocialAccessLog::query()->create([
            'user_id' => $request->user()->id,
            'company_id' => $survey->company_id,
            'survey_id' => $survey->id,
            'resource' => $resource,
            'document_version_id' => null,
            'ip' => $request->ip(),
        ]);
    }
}
