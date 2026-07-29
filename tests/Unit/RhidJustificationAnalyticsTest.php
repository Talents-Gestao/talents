<?php

namespace Tests\Unit;

use App\Support\RhidJustificationAnalytics;
use PHPUnit\Framework\TestCase;

class RhidJustificationAnalyticsTest extends TestCase
{
    public function test_is_feriado_by_keyword_matches_type_label(): void
    {
        $typeMap = [
            '9' => ['id' => 9, 'name' => 'Feriado Nacional'],
        ];
        $row = ['idJustificationType' => 9, 'justificativa' => ''];

        $this->assertTrue(RhidJustificationAnalytics::isFeriadoByKeyword($row, $typeMap));
        $this->assertFalse(RhidJustificationAnalytics::isAtestadoByKeyword($row, $typeMap));
    }

    public function test_is_feriado_by_keyword_matches_description_text(): void
    {
        $row = [
            'idJustificationType' => 1,
            'justificativa' => 'Compensação de feriado municipal',
        ];
        $typeMap = ['1' => ['id' => 1, 'name' => 'Outros']];

        $this->assertTrue(RhidJustificationAnalytics::isFeriadoByKeyword($row, $typeMap));
    }

    public function test_is_atestado_unchanged(): void
    {
        $typeMap = ['2' => ['id' => 2, 'name' => 'Atestado médico']];
        $row = ['idJustificationType' => 2];

        $this->assertTrue(RhidJustificationAnalytics::isAtestadoByKeyword($row, $typeMap));
        $this->assertFalse(RhidJustificationAnalytics::isFeriadoByKeyword($row, $typeMap));
    }
}
