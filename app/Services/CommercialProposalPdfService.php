<?php

namespace App\Services;

use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Services\Commercial\CommercialProposalServiceLines;
use App\Support\TalentsButterflyDataUri;
use App\Support\TalentsLogoDataUri;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class CommercialProposalPdfService
{
    public function generate(CommercialProposal $proposal): \Barryvdh\DomPDF\PDF
    {
        $proposal->loadMissing('seller:id,name,email');
        $settings = CommercialSetting::current();

        $this->ensureDompdfWritableDirs();

        $fontDir = storage_path('fonts');
        $tempDir = storage_path('app/dompdf-tmp');
        $chroot = realpath(base_path()) ?: base_path();

        $pdf = Pdf::loadView('reports.commercial-proposal', [
            'proposal' => $proposal,
            'settings' => $settings,
            'logoBase64' => TalentsLogoDataUri::get(),
            'butterflyBase64' => TalentsButterflyDataUri::get(),
            'services' => CommercialProposalServiceLines::forProposal($proposal, $settings),
            'validityDate' => now()->copy()->addDays((int) $settings->pdf_validade_dias),
        ]);

        $pdf->setOption('fontDir', $fontDir);
        $pdf->setOption('fontCache', $fontDir);
        $pdf->setOption('tempDir', $tempDir);
        $pdf->setOption('chroot', $chroot);

        return $pdf->setPaper('a4');
    }

    /**
     * DomPDF grava métricas de fonte e imagens temporárias; sem pastas graváveis o render falha (500).
     */
    private function ensureDompdfWritableDirs(): void
    {
        foreach ([storage_path('fonts'), storage_path('app/dompdf-tmp')] as $dir) {
            File::ensureDirectoryExists($dir);
        }
    }
}
