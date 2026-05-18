<?php

namespace App\Services;

use App\Models\CommercialSetting;

/**
 * Reproduz as fórmulas Q–X da aba "Simulador" da planilha
 * `TALENTS - COMERCIAL.xlsx`. Toda matemática é em CENTAVOS para precisão.
 *
 * @see DOCUMENTACAO_PLANILHA.md (seção 4.2)
 */
class CommercialPricingService
{
    /**
     * Calcula o breakdown completo de uma proposta.
     *
     * Inputs esperados (todos opcionais; default = serviço não contratado):
     *   - employee_count (int)                    → coluna B do simulador
     *   - svc_pesquisas (bool)                    → coluna C
     *   - svc_profiler (bool)                     → coluna D
     *   - svc_devolutiva (string|null)            → coluna E ('individual'|'grupo'|null)
     *   - svc_nr1 (bool)                          → coluna F
     *   - svc_nr1_implantacao_modo (string|null)  → coluna G ('online'|'presencial'|null)
     *   - svc_contratacao (bool)                  → coluna H
     *   - svc_contratacao_salario_cents (int)     → coluna I (em centavos!)
     *   - svc_direcionamento (bool)               → coluna J
     *   - svc_palestras (bool)                    → coluna K
     *   - commission_percent (float)              → coluna O (0..100)
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, int|array> retorno em centavos (chaves *_cents)
     */
    public function calculate(array $inputs, ?CommercialSetting $settings = null): array
    {
        $s = $settings ?? CommercialSetting::current();
        $employees = max(0, (int) ($inputs['employee_count'] ?? 0));

        $pesquisas = $this->calcPesquisas($employees, $inputs, $s);
        $profiler = $this->calcProfiler($employees, $inputs, $s);
        $devolutiva = $this->calcDevolutiva($inputs, $s);
        $nr1 = $this->calcNr1($employees, $inputs, $s);
        $nr1Implantacao = $this->calcNr1Implantacao($employees, $inputs, $s, $nr1);
        $contratacao = $this->calcContratacao($employees, $inputs);
        $direcionamento = $this->calcDirecionamento($employees, $inputs, $s);
        $palestras = $this->calcPalestras($employees, $inputs, $s);

        $totalFinal = $pesquisas + $profiler + $devolutiva + $nr1 + $nr1Implantacao
            + $contratacao + $direcionamento + $palestras;

        $commissionPercent = (float) ($inputs['commission_percent'] ?? $s->default_commission_percent ?? 0);
        $commissionCents = (int) round($totalFinal * $commissionPercent / 100);

        return [
            'total_pesquisas_cents' => $pesquisas,
            'total_profiler_cents' => $profiler,
            'total_devolutiva_cents' => $devolutiva,
            'total_nr1_cents' => $nr1,
            'total_nr1_implantacao_cents' => $nr1Implantacao,
            'total_contratacao_cents' => $contratacao,
            'total_direcionamento_cents' => $direcionamento,
            'total_palestras_cents' => $palestras,
            'total_final_cents' => $totalFinal,
            'commission_percent' => $commissionPercent,
            'commission_cents' => $commissionCents,
        ];
    }

    /**
     * Coluna Q — Pesquisas e Organograma:
     *   B × IFS(B≤E5,F5; B≤E6,F6; B≤E7,F7; B>E7,F8)
     */
    private function calcPesquisas(int $employees, array $in, CommercialSetting $s): int
    {
        if (! ($in['svc_pesquisas'] ?? false) || $employees <= 0) {
            return 0;
        }

        return $employees * $this->pickTier(
            $employees,
            [$s->pesquisas_tier1_max, $s->pesquisas_tier2_max, $s->pesquisas_tier3_max],
            [
                $s->pesquisas_tier1_cents,
                $s->pesquisas_tier2_cents,
                $s->pesquisas_tier3_cents,
                $s->pesquisas_tier4_cents,
            ],
        );
    }

    /**
     * Coluna R — Profiler:
     *   B × IFS(B≤A5,B5; B≤A6,B6; B≤A7,B7; B>A7,B8)
     */
    private function calcProfiler(int $employees, array $in, CommercialSetting $s): int
    {
        if (! ($in['svc_profiler'] ?? false) || $employees <= 0) {
            return 0;
        }

        return $employees * $this->pickTier(
            $employees,
            [$s->profiler_tier1_max, $s->profiler_tier2_max, $s->profiler_tier3_max],
            [
                $s->profiler_tier1_cents,
                $s->profiler_tier2_cents,
                $s->profiler_tier3_cents,
                $s->profiler_tier4_cents,
            ],
        );
    }

