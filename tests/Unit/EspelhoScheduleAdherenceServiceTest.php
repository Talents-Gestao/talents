<?php

namespace Tests\Unit;

use App\Services\Rhid\EspelhoScheduleAdherenceService;
use App\Services\Rhid\PunchScheduleSettingsService;
use PHPUnit\Framework\TestCase;

class EspelhoScheduleAdherenceServiceTest extends TestCase
{
    private EspelhoScheduleAdherenceService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new EspelhoScheduleAdherenceService(new PunchScheduleSettingsService);
    }

    public function test_diff_minutes(): void
    {
        $this->assertSame(60, $this->svc->diffMinutes('09:00', '08:00'));
        $this->assertSame(-30, $this->svc->diffMinutes('08:00', '08:30'));
        $this->assertNull($this->svc->diffMinutes('xx', '08:00'));
    }

    public function test_analyze_day_detects_entrada_atraso(): void
    {
        $day = [
            'ativo' => true,
            'entrada' => '08:00',
            'saida_almoco' => '12:00',
            'volta_almoco' => '13:00',
            'saida' => '17:00',
        ];
        $frag = [
            'ent_1' => '08:30',
            'sai_1' => '12:00',
            'ent_2' => '13:00',
            'sai_2' => '17:00',
        ];
        $r = $this->svc->analyzeDayFragment($frag, $day, 0);
        $this->assertNotNull($r);
        $this->assertSame(30, $r['atraso_entrada_minutos']);
        $this->assertFalse($r['tem_infracao_almoco']);
    }

    public function test_analyze_day_detects_almoco_curto(): void
    {
        $day = [
            'ativo' => true,
            'entrada' => '08:00',
            'saida_almoco' => '12:00',
            'volta_almoco' => '13:00',
            'saida' => '17:00',
        ];
        $frag = [
            'ent_1' => '08:00',
            'sai_1' => '12:30',
            'ent_2' => '12:45',
            'sai_2' => '17:00',
        ];
        $r = $this->svc->analyzeDayFragment($frag, $day, 0);
        $this->assertNotNull($r);
        $this->assertTrue($r['almoco_curto']);
        $this->assertTrue($r['tem_infracao_almoco']);
    }

    public function test_analyze_day_returns_null_without_four_slots(): void
    {
        $day = [
            'ativo' => true,
            'entrada' => '08:00',
            'saida_almoco' => '12:00',
            'volta_almoco' => '13:00',
            'saida' => '17:00',
        ];
        $frag = [
            'ent_1' => '08:00',
            'sai_1' => '12:00',
            'ent_2' => '',
            'sai_2' => '17:00',
        ];
        $this->assertNull($this->svc->analyzeDayFragment($frag, $day, 0));
    }
}
