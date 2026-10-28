<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PsychosocialAccessLog;
use App\Models\Survey;
use App\Support\Nr1GroupPrivacy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dado bruto (uma linha por resposta) é de uso interno da Talents: só com a permissão específica
 * `nr1_dados_brutos` e sempre com registro de auditoria. Faixa etária e tempo de casa nunca saem;
 * a data de conclusão sai só como mês. Empresa, PASSEG e Comercial nunca chegam aqui.
 */
class SurveyRawExportController extends Controller
{
    public function csv(Request $request, Company $company, Survey $survey): StreamedResponse
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);

        $survey->load(['template.sections.questions', 'company.departments', 'completedResponses.answers']);

        PsychosocialAccessLog::query()->create([
            'user_id' => $request->user()->id,
            'company_id' => $survey->company_id,
            'survey_id' => $survey->id,
            'resource' => 'raw_export',
            'document_version_id' => null,
            'ip' => $request->ip(),
        ]);

        $departments = $survey->company?->departments->keyBy('id') ?? collect();
        $questionMeta = [];
        foreach ($survey->template?->sections ?? [] as $section) {
            foreach ($section->questions as $question) {
                $questionMeta[$question->id] = ['dimension' => $section->title, 'body' => $question->body];
            }
        }

        return response()->streamDownload(function () use ($survey, $departments, $questionMeta): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['linha_resposta', 'setor', 'mes_conclusao', 'dimensao', 'pergunta', 'valor_1_a_5'], ';');

            $row = 0;
            foreach ($survey->completedResponses as $response) {
                $row++;
                $department = $response->department_id
                    ? ($departments->get((int) $response->department_id)?->name ?? '')
                    : '';
                foreach ($response->answers as $answer) {
                    $meta = $questionMeta[$answer->survey_template_question_id] ?? ['dimension' => '', 'body' => ''];
                    fputcsv($out, [
                        $row,
                        $department,
                        Nr1GroupPrivacy::month($response->completed_at),
                        $meta['dimension'],
                        $meta['body'],
                        $answer->value,
                    ], ';');
                }
            }
            fclose($out);
        }, 'dados-brutos-'.$survey->id.'-'.now()->format('Y-m').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