    /**
     * Coluna S — Devolutiva: valor fixo por modalidade (Individual ou Grupo).
     */
    private function calcDevolutiva(array $in, CommercialSetting $s): int
    {
        $modo = $in['svc_devolutiva'] ?? null;

        return match ($modo) {
            'individual' => (int) $s->devolutiva_individual_cents,
            'grupo' => (int) $s->devolutiva_grupo_cents,
            default => 0,
        };
    }

    /**
     * Coluna T — NR-1 Mapeamento:
     *   B × IFS(B≤E16,F16; B≤E17,F17; B≤E18,F18; B>E18,F19)
     */
    private function calcNr1(int $employees, array $in, CommercialSetting $s): int
    {
        if (! ($in['svc_nr1'] ?? false) || $employees <= 0) {
            return 0;
        }

        return $employees * $this->pickTier(
            $employees,
            [$s->nr1_tier1_max, $s->nr1_tier2_max, $s->nr1_tier3_max],
            [
                $s->nr1_tier1_cents,
                $s->nr1_tier2_cents,
                $s->nr1_tier3_cents,
                $s->nr1_tier4_cents,
            ],
        );
    }

    /**
     * Coluna U — NR-1 Implantação:
     *   IF(F="SIM", IF(G="Presencial", F22, B*F21) + T, "")
     *
     * Observação: na planilha o cálculo soma o valor de Mapeamento (T) ao
     * de Implantação. Replicamos esse comportamento.
     */
    private function calcNr1Implantacao(int $employees, array $in, CommercialSetting $s, int $nr1Mapeamento): int
    {
        if (! ($in['svc_nr1'] ?? false)) {
            return 0;
        }

        $modo = $in['svc_nr1_implantacao_modo'] ?? null;
        if ($modo === null) {
            return 0;
        }

        $implantacao = $modo === 'presencial'
            ? (int) $s->nr1_implantacao_presencial_cents
            : $employees * (int) $s->nr1_implantacao_online_cents;

        return $implantacao + $nr1Mapeamento;
    }

    /**
     * Coluna V — Contratação:
     *   IF(H="Sim", I*B, "")
     */
    private function calcContratacao(int $employees, array $in): int
    {
        if (! ($in['svc_contratacao'] ?? false) || $employees <= 0) {
            return 0;
        }

        $salarioCents = (int) ($in['svc_contratacao_salario_cents'] ?? 0);

        return $salarioCents * $employees;
    }

    /**
     * Coluna W — Direcionamento Estratégico:
     *   B × IFS(B≤A16,B16; B≤A17,B17; B≤A18,B18; B>A18,B19)
     */
    private function calcDirecionamento(int $employees, array $in, CommercialSetting $s): int
    {
        if (! ($in['svc_direcionamento'] ?? false) || $employees <= 0) {
            return 0;
        }

        return $employees * $this->pickTier(
            $employees,
            [$s->direcionamento_tier1_max, $s->direcionamento_tier2_max, $s->direcionamento_tier3_max],
            [
                $s->direcionamento_tier1_cents,
                $s->direcionamento_tier2_cents,
                $s->direcionamento_tier3_cents,
                $s->direcionamento_tier4_cents,
            ],
        );
    }

    /**
     * Coluna X — Palestras e Treinamentos:
     *   IF(B>30, F11*2, F11)
     */
    private function calcPalestras(int $employees, array $in, CommercialSetting $s): int
    {
        if (! ($in['svc_palestras'] ?? false)) {
            return 0;
        }

        $base = (int) $s->palestras_base_cents;
        $threshold = (int) $s->palestras_threshold_funcionarios;
        $multiplier = max(1, (int) $s->palestras_multiplier);

        return $employees > $threshold ? $base * $multiplier : $base;
    }

    /**
     * Implementa o IFS por faixa: até $maxes[0], depois até $maxes[1], etc.
     * O último valor (`$values[count($maxes)]`) é o "acima da última faixa".
     *
     * @param  array<int, int>  $maxes   tetos de cada faixa (3 valores)
     * @param  array<int, int>  $values  valores em centavos (4 valores)
     */
    private function pickTier(int $employees, array $maxes, array $values): int
    {
        foreach ($maxes as $i => $max) {
            if ($employees <= $max) {
                return (int) $values[$i];
            }
        }

        return (int) $values[count($maxes)];
    }
}
