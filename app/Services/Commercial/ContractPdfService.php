<?php

namespace App\Services\Commercial;

use App\Support\TalentsLogoDataUri;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class ContractPdfService
{
    public function render(string $contentHtml, string $code, ?\DateTimeInterface $generatedAt = null): \Barryvdh\DomPDF\PDF
    {
        $this->ensureDompdfWritableDirs();

        $fontDir = storage_path('fonts');
        $tempDir = storage_path('app/dompdf-tmp');
        $chroot = realpath(base_path()) ?: base_path();

        $pdf = Pdf::loadView('reports.commercial-contract', [
            'content_html' => $contentHtml,
            'code' => $code,
            'generatedAt' => $generatedAt ?? now(),
            'logoBase64' => TalentsLogoDataUri::get(),
        ]);

        $pdf->setOption('fontDir', $fontDir);
        $pdf->setOption('fontCache', $fontDir);
        $pdf->setOption('tempDir', $tempDir);
        $pdf->setOption('chroot', $chroot);

        return $pdf->setPaper('a4');
    }

    public function output(string $contentHtml, string $code, ?\DateTimeInterface $generatedAt = null): string
    {
        return $this->render($contentHtml, $code, $generatedAt)->output();
    }

    private function ensureDompdfWritableDirs(): void
    {
        foreach ([storage_path('fonts'), storage_path('app/dompdf-tmp')] as $dir) {
            File::ensureDirectoryExists($dir);
        }
    }
}
