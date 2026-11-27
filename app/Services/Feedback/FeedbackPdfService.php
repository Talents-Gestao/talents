<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\FeedbackSession;
use App\Support\Feedback\FeedbackSessionStructure;
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
            'templateVersion.sections.questions',
            'answers.question',
            'signatures',
        ]);

        $tempDir = storage_path('app/dompdf-tmp');
        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        // O PDF é de quem administra a empresa: mostra também a «Reflexão do líder». Estrutura, título e
        // descrição vêm da versão do modelo usada pelo feedback; respostas de outra versão saem à parte.
        $version = FeedbackSessionStructure::version($session);

        return Pdf::loadView('reports.feedback-session', [
            'session' => $session,
            'version' => $version,
            'sections' => FeedbackSessionStructure::sections($session, true),
            'outOfVersionAnswers' => FeedbackSessionStructure::outOfVersionAnswers($session, true),
            'outOfVersionExtras' => FeedbackSessionStructure::outOfVersionExtras($session, true),
            'answersByQuestion' => $session->answers->keyBy('feedback_template_question_id'),
            'sectionExtras' => $session->section_extras ?? [],
        ])->setPaper('a4');
    }
}
