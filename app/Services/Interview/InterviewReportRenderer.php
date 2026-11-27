<?php

namespace App\Services\Interview;

use App\Enums\InterviewAnswerOrigin;
use App\Models\Interview;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class InterviewReportRenderer
{
    public function loadInterview(int $interviewId): Interview
    {
        return Interview::query()
            ->with([
                'questionnaire.sections.questions',
                'answers.question.section',
                'company:id,name',
                'createdBy:id,name',
                'reviewedBy:id,name',
            ])
            ->findOrFail($interviewId);
    }

    public function pdf(Interview $interview): \Barryvdh\DomPDF\PDF
    {
        $this->ensureDompdfWritableDirs();

        $fontDir = storage_path('fonts');
        $tempDir = storage_path('app/dompdf-tmp');
        $chroot = realpath(base_path()) ?: base_path();

        $pdf = Pdf::loadView('admin.entrevistas.report-pdf', [
            'interview' => $interview,
            'sections' => $this->groupedSections($interview),
            'reviewLine' => $this->reviewLine($interview),
            'unreviewedLabel' => self::UNREVIEWED_LABEL,
        ]);

        $pdf->setOption('fontDir', $fontDir);
        $pdf->setOption('fontCache', $fontDir);
        $pdf->setOption('tempDir', $tempDir);
        $pdf->setOption('chroot', $chroot);

        return $pdf->setPaper('a4');
    }

    public function docx(Interview $interview): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addTitle('Relatório de Entrevista', 1);
        $section->addText('Candidato: '.$interview->candidate_name);
        if ($interview->position_title) {
            $section->addText('Vaga: '.$interview->position_title);
        }
        $section->addText('Roteiro: '.$interview->questionnaire->name);
        $section->addText('Data: '.($interview->finished_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? '—'));
        $section->addText('Revisão humana: '.$this->reviewLine($interview));
        $section->addTextBreak(1);

        foreach ($this->groupedSections($interview) as $block) {
            $section->addTitle($block['title'], 2);
            foreach ($block['items'] as $item) {
                $section->addText($item['question'], ['bold' => true]);
                if ($item['unreviewed_ai']) {
                    $section->addText(self::UNREVIEWED_LABEL, ['italic' => true, 'color' => 'B45309']);
                }
                $section->addText($item['answer'], [], ['alignment' => Jc::BOTH]);
                if ($item['raw_quote']) {
                    $section->addText('Evidência: “'.$item['raw_quote'].'”', ['italic' => true, 'size' => 9]);
                }
                if ($item['reviewer_note']) {
                    $section->addText('Observação: '.$item['reviewer_note'], ['size' => 9]);
                }
                $section->addTextBreak(1);
            }
        }

        if ($interview->transcript_text) {
            $section->addPageBreak();
            $section->addTitle('Transcrição completa', 2);
            $section->addText($interview->transcript_text);
        }

        $path = storage_path('app/interview-tmp/report-'.$interview->id.'-'.uniqid().'.docx');
        File::ensureDirectoryExists(dirname($path));
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    public const UNREVIEWED_LABEL = 'Sugestão da IA não revisada';

    public const NO_EVIDENCE_LABEL = 'Sem evidência na transcrição';

    public function reviewLine(Interview $interview): string
    {
        if (! $interview->isReviewed()) {
            return 'pendente';
        }

        return 'concluída em '.$interview->reviewed_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i')
            .($interview->reviewedBy ? ' por '.$interview->reviewedBy->name : '');
    }

    /**
     * Resposta da IA ainda não revisada é marcada como «Sugestão da IA não revisada»;
     * sem trecho que sustente, aparece «Sem evidência na transcrição» (nada é inventado).
     *
     * @return list<array{title: string, items: list<array{question: string, answer: string, raw_quote: string|null, reviewer_note: string|null, unreviewed_ai: bool}>}>
     */
    public function groupedSections(Interview $interview): array
    {
        $answersByQuestionId = $interview->answers->keyBy('question_id');
        $reviewed = $interview->isReviewed();
        $out = [];

        foreach ($interview->questionnaire->sections as $section) {
            $items = [];
            foreach ($section->questions as $question) {
                $answer = $answersByQuestionId->get($question->id);
                $text = trim((string) ($answer?->answer ?? ''));
                $items[] = [
                    'question' => $question->text,
                    'answer' => $text !== '' ? $text : self::NO_EVIDENCE_LABEL,
                    'raw_quote' => $answer?->raw_quote,
                    'reviewer_note' => $answer?->reviewer_note,
                    'unreviewed_ai' => ! $reviewed && $answer?->origin === InterviewAnswerOrigin::AiSuggestion,
                ];
            }
            $out[] = [
                'title' => $section->title,
                'items' => $items,
            ];
        }

        return $out;
    }

    private function ensureDompdfWritableDirs(): void
    {
        foreach ([storage_path('fonts'), storage_path('app/dompdf-tmp')] as $dir) {
            File::ensureDirectoryExists($dir);
        }
    }
}
