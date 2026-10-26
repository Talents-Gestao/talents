<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Controller;
use App\Models\PsychosocialAccessLog;
use App\Models\Survey;
use App\Services\ExecutiveBriefService;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Services\PsychosocialDashboardBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Painel gerencial novo de riscos psicossociais. Só leitura; convive com a tela de Resultados.
 */
class PsychosocialDashboardController extends Controller
{
    private const ITEM_CSV_COLUMNS = ['posicao', 'dimensao', 'pergunta', 'n', 'nunca_pct', 'raramente_pct', 'as_vezes_pct', 'frequentemente_pct', 'sempre_pct', 'favoravel_pct', 'desfavoravel_pct', 'observacao'];

    private const CSV_COLUMNS = ['recorte', 'setor', 'dimensao', 'n', 'indice_gravado', 'faixa_gravada', 'faixa', 'metodo', 'observacao'];

    public function __construct(private readonly PsychosocialDashboardBuilder $builder) {}

    public function show(
        Request $request,
        Survey $survey,
        ExecutiveBriefService $executiveBriefService,
        PsychosocialDocumentService $documents,
    ): Response {
        $this->authorizeSurvey($request, $survey);
        $this->logAccess($request, $survey, 'dashboard');

        $user = $request->user();
        $canValidate = $user->canAccess(PermissionModule::Relatorios, PermissionAction::Validate)
            && ! $user->isSuperAdmin()
            && ! $user->isOwner();

        return Inertia::render('Client/Surveys/PsychosocialDashboard', [
            'dashboard' => $this->builder->build($survey),
            'executiveBrief' => $executiveBriefService->currentVersionSummary($survey),
            'psychosocialDocuments' => $documents->documentsSummary($survey),
            'documentsUrl' => $survey->usesReviewFlow() ? route('client.surveys.documents.index', $survey) : null,
            'canValidate' => $canValidate,
            'validationDeclaration' => PsychosocialDocumentService::IN_SYSTEM_DECLARATION,
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

    /**
     * Panorama para impressão: mesma fonte e mesmas regras do painel (grupo mínimo, só agregado).
     * A página é HTML próprio para imprimir/salvar em PDF pelo navegador; nada é gerado nem gravado.
     */
    public function print(Request $request, Survey $survey): HttpResponse
    {
        $this->authorizeSurvey($request, $survey);
        $dashboard = $this->builder->build($survey);
        $this->logAccess($request, $survey, 'panorama_print');

        return response()->view('psychosocial.panorama_print', [
            'dashboard' => $dashboard,
            'generatedAt' => now(),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    /**
     * Cartões das perguntas em CSV: distribuição e % favorável da campanha inteira, sem recorte por setor.
     */
    public function exportItemsCsv(Request $request, Survey $survey): StreamedResponse
    {
        $this->authorizeSurvey($request, $survey);
        $dashboard = $this->builder->build($survey);
        $this->logAccess($request, $survey, 'export_items_csv');

        $pct = fn (?float $value): string => $value === null ? '' : number_format($value, 1, ',', '');

        return response()->streamDownload(function () use ($dashboard, $pct): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::ITEM_CSV_COLUMNS, ';');
            foreach ($dashboard['items'] as $item) {
                fputcsv($out, [
                    $item['position'],
                    $item['section_title'],
                    $item['body'],
                    $item['n'],
                    $pct($item['distribution'][1]),
                    $pct($item['distribution'][2]),
                    $pct($item['distribution'][3]),
                    $pct($item['distribution'][4]),
                    $pct($item['distribution'][5]),
                    $pct($item['favorable_pct']),
                    $pct($item['unfavorable_pct']),
                    $item['quality_alert'] ? 'N diferente do total de respondentes' : '',
                ], ';');
            }
            fclose($out);
        }, 'perguntas-'.$survey->id.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
