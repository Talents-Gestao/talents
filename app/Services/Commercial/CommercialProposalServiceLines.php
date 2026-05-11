<?php

namespace App\Services\Commercial;

use App\Models\CommercialProposal;

class CommercialProposalServiceLines
{
    /**
     * @return array<int, array{label:string, detail:string, value_cents:int}>
     */
    public static function forProposal(CommercialProposal $p): array
    {
        $lines = [];

        if ($p->svc_pesquisas) {
            $lines[] = [
                'label' => 'Pesquisas e Organograma',
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_pesquisas_cents,
            ];
        }

        if ($p->svc_profiler) {
            $lines[] = [
                'label' => 'Profiler — Diagnóstico Comportamental',
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_profiler_cents,
            ];
        }

        if ($p->svc_devolutiva) {
            $lines[] = [
                'label' => 'Devolutiva e Diagnóstico',
                'detail' => $p->svc_devolutiva === 'grupo' ? 'Modalidade em grupo' : 'Modalidade individual',
                'value_cents' => (int) $p->total_devolutiva_cents,
            ];
        }

        if ($p->svc_nr1) {
            $lines[] = [
                'label' => 'NR-1 — Mapeamento de Risco Psicossocial (12 parcelas)',
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_nr1_cents,
            ];
        }

        if ($p->svc_nr1_implantacao_modo) {
            $lines[] = [
                'label' => 'NR-1 — Implantação',
                'detail' => $p->svc_nr1_implantacao_modo === 'presencial'
                    ? 'Implantação Presencial (taxa única)'
                    : 'Implantação On-line por funcionário',
                'value_cents' => (int) $p->total_nr1_implantacao_cents,
            ];
        }

        if ($p->svc_contratacao) {
            $lines[] = [
                'label' => 'Contratação / Recrutamento',
                'detail' => sprintf(
                    'Salário base R$ %s × %d funcionários',
                    number_format(((int) $p->svc_contratacao_salario_cents) / 100, 2, ',', '.'),
                    $p->employee_count,
                ),
                'value_cents' => (int) $p->total_contratacao_cents,
            ];
        }

        if ($p->svc_direcionamento) {
            $lines[] = [
                'label' => 'Direcionamento Estratégico',
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_direcionamento_cents,
            ];
        }

        if ($p->svc_palestras) {
            $lines[] = [
                'label' => 'Palestras e Treinamentos',
                'detail' => $p->employee_count > 30 ? 'Pacote ampliado (acima de 30 funcionários)' : 'Pacote padrão',
                'value_cents' => (int) $p->total_palestras_cents,
            ];
        }

        return $lines;
    }
}
