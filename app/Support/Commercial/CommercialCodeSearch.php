<?php

declare(strict_types=1);

namespace App\Support\Commercial;

/**
 * Normalização leve para busca por código comercial (PROP-/VENDA-/CONTR-…).
 */
final class CommercialCodeSearch
{
    /**
     * Remove espaços e prefixo «#» da UI resumida (ex.: «#25» → «25»).
     */
    public static function normalizeTerm(string $term): string
    {
        $term = trim($term);
        if ($term !== '' && str_starts_with($term, '#')) {
            $term = ltrim(substr($term, 1));
        }

        return $term;
    }
}
