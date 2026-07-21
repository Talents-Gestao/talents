<?php

declare(strict_types=1);

namespace App\Services\Offboarding;

use App\Models\ExitInterview;
use App\Support\Offboarding\ExitInterviewScript;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class ExitInterviewPdfService
{
    /**
     * @param  bool  $includeConsultantNotes  Inclui anotações internas (uso Talents / admin).
     */
    public function download(ExitInterview $interview, bool $includeConsultantNotes = true)
    {
        $interview->load(['company:id,name', 'employee:id,name,email', 'creator:id,name']);

        $tempDir = storage_path('app/dompdf-tmp');
        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        return Pdf::loadView('reports.exit-interview', [
            'interview' => $interview,
            'sections' => ExitInterviewScript::sections(),
            'consultantNoteFields' => ExitInterviewScript::consultantNoteFields(),
            'answers' => $interview->answers ?? [],
            'consultantNotes' => $interview->consultant_notes ?? [],
            'includeConsultantNotes' => $includeConsultantNotes,
        ])->setPaper('a4');
    }
}
