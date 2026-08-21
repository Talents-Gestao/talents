<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Commercial;

use App\Support\Commercial\CommercialCodeSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommercialCodeSearchTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function test_normalize_term(string $input, string $expected): void
    {
        $this->assertSame($expected, CommercialCodeSearch::normalizeTerm($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'plain' => ['PROP-2026-0025', 'PROP-2026-0025'],
            'hash short' => ['#25', '25'],
            'hash padded' => ['#0025', '0025'],
            'trim' => ['  25  ', '25'],
            'empty hash' => ['#', ''],
        ];
    }
}
