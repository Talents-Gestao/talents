<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class FeedbackPdfService
{
    public function download(FeedbackSession $session)
    {
        $session->load([
            'employee.department',
            'employee.position',
            'leader',
            'template.sections.questions',
            'answers.question',
            'signatures',
        ]);

        $tempDir = storage_path('app/dompdf-tmp');
        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        return Pdf::loadView('reports.feedback-session', [
            'session' => $session,
            'answersByQuestion' => $session->answers->keyBy('feedback_template_question_id'),
            'sectionExtras' => $session->section_extras ?? [],
        ])->setPaper('a4');
    }
}
