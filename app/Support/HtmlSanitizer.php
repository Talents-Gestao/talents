<?php

namespace App\Support;

class HtmlSanitizer
{
    /**
     * Remove tags e atributos perigosos; mantém formatação básica de parecer/contrato.
     * Uso: campos de rich text simples (TipTap, editor de notas).
     */
    public static function sanitizeRichText(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $allowed = '<p><br><strong><em><u><b><i><h2><h3><h4><ul><ol><li><a><div><span><table><tr><td><th><tbody><thead><tfoot>';

        $clean = strip_tags($html, $allowed);

        // Remove event handlers e javascript: em links
        $clean = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/iu', '', $clean) ?? $clean;

        $trimmed = trim($clean);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Sanitização para HTML de contrato/documento gerado via editor rich text ou DOCX.
     *
     * Preserva toda a estrutura e formatação (tabelas, atributos style, classes CSS inline)
     * necessária para a fidelidade visual do PDF — remove apenas vetores de injeção:
     * tags <script>/<style> globais, event handlers (onX="…") e URIs javascript:.
     *
     * Não usa strip_tags para não destruir o layout do contrato.
     */
    public static function sanitizeContractHtml(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        // Remove blocos <script>…</script> (conteúdo + tag)
        $clean = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/iu', '', $html) ?? $html;

        // Remove blocos <style>…</style> globais (estilos inline em atributos style="" são mantidos)
        $clean = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/iu', '', $clean) ?? $clean;

        // Remove event handlers: on{evento}="..." ou on{evento}='...'
        $clean = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\')/iu', '', $clean) ?? $clean;

        // Remove javascript: em atributos href/src/action
        $clean = preg_replace('/\b(href|src|action)\s*=\s*(["\'])\s*javascript\s*:[^"\']*\2/iu', '$1=$2#$2', $clean) ?? $clean;

        // Remove data: URIs em src (podem embutir scripts via SVG)
        $clean = preg_replace('/\b(src)\s*=\s*(["\'])\s*data:[^"\']*\2/iu', '$1=$2$2', $clean) ?? $clean;

        return $clean;
    }
}
