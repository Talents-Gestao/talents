<?php

namespace App\Services\Commercial;

use App\Models\CommercialContract;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContractGenerationService
{
    public function __construct(
        private readonly DocxToHtmlService $docxToHtml,
        private readonly ContractPlaceholderService $placeholders,
        private readonly ContractPdfService $contractPdf,
    ) {}

    public function generate(
        CommercialContractTemplate $template,
        CommercialProposal $proposal,
        ?User $generator = null,
    ): CommercialContract {
        return DB::transaction(function () use ($template, $proposal, $generator) {
            $proposal->refresh();

            $baseHtml = match ($template->source_type) {
                'html' => (string) ($template->body_html ?? ''),
                'docx' => $this->docxToHtml->extract((string) $template->docx_path),
                default => throw new \InvalidArgumentException('Tipo de modelo inválido.'),
            };

            $map = $this->placeholders->placeholders($proposal);
            $merged = $this->applyPlaceholders($baseHtml, $map);

            $code = CommercialContract::nextCode();
            $year = now()->format('Y');
            $dir = "contracts/{$year}";
            Storage::disk('local')->makeDirectory($dir);
            $relativePdf = "{$dir}/{$code}.pdf";

            $pdfBinary = $this->contractPdf->output($merged, $code, now());
            Storage::disk('local')->put($relativePdf, $pdfBinary);

            return CommercialContract::create([
                'code' => $code,
                'proposal_id' => $proposal->id,
                'template_id' => $template->id,
                'template_name_snapshot' => $template->name,
                'pdf_path' => $relativePdf,
                'html_snapshot' => $merged,
                'generated_by' => $generator?->id,
                'generated_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, string>  $map
     */
    private function applyPlaceholders(string $html, array $map): string
    {
        $keys = array_keys($map);
        usort($keys, fn ($a, $b) => strlen($b) <=> strlen($a));

        $search = [];
        $replace = [];
        foreach ($keys as $key) {
            $token = '{{'.$key.'}}';
            $val = $map[$key];
            // Fragmentos HTML (tabelas, listas, blocos por serviço): não escapar de novo
            if (str_ends_with($key, '_html')) {
                $search[] = $token;
                $replace[] = $val;
            } else {
                $search[] = $token;
                $replace[] = e($val);
            }
        }

        return str_replace($search, $replace, $html);
    }
}
