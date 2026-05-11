<?php

namespace App\Services;

use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
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
            'logoBase64' => $this->logoBase64(),
            'services' => $this->buildServiceLines($proposal),
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

    /**
     * Monta as linhas de serviços contratados com label, detalhe e valor em centavos.
     *
     * @return array<int, array{label:string, detail:string, value_cents:int}>
     */
    private function buildServiceLines(CommercialProposal $p): array
    {
        $lines = [];
        $emp = max(1, (int) $p->employee_count);

        if ($p->svc_pesquisas) {
            $lines[] = [
                'label' => 'Pesquisas e Organograma',
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_pesquisas_cents,
            ];
        }

        if ($p->svc_profiler) {
            $lines[] = [
                'label' => 'Profiler — Diagnóstico Comportamental',
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_profiler_cents,
            ];
        }

        if ($p->svc_devolutiva) {
            $lines[] = [
                'label' => 'Devolutiva e Diagnóstico',
                'detail' => $p->svc_devolutiva === 'grupo' ? 'Modalidade em grupo' : 'Modalidade individual',
                'value_cents' => (int) $p->total_devolutiva_cents,
            ];
        }

        if ($p->svc_nr1) {
            $lines[] = [
                'label' => 'NR-1 — Mapeamento de Risco Psicossocial (12 parcelas)',
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_nr1_cents,
            ];
        }

        if ($p->svc_nr1_implantacao_modo) {
            $lines[] = [
                'label' => 'NR-1 — Implantação',
                'detail' => $p->svc_nr1_implantacao_modo === 'presencial'
                    ? 'Implantação Presencial (taxa única)'
                    : 'Implantação On-line por funcionário',
                'value_cents' => (int) $p->total_nr1_implantacao_cents,
            ];
        }

        if ($p->svc_contratacao) {
            $lines[] = [
                'label' => 'Contratação / Recrutamento',
                'detail' => sprintf(
                    'Salário base R$ %s × %d funcionários',
                    number_format(((int) $p->svc_contratacao_salario_cents) / 100, 2, ',', '.'),
                    $p->employee_count,
                ),
                'value_cents' => (int) $p->total_contratacao_cents,
            ];
        }

        if ($p->svc_direcionamento) {
            $lines[] = [
                'label' => 'Direcionamento Estratégico',
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_direcionamento_cents,
            ];
        }

        if ($p->svc_palestras) {
            $lines[] = [
                'label' => 'Palestras e Treinamentos',
                'detail' => $p->employee_count > 30 ? 'Pacote ampliado (acima de 30 funcionários)' : 'Pacote padrão',
                'value_cents' => (int) $p->total_palestras_cents,
            ];
        }

        return $lines;
    }

    /**
     * Carrega o logo da raiz do repositório como data URI para o DOMPDF.
     */
    private function logoBase64(): ?string
    {
        $candidates = [
            base_path('logo.png'),
            base_path('../logo.png'),
            public_path('images/logo.png'),
            public_path('logo.png'),
        ];

        foreach ($candidates as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $raw = @file_get_contents($path);
            if ($raw === false || $raw === '') {
                continue;
            }
            $info = @getimagesizefromstring($raw);
            if ($info === false) {
                continue;
            }
            $mime = $info['mime'] ?? 'image/png';
            if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
                continue;
            }

            return 'data:'.$mime.';base64,'.base64_encode($raw);
        }

        return null;
    }
}
