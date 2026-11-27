<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\HiringAssessmentRating;
use App\Models\HiringApplication;
use App\Models\Interview;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

/**
 * Dossiê da candidatura em PDF (dompdf), no padrão visual do relatório de entrevista.
 * Usa só: identificação do candidato, vaga, avaliação aprovada e a lista das entrevistas da candidatura.
 * Nunca lê anexos internos, protocolos psicológicos nem dados financeiros, criminais ou jurídicos.
 */
final class HiringDossierRenderer
{
    public const METHODOLOGY_NOTE = 'Nota metodológica: a decisão registrada neste dossiê é humana, tomada pela equipe Talents a partir das evidências descritas. A IA não decide nem avalia o candidato; quando usada, apenas sugere respostas a partir da transcrição, sempre revisadas por uma pessoa. Anexos internos, protocolos psicológicos e dados financeiros, criminais ou jurídicos não entram neste dossiê.';

    /**
     * @return array<string, mixed>
     */
    public function data(HiringApplication $application): array
    {
        $application->loadMissing([
            'candidate:id,name,current_role,current_company',
            'vacancy:id,title,company_id,hiring_process_id',
            'vacancy.company:id,name',
            'vacancy.hiringProcess:id,title',
            'stage:id,name',
            'assessment.approvedBy:id,name',
        ]);

        $assessment = $application->assessment;

        $interviews = Interview::query()
            ->where('hiring_application_id', $application->id)
            ->with(['questionnaire:id,name,version', 'reviewedBy:id,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Interview $i) => [
                'date' => ($i->finished_at ?? $i->created_at)?->timezone('America/Sao_Paulo')->format('d/m/Y'),
                'questionnaire' => $i->questionnaire ? $i->questionnaire->name.' (v'.$i->questionnaire->version.')' : '—',
                'source' => $i->source()->label(),
                'reviewed' => $i->isReviewed(),
            ])
            ->all();

        $criteria = array_map(static fn (array $c) => [
            'criterio' => $c['criterio'] ?? '',
            'evidencia' => $c['evidencia'] ?? null,
            'origem' => $c['origem'] ?? null,
            'avaliacao' => HiringAssessmentRating::tryFrom((string) ($c['avaliacao'] ?? ''))?->label() ?? '—',
        ], $assessment?->criteria ?? []);

        return [
            'candidate' => $application->candidate,
            'vacancy' => $application->vacancy,
            'stage' => $application->stage,
            'assessment' => $assessment,
            'criteria' => $criteria,
            'interviews' => $interviews,
            'methodologyNote' => self::METHODOLOGY_NOTE,
            'generatedAt' => now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
        ];
    }

    public function html(HiringApplication $application): string
    {
        return view('admin.contratacao.dossie-pdf', $this->data($application))->render();
    }

    public function pdf(HiringApplication $application): \Barryvdh\DomPDF\PDF
    {
        foreach ([storage_path('fonts'), storage_path('app/dompdf-tmp')] as $dir) {
            File::ensureDirectoryExists($dir);
        }

        $pdf = Pdf::loadView('admin.contratacao.dossie-pdf', $this->data($application));
        $pdf->setOption('fontDir', storage_path('fonts'));
        $pdf->setOption('fontCache', storage_path('fonts'));
        $pdf->setOption('tempDir', storage_path('app/dompdf-tmp'));
        $pdf->setOption('chroot', realpath(base_path()) ?: base_path());

        return $pdf->setPaper('a4');
    }
}
