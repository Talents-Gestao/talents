<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\CanonicalJson;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Snapshots emitidos antes da correção do CanonicalJson (decimal inteiro 39.0 -> 39) precisam gerar o mesmo hash.
 * Os fixtures em tests/Fixtures/canonical_json guardam o snapshot como os construtores o emitiam (resumo executivo e
 * subsídio AEP/PGR de campanha anterior à V11) e os hashes calculados pela classe ANTIGA.
 */
class CanonicalJsonLegacyHashTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function fixtures(): array
    {
        return [
            'resumo executivo' => ['executive_brief'],
            'subsidio AEP/PGR' => ['pgr_subsidy'],
        ];
    }

    #[DataProvider('fixtures')]
    public function test_legacy_snapshots_keep_their_hashes(string $name): void
    {
        $fixture = json_decode((string) file_get_contents(base_path("tests/Fixtures/canonical_json/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);

        // Como o construtor emitia (decimais preservados) e como o banco devolve (JSON gravado e relido).
        $emitted = json_decode($fixture['snapshot_json'], true, flags: JSON_THROW_ON_ERROR);
        $stored = json_decode($fixture['stored_json'], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($fixture['old_snapshot_sha256'], CanonicalJson::sha256($emitted));
        $this->assertSame($fixture['old_snapshot_sha256'], CanonicalJson::sha256($stored));
        $this->assertSame($fixture['old_content_sha256'], CanonicalJson::sha256(['snapshot' => $emitted, 'validation_block' => null]));
        $this->assertSame($fixture['old_content_sha256'], CanonicalJson::sha256(['snapshot' => $stored, 'validation_block' => null]));
    }
}
