<?php

namespace App\Services\Commercial;

use App\Models\CommercialProposal;

class CommercialProposalServiceLines
{
    /** Ordem estável (igual ao PDF da proposta). */
    public const SERVICE_KEYS = [
        'pesquisas',
        'profiler',
        'devolutiva',
        'nr1',
        'nr1_implantacao',
        'contratacao',
        'direcionamento',
        'palestras',
    ];

    /**
     * Rótulo fixo por chave (para placeholders quando o serviço não está na proposta).
     */
    public static function labelForKey(string $key): string
    {
        return match ($key) {
            'pesquisas' => 'Pesquisas e Organograma',
            'profiler' => 'Profiler — Diagnóstico Comportamental',
            'devolutiva' => 'Devolutiva e Diagnóstico',
            'nr1' => 'NR-1 — Mapeamento de Risco Psicossocial (12 parcelas)',
            'nr1_implantacao' => 'NR-1 — Implantação',
            'contratacao' => 'Contratação / Recrutamento',
            'direcionamento' => 'Direcionamento Estratégico',
            'palestras' => 'Palestras e Treinamentos',
            default => $key,
        };
    }

    /**
     * @return array<int, array{key:string, label:string, detail:string, value_cents:int}>
     */
    public static function forProposal(CommercialProposal $p): array
    {
        $lines = [];

        if ($p->svc_pesquisas) {
            $lines[] = [
                'key' => 'pesquisas',
                'label' => self::labelForKey('pesquisas'),
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_pesquisas_cents,
            ];
        }

        if ($p->svc_profiler) {
            $lines[] = [
                'key' => 'profiler',
                'label' => self::labelForKey('profiler'),
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_profiler_cents,
            ];
        }

        if ($p->svc_devolutiva) {
            $lines[] = [
                'key' => 'devolutiva',
                'label' => self::labelForKey('devolutiva'),
                'detail' => $p->svc_devolutiva === 'grupo' ? 'Modalidade em grupo' : 'Modalidade individual',
                'value_cents' => (int) $p->total_devolutiva_cents,
            ];
        }

        if ($p->svc_nr1) {
            $lines[] = [
                'key' => 'nr1',
                'label' => self::labelForKey('nr1'),
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_nr1_cents,
            ];
        }

        if ($p->svc_nr1_implantacao_modo) {
            $lines[] = [
                'key' => 'nr1_implantacao',
                'label' => self::labelForKey('nr1_implantacao'),
                'detail' => $p->svc_nr1_implantacao_modo === 'presencial'
                    ? 'Implantação Presencial (taxa única)'
                    : 'Implantação On-line por funcionário',
                'value_cents' => (int) $p->total_nr1_implantacao_cents,
            ];
        }

        if ($p->svc_contratacao) {
            $lines[] = [
                'key' => 'contratacao',
                'label' => self::labelForKey('contratacao'),
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
                'key' => 'direcionamento',
                'label' => self::labelForKey('direcionamento'),
                'detail' => "{$p->employee_count} funcionários",
                'value_cents' => (int) $p->total_direcionamento_cents,
            ];
        }

        if ($p->svc_palestras) {
            $lines[] = [
                'key' => 'palestras',
                'label' => self::labelForKey('palestras'),
                'detail' => $p->employee_count > 30 ? 'Pacote ampliado (acima de 30 funcionários)' : 'Pacote padrão',
                'value_cents' => (int) $p->total_palestras_cents,
            ];
        }

        $p->loadMissing('catalogLines.product');

        foreach ($p->catalogLines as $line) {
            $slug = $line->product?->slug ?? ('produto-'.$line->commercial_product_id);
            $lines[] = [
                'key' => $slug,
                'label' => $line->label_snapshot,
                'detail' => (string) ($line->detail_snapshot ?? ''),
                'value_cents' => (int) $line->total_cents,
            ];
        }

        return $lines;
    }

    /**
     * Chaves de serviço (legado + catálogo) para placeholders de contrato.
     *
     * @return array<int, string>
     */
    public static function allServiceKeysForProposal(CommercialProposal $p): array
    {
        $keys = self::SERVICE_KEYS;
        $p->loadMissing('catalogLines.product');
        foreach ($p->catalogLines as $line) {
            $keys[] = $line->product?->slug ?? ('produto-'.$line->commercial_product_id);
        }

        return array_values(array_unique($keys));
    }

    /**
     * Mapa key => linha para lookups rápidos.
     *
     * @param  array<int, array{key:string, label:string, detail:string, value_cents:int}>  $lines
     * @return array<string, array{key:string, label:string, detail:string, value_cents:int}>
     */
    public static function indexByKey(array $lines): array
    {
        $map = [];
        foreach ($lines as $line) {
            $map[$line['key']] = $line;
        }

        return $map;
    }
}
