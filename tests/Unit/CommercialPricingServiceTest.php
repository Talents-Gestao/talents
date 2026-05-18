<?php

namespace Tests\Unit;

use App\Models\CommercialSetting;
use App\Services\CommercialPricingService;
use PHPUnit\Framework\TestCase;

/**
 * Valida as fórmulas Q–X do simulador comercial usando os exemplos
 * exatos da aba "Simulador" da planilha TALENTS - COMERCIAL.xlsx.
 */
class CommercialPricingServiceTest extends TestCase
{
    private function defaultSettings(): CommercialSetting
    {
        return tap(new CommercialSetting(), function (CommercialSetting $s) {
            // Profiler
            $s->profiler_tier1_max = 5;
            $s->profiler_tier1_cents = 32700;
            $s->profiler_tier2_max = 10;
            $s->profiler_tier2_cents = 30500;
            $s->profiler_tier3_max = 20;
            $s->profiler_tier3_cents = 28500;
            $s->profiler_tier4_cents = 26500;
            // Pesquisas
            $s->pesquisas_tier1_max = 10;
            $s->pesquisas_tier1_cents = 12700;
            $s->pesquisas_tier2_max = 20;
            $s->pesquisas_tier2_cents = 12000;
            $s->pesquisas_tier3_max = 30;
            $s->pesquisas_tier3_cents = 11450;
            $s->pesquisas_tier4_cents = 10700;
            // Direcionamento
            $s->direcionamento_tier1_max = 5;
            $s->direcionamento_tier1_cents = 5000;
            $s->direcionamento_tier2_max = 10;
            $s->direcionamento_tier2_cents = 4750;
            $s->direcionamento_tier3_max = 20;
            $s->direcionamento_tier3_cents = 4500;
            $s->direcionamento_tier4_cents = 4250;
            // NR-1
            $s->nr1_tier1_max = 5;
            $s->nr1_tier1_cents = 1700;
            $s->nr1_tier2_max = 10;
            $s->nr1_tier2_cents = 1615;
            $s->nr1_tier3_max = 20;
            $s->nr1_tier3_cents = 1535;
            $s->nr1_tier4_cents = 1500;
            // Devolutiva
            $s->devolutiva_individual_cents = 157700;
            $s->devolutiva_grupo_cents = 210700;
            // NR-1 Implantação
            $s->nr1_implantacao_online_cents = 14000;
            $s->nr1_implantacao_presencial_cents = 421400;
            // Palestras
            $s->palestras_base_cents = 157700;
            $s->palestras_threshold_funcionarios = 30;
            $s->palestras_multiplier = 2;
        });
    }

    /**
     * Empresa "Passeg" (linha 4 da planilha): 1 funcionário, marca Pesquisas,
     * Direcionamento e Palestras. Total esperado: R$ 1.754,00.
     */
    public function test_passeg_example_matches_spreadsheet(): void
    {
        $svc = new CommercialPricingService();
        $r = $svc->calculate([
            'employee_count' => 1,
            'svc_pesquisas' => true,
            'svc_direcionamento' => true,
            'svc_palestras' => true,
        ], $this->defaultSettings());

        $this->assertSame(12700, $r['total_pesquisas_cents']);
        $this->assertSame(5000, $r['total_direcionamento_cents']);
        $this->assertSame(157700, $r['total_palestras_cents']);
        $this->assertSame(0, $r['total_profiler_cents']);
        $this->assertSame(175400, $r['total_final_cents']);
    }

