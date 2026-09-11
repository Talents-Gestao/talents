<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

final class TechnicalOpinionDisclaimerStripper
{
    public static function strip(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        $stripped = str_contains($trimmed, '<')
            ? self::stripHtml($trimmed)
            : self::stripMarkdown($trimmed);

        $stripped = trim(preg_replace('/(\s*<p[^>]*>\s*&nbsp;\s*<\/p>\s*)+$/iu', '', $stripped) ?? $stripped);
        $stripped = trim(preg_replace('/(\s*<p[^>]*>\s*<br\s*\/?>\s*<\/p>\s*)+$/iu', '', $stripped) ?? $stripped);

        return $stripped === '' ? null : $stripped;
    }

    private static function stripHtml(string $html): string
    {
        $previous = libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="talents-opinion-root">'.$html.'</div>',
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $rootQuery = $xpath->query('//*[@id="talents-opinion-root"]');
        $root = $rootQuery?->item(0);
        if (! $root instanceof DOMElement) {
            return $html;
        }

        self::removeDisclaimerSections($root);
        self::removeDisclaimerNodes($root);

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return trim($inner);
    }

    private static function removeDisclaimerSections(DOMElement $root): void
    {
        $headings = [];
        $xpath = new DOMXPath($root->ownerDocument);
        foreach ($xpath->query('.//h1|.//h2|.//h3|.//h4', $root) ?: [] as $heading) {
            if ($heading instanceof DOMElement) {
                $headings[] = $heading;
            }
        }

        foreach ($headings as $heading) {
            if (! self::isDisclaimerHeading($heading->textContent ?? '')) {
                continue;
            }

            $toRemove = [$heading];
            $sibling = $heading->nextSibling;
            while ($sibling instanceof DOMNode) {
                $next = $sibling->nextSibling;
                if ($sibling instanceof DOMElement && preg_match('/^h[1-4]$/i', $sibling->nodeName) === 1) {
                    break;
                }
                $toRemove[] = $sibling;
                $sibling = $next;
            }

            foreach ($toRemove as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private static function removeDisclaimerNodes(DOMElement $root): void
    {
        $xpath = new DOMXPath($root->ownerDocument);
        $nodes = $xpath->query('.//p|.//li|.//blockquote', $root);
        if ($nodes === false) {
            return;
        }

        $toRemove = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement && self::looksLikeDisclaimer($node->textContent ?? '')) {
                $toRemove[] = $node;
            }
        }

        foreach ($toRemove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private static function stripMarkdown(string $text): string
    {
        $withoutHeadingBlock = preg_replace(
            '/^#{1,6}\s*disclaimer\s*:?\s*$.*/imsu',
            '',
            $text
        ) ?? $text;

        $withoutInline = preg_replace(
            '/^\s*\*{0,2}disclaimer\*{0,2}\s*:?\s+.+$/imu',
            '',
            $withoutHeadingBlock
        ) ?? $withoutHeadingBlock;

        $paragraphs = preg_split("/\n{2,}/", $withoutInline) ?: [];
        $kept = [];
        foreach ($paragraphs as $paragraph) {
            if (self::looksLikeDisclaimer($paragraph) || self::isDisclaimerHeading($paragraph)) {
                continue;
            }
            $kept[] = $paragraph;
        }

        return trim(implode("\n\n", $kept));
    }

    private static function isDisclaimerHeading(string $text): bool
    {
        $normalized = self::normalize($text);

        return $normalized === 'disclaimer'
            || $normalized === 'aviso legal'
            || str_starts_with($normalized, 'disclaimer ');
    }

    private static function looksLikeDisclaimer(string $text): bool
    {
        $normalized = self::normalize($text);

        if ($normalized === '') {
            return false;
        }

        if (str_contains($normalized, 'disclaimer')) {
            return true;
        }

        $hasSupport = str_contains($normalized, 'apoio a gestao de riscos')
            || str_contains($normalized, 'apoio a decisao');

        $hasLegal = str_contains($normalized, 'obrigacoes legais')
            || str_contains($normalized, 'nao dispensa')
            || str_contains($normalized, 'nao substitui avaliacao')
            || str_contains($normalized, 'equipe tecnica competente')
            || str_contains($normalized, 'profissionais habilitados');

        return $hasSupport && $hasLegal;
    }

    private static function normalize(string $text): string
    {
        $ascii = Str::ascii(mb_strtolower(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return trim(preg_replace('/\s+/u', ' ', $ascii) ?? $ascii);
    }
}
