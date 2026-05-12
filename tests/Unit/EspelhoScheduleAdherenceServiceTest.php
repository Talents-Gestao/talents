<?php

namespace Tests\Unit;

use App\Services\Rhid\EspelhoScheduleAdherenceService;
use App\Services\Rhid\PunchScheduleSettingsService;
use App\Services\Rhid\RhidPersonSchedulePreferenceService;
use PHPUnit\Framework\TestCase;

class EspelhoScheduleAdherenceServiceTest extends TestCase
{
    private EspelhoScheduleAdherenceService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new EspelhoScheduleAdherenceService(
            new PunchScheduleSettingsService,
            new RhidPersonSchedulePreferenceService,
        );
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

    public function test_analyze_day_uses_second_lunch_when_configured(): void
    {
        $day = [
            'ativo' => true,
            'entrada' => '08:00',
            'saida_almoco' => '11:30',
            'volta_almoco' => '12:30',
            'almoco2_inicio' => '13:00',
            'almoco2_fim' => '14:00',
            'saida' => '18:00',
        ];
        $settings = ['segundo_almoco' => true];
        $frag = [
            'ent_1' => '08:00',
            'sai_1' => '13:00',
            'ent_2' => '14:00',
            'sai_2' => '18:00',
        ];
        $r = $this->svc->analyzeDayFragment($frag, $day, 0, $settings, true);
        $this->assertNotNull($r);
        $this->assertSame(0, $r['atraso_saida_almoco_minutos']);
        $this->assertSame(0, $r['atraso_volta_almoco_minutos']);
        $this->assertFalse($r['tem_infracao_almoco']);
        $this->assertSame(2, $r['lunch_interval_used']);
    }

    /**
     * Caso real do cliente: 1º intervalo 11:30-12:30, 2º intervalo 12:30-13:30,
     * marcações claramente no 2º (sai_1=13:05, ent_2=13:56). Sem preferencia manual.
     * Com auto-detect, NAO deve aplicar o 1º intervalo (que daria ~150 min de falso atraso).
     */
    public function test_analyze_day_auto_detects_second_lunch_when_marks_are_closer(): void
    {
        $day = [
            'ativo' => true,
            'entrada' => '07:30',
            'saida_almoco' => '11:30',
            'volta_almoco' => '12:30',
            'almoco2_inicio' => '12:30',
            'almoco2_fim' => '13:30',
            'saida' => '17:30',
        ];
        $settings = ['segundo_almoco' => true];
        $frag = [
            'ent_1' => '07:31',
            'sai_1' => '13:05',
            'ent_2' => '13:56',
            'sai_2' => '17:39',
        ];

        // Preferencia manual = false (1º), MAS allowAutoDetect=false → mantem 1º (falso atraso enorme)
        $r1 = $this->svc->analyzeDayFragment($frag, $day, 15, $settings, false, false);
        $this->assertNotNull($r1);
        $this->assertSame(1, $r1['lunch_interval_used']);
        $this->assertGreaterThan(60, $r1['atraso_saida_almoco_minutos']);

        // Auto-detect ativo → escolhe 2º (mais proximo), atrasos pequenos
        $r2 = $this->svc->analyzeDayFragment($frag, $day, 15, $settings, false, true);
        $this->assertNotNull($r2);
        $this->assertSame(2, $r2['lunch_interval_used']);
        $this->assertLessThanOrEqual(30, $r2['atraso_saida_almoco_minutos']);
        $this->assertLessThanOrEqual(30, $r2['atraso_volta_almoco_minutos']);
    }

    public function test_auto_detect_picks_first_lunch_when_closer(): void
    {
        $day = [
            'ativo' => true,
            'entrada' => '08:00',
            'saida_almoco' => '12:00',
            'volta_almoco' => '13:00',
            'almoco2_inicio' => '14:00',
            'almoco2_fim' => '15:00',
            'saida' => '18:00',
        ];
        $settings = ['segundo_almoco' => true];
        $frag = [
            'ent_1' => '08:00',
            'sai_1' => '12:02',
            'ent_2' => '13:01',
            'sai_2' => '18:00',
        ];
        $r = $this->svc->analyzeDayFragment($frag, $day, 15, $settings, false, true);
        $this->assertNotNull($r);
        $this->assertSame(1, $r['lunch_interval_used']);
        $this->assertSame(0, $r['atraso_saida_almoco_minutos']);
        $this->assertSame(0, $r['atraso_volta_almoco_minutos']);
    }
}
