<?php

namespace App\Http\Controllers\Admin\Interviews;

use App\Enums\InterviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Services\Interview\InterviewReportRenderer;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InterviewReportController extends Controller
{
    public function pdf(Interview $interview, InterviewReportRenderer $renderer): HttpResponse
    {
        $this->ensureCompleted($interview);

        $loaded = $renderer->loadInterview($interview->id);
        $filename = $this->safeFilename($loaded, 'pdf');

        return $renderer->pdf($loaded)->download($filename);
    }

    public function docx(Interview $interview, InterviewReportRenderer $renderer): BinaryFileResponse
    {
        $this->ensureCompleted($interview);

        $loaded = $renderer->loadInterview($interview->id);
        $path = $renderer->docx($loaded);
        $filename = $this->safeFilename($loaded, 'docx');

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function ensureCompleted(Interview $interview): void
    {
        if ($interview->status !== InterviewStatus::Completed) {
            abort(422, 'O relatório só está disponível após o processamento concluir.');
        }
    }

    private function safeFilename(Interview $interview, string $extension): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($interview->candidate_name)) ?: 'entrevista';

        return trim($slug, '-').'-'.$interview->id.'.'.$extension;
    }
}
