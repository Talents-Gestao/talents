<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Rhid;

use App\Support\Rhid\PontoPeopleSnapshot;
use PHPUnit\Framework\TestCase;

class PontoPeopleSnapshotTest extends TestCase
{
    public function test_empty_snapshot_has_no_data(): void
    {
        $snapshot = PontoPeopleSnapshot::empty();

        $this->assertFalse($snapshot['has_data']);
        $this->assertSame(0, $snapshot['evaluated_count']);
        $this->assertNull($snapshot['punctuality_avg_percent']);
        $this->assertSame(0, $snapshot['unjustified_absence_days']);
        $this->assertSame(0, $snapshot['atestado_days']);
        $this->assertSame([], $snapshot['ranking_pontualidade']);
        $this->assertSame([], $snapshot['ranking_faltas']);
        $this->assertSame([], $snapshot['ranking_atestados']);
    }

    public function test_builds_kpis_and_three_rankings(): void
    {
        $snapshot = PontoPeopleSnapshot::fromComplianceRanking([
            [
                'id_person' => 1,
                'nome' => 'Ana Pontual',
                'percentual' => 100,
                'dias_esperados' => 20,
                'dias_cumpridos' => 20,
                'dias_faltas_nao_justificadas' => 0,
                'dias_atestado_excluidos' => 0,
            ],
            [
                'id_person' => 2,
                'nome' => 'Bruno Faltas',
                'percentual' => 50,
                'dias_esperados' => 20,
                'dias_cumpridos' => 10,
                'dias_faltas_nao_justificadas' => 10,
                'dias_atestado_excluidos' => 1,
            ],
            [
                'id_person' => 3,
                'nome' => 'Carla Atestado',
                'percentual' => 80,
                'dias_esperados' => 10,
                'dias_cumpridos' => 8,
                'dias_faltas_nao_justificadas' => 2,
                'dias_atestado_excluidos' => 5,
            ],
        ]);

        $this->assertTrue($snapshot['has_data']);
        $this->assertSame(3, $snapshot['evaluated_count']);
        $this->assertSame(76.7, $snapshot['punctuality_avg_percent']);
        $this->assertSame(12, $snapshot['unjustified_absence_days']);
        $this->assertSame(6, $snapshot['atestado_days']);

        $this->assertSame(
            [1, 3, 2],
            array_column($snapshot['ranking_pontualidade'], 'id_person'),
        );
        $this->assertSame(
            [2, 3],
            array_column($snapshot['ranking_faltas'], 'id_person'),
        );
        $this->assertSame(
            [3, 2],
            array_column($snapshot['ranking_atestados'], 'id_person'),
        );
        $this->assertCount(2, $snapshot['ranking_faltas']);
    }

    public function test_returns_full_rankings_without_display_cap(): void
    {
        $rows = [];
        for ($i = 1; $i <= 12; $i++) {
            $rows[] = [
                'id_person' => $i,
                'nome' => sprintf('Pessoa %02d', $i),
                'percentual' => 100 - $i,
                'dias_esperados' => 10,
                'dias_cumpridos' => 10 - min($i, 9),
                'dias_faltas_nao_justificadas' => $i,
                'dias_atestado_excluidos' => $i,
            ];
        }

        $snapshot = PontoPeopleSnapshot::fromComplianceRanking($rows);

        $this->assertSame(12, $snapshot['evaluated_count']);
        $this->assertCount(12, $snapshot['ranking_pontualidade']);
        $this->assertCount(12, $snapshot['ranking_faltas']);
        $this->assertCount(12, $snapshot['ranking_atestados']);
        $this->assertSame(1, $snapshot['ranking_pontualidade'][0]['id_person']);
        $this->assertSame(12, $snapshot['ranking_faltas'][0]['id_person']);
        $this->assertSame(12, $snapshot['ranking_atestados'][0]['id_person']);
    }

    public function test_derives_faltas_when_field_missing(): void
    {
        $snapshot = PontoPeopleSnapshot::fromComplianceRanking([
            [
                'id_person' => 9,
                'nome' => 'Sem campo',
                'percentual' => 40,
                'dias_esperados' => 10,
                'dias_cumpridos' => 4,
                'dias_atestado_excluidos' => 0,
            ],
        ]);

        $this->assertSame(6, $snapshot['unjustified_absence_days']);
        $this->assertSame(6, $snapshot['ranking_faltas'][0]['dias_faltas_nao_justificadas']);
    }
}
