<?php

declare(strict_types=1);

namespace App\Support;

/**
 * JSON canônico para hash: chaves de objeto em ordem alfabética, listas na ordem original,
 * sem escapar barras nem Unicode. O mesmo conteúdo sempre gera os mesmos bytes.
 */
final class CanonicalJson
{
    /**
     * @param  array<mixed>  $data
     */
    public static function encode(array $data): string
    {
        return json_encode(self::normalize($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function sha256(array $data): string
    {
        return hash('sha256', self::encode($data));
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = array_map(self::normalize(...), $value);

        if (! array_is_list($normalized)) {
            ksort($normalized, SORT_STRING);
        }

        return $normalized;
    }
}
