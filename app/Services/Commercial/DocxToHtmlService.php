<?php

namespace App\Services\Commercial;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Writer\HTML;

class DocxToHtmlService
{
    /**
     * Extrai HTML do corpo a partir de um .docx armazenado no disco local (path relativo a storage/app).
     */
    public function extract(string $relativePath): string
    {
        $full = Storage::disk('local')->path($relativePath);
        if (! is_readable($full)) {
            throw new \RuntimeException('Arquivo DOCX não encontrado.');
        }

        $phpWord = IOFactory::load($full);
        $tmp = tempnam(sys_get_temp_dir(), 'docxhtml');
        if ($tmp === false) {
            throw new \RuntimeException('Não foi possível criar arquivo temporário.');
        }

        try {
            $writer = new HTML($phpWord);
            $writer->save($tmp);
            $html = (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        return $this->stripToBodyInner($html);
    }

    private function stripToBodyInner(string $html): string
    {
        if (preg_match('~<body[^>]*>(.*?)</body>~is', $html, $m)) {
            return trim($m[1]);
        }

        return trim(strip_tags($html, '<table><tr><td><th><tbody><thead><tfoot><p><br><span><strong><em><u><ol><ul><li><h1><h2><h3><h4><a><div><b><i>'));
    }
}
