<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PsychosocialAccessLog;
use App\Models\Survey;
use App\Services\ExecutiveBriefService;
use App\Services\PsychosocialDashboardBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Painel gerencial novo de riscos psicossociais. Só leitura; convive com a tela de Resultados.
 */
class PsychosocialDashboardController extends Controller
{
    private const CSV_COLUMNS = ['recorte', 'setor', 'dimensao', 'n', 'indice_gravado', 'faixa_gravada', 'faixa', 'metodo', 'observacao'];

    public function __construct(private readonly PsychosocialDashboardBuilder $builder)
    {
    }

    public function show(Request $request, Survey $survey, ExecutiveBriefService $executiveBriefService): Response
    {
        $this->authorizeSurvey($request, $survey);
        $this->logAccess($request, $survey, 'dashboard');

        return Inertia::render('Client/Surveys/PsychosocialDashboard', [
            'dashboard' => $this->builder->build($survey),
            'executiveBrief' => $executiveBriefService->currentVersionSummary($survey),
        ]);
    }

    public function exportCsv(Request $request, Survey $survey): StreamedResponse
    {
        $this->authorizeSurvey($request, $survey);
        $rows = $this->builder->exportRows($this->builder->build($survey));
        $this->logAccess($request, $survey, 'export_csv');

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::CSV_COLUMNS, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (string $column) => $row[$column], self::CSV_COLUMNS), ';');
            }
            fclose($out);
        }, 'painel-psicossocial-'.$survey->id.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportJson(Request $request, Survey $survey): JsonResponse
    {
        $this->authorizeSurvey($request, $survey);
        $dashboard = $this->builder->build($survey);
        $this->logAccess($request, $survey, 'export_json');

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'survey' => $dashboard['survey'],
            'method' => $dashboard['method'],
            'band_disclaimer' => $dashboard['band_disclaimer'],
            'polarity_warning' => $dashboard['polarity_warning'],
            'min_group_size' => $dashboard['min_group_size'],
            'respondents' => $dashboard['respondents'],
            'eligible_count' => $dashboard['eligible']['count'],
            'rows' => $this->builder->exportRows($dashboard),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function authorizeSurvey(Request $request, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->company_id, 404);
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