    /**
     * Empresa "2" (linha 5): 2 funcionários, todos os serviços, Devolutiva
     * Individual, NR-1 Implantação On-line, salário R$ 1.000.
     * Total esperado na planilha: R$ 6.510,00.
     */
    public function test_full_services_online_example(): void
    {
        $svc = new CommercialPricingService();
        $r = $svc->calculate([
            'employee_count' => 2,
            'svc_pesquisas' => true,
            'svc_profiler' => true,
            'svc_devolutiva' => 'individual',
            'svc_nr1' => true,
            'svc_nr1_implantacao_modo' => 'online',
            'svc_contratacao' => true,
            'svc_contratacao_salario_cents' => 100000,
            'svc_direcionamento' => true,
            'svc_palestras' => true,
            'commission_percent' => 5,
        ], $this->defaultSettings());

        $this->assertSame(25400, $r['total_pesquisas_cents']);
        $this->assertSame(65400, $r['total_profiler_cents']);
        $this->assertSame(157700, $r['total_devolutiva_cents']);
        $this->assertSame(3400, $r['total_nr1_cents']);
        $this->assertSame(31400, $r['total_nr1_implantacao_cents']); // 2*140 + T(34) = 314
        $this->assertSame(200000, $r['total_contratacao_cents']);
        $this->assertSame(10000, $r['total_direcionamento_cents']);
        $this->assertSame(157700, $r['total_palestras_cents']);
        $this->assertSame(651000, $r['total_final_cents']);
        $this->assertSame((int) round(651000 * 0.05), $r['commission_cents']);
    }

    /**
     * Empresa "3" (linha 6): 3 funcionários, Devolutiva Grupo, NR-1
     * Presencial (R$ 4.214 fixo). Total esperado: R$ 12.512,00.
     */
    public function test_presencial_uses_fixed_implantacao(): void
    {
        $svc = new CommercialPricingService();
        $r = $svc->calculate([
            'employee_count' => 3,
            'svc_pesquisas' => true,
            'svc_profiler' => true,
            'svc_devolutiva' => 'grupo',
            'svc_nr1' => true,
            'svc_nr1_implantacao_modo' => 'presencial',
            'svc_contratacao' => true,
            'svc_contratacao_salario_cents' => 100000,
            'svc_direcionamento' => true,
            'svc_palestras' => true,
        ], $this->defaultSettings());

        $this->assertSame(38100, $r['total_pesquisas_cents']);  // 3 * 127
        $this->assertSame(98100, $r['total_profiler_cents']);   // 3 * 327
        $this->assertSame(210700, $r['total_devolutiva_cents']);
        $this->assertSame(5100, $r['total_nr1_cents']);         // 3 * 17
        $this->assertSame(421400 + 5100, $r['total_nr1_implantacao_cents']);
        $this->assertSame(300000, $r['total_contratacao_cents']);
        $this->assertSame(15000, $r['total_direcionamento_cents']); // 3 * 50
        $this->assertSame(157700, $r['total_palestras_cents']);
        $this->assertSame(1251200, $r['total_final_cents']);
    }

    /**
     * Acima de 30 funcionários, a coluna Palestras dobra.
     */
    public function test_palestras_doubles_above_threshold(): void
    {
        $svc = new CommercialPricingService();
        $r = $svc->calculate([
            'employee_count' => 31,
            'svc_palestras' => true,
        ], $this->defaultSettings());

        $this->assertSame(157700 * 2, $r['total_palestras_cents']);
    }

    /**
     * Sem serviços marcados o total é zero.
     */
    public function test_no_services_returns_zero(): void
    {
        $svc = new CommercialPricingService();
        $r = $svc->calculate(['employee_count' => 50], $this->defaultSettings());

        $this->assertSame(0, $r['total_final_cents']);
        $this->assertSame(0, $r['commission_cents']);
    }

    public function test_commission_uses_settings_default_when_not_in_inputs(): void
    {
        $settings = $this->defaultSettings();
        $settings->default_commission_percent = 10;

        $svc = new CommercialPricingService();
        $r = $svc->calculate([
            'employee_count' => 1,
            'svc_pesquisas' => true,
        ], $settings);

        $this->assertSame(10.0, $r['commission_percent']);
        $this->assertSame(1270, $r['commission_cents']);
    }
}
