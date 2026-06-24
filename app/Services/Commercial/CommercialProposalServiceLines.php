<?php

namespace App\Services\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Support\CommercialProposalPdfDefaults;

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
     * @return array<int, array{key:string, label:string, detail:string, description:string, value_cents:int}>
     */
    public static function forProposal(CommercialProposal $p, ?CommercialSetting $settings = null): array
    {
        $settings = $settings ?? CommercialSetting::current();
        $overrides = $p->service_descriptions ?? [];
        $defaultDescriptions = CommercialProposalPdfDefaults::serviceDescriptionsForSettings(
            $settings->pdf_descricoes_servicos
        );

        $lines = [];

        if ($p->svc_pesquisas) {
            $lines[] = self::line(
                'pesquisas',
                self::labelForKey('pesquisas'),
                "{$p->employee_count} funcionários",
                (int) $p->total_pesquisas_cents,
                $overrides,
                $defaultDescriptions,
            );
        }

        if ($p->svc_profiler) {
            $lines[] = self::line(
                'profiler',
                self::labelForKey('profiler'),
                "{$p->employee_count} funcionários",
                (int) $p->total_profiler_cents,
                $overrides,
                $defaultDescriptions,
            );
        }

        if ($p->svc_devolutiva) {
            $lines[] = self::line(
                'devolutiva',
                self::labelForKey('devolutiva'),
                $p->svc_devolutiva === 'grupo' ? 'Modalidade em grupo' : 'Modalidade individual',
                (int) $p->total_devolutiva_cents,
                $overrides,
                $defaultDescriptions,
            );
        }

        if ($p->svc_nr1) {
            $lines[] = self::line(
                'nr1',
                self::labelForKey('nr1'),
                "{$p->employee_count} funcionários",
                (int) $p->total_nr1_cents,
                $overrides,
                $defaultDescriptions,
            );
        }

        if ($p->svc_nr1_implantacao_modo) {
            $lines[] = self::line(
                'nr1_implantacao',
                self::labelForKey('nr1_implantacao'),
                $p->svc_nr1_implantacao_modo === 'presencial'
                    ? 'Implantação Presencial (taxa única)'
                    : 'Implantação On-line por funcionário',
                (int) $p->total_nr1_implantacao_cents,
                $overrides,
                $defaultDescriptions,
            );
        }

        if ($p->svc_contratacao) {
            $lines[] = self::line(
                'contratacao',
                self::labelForKey('contratacao'),
                sprintf(
                    'Salário base R$ %s × %d funcionários',
                    number_format(((int) $p->svc_contratacao_salario_cents) / 100, 2, ',', '.'),
                    $p->employee_count,
                ),
                (int) $p->total_contratacao_cents,
                $overrides,
                $defaultDescriptions,
            );
        }

        if ($p->svc_direcionamento) {
            $hours = (float) ($p->direcionamento_horas ?? 0);
            $detail = $hours > 0
                ? sprintf(
                    '%s h × R$ %s/hora',
                    rtrim(rtrim(number_format($hours, 2, ',', '.'), '0'), ','),
                    number_format(((int) ($settings->direcionamento_hora_cents ?? $settings->direcionamento_tier1_cents ?? 0)) / 100, 2, ',', '.'),
                )
                : 'Horas não informadas';

            $lines[] = self::line(
                'direcionamento',
                self::labelForKey('direcionamento'),
                $detail,
                (int) $p->total_direcionamento_cents,
                $overrides,
                $defaultDescriptions,
            );
        }

        if ($p->svc_palestras) {
            $lines[] = self::line(
                'palestras',
                self::labelForKey('palestras'),
                $p->employee_count > 30 ? 'Pacote ampliado (acima de 30 funcionários)' : 'Pacote padrão',
                (int) $p->total_palestras_cents,
                $overrides,
                $defaultDescriptions,
            );
        }

        $p->loadMissing('catalogLines.product');

        foreach ($p->catalogLines as $line) {
            $slug = $line->product?->slug ?? ('produto-'.$line->commercial_product_id);
            $subtotalCents = (int) ($line->options['subtotal_cents'] ?? $line->total_cents);
            $valueCents = (int) $line->total_cents;
            $discountCents = max(0, $subtotalCents - $valueCents);

            $lines[] = [
                'key' => $slug,
                'label' => $line->label_snapshot,
                'detail' => (string) ($line->detail_snapshot ?? ''),
                'description' => self::resolveDescription(
                    $slug,
                    $overrides,
                    $defaultDescriptions,
                    (string) ($line->product?->description ?? ''),
                ),
                'value_cents' => $valueCents,
                'subtotal_cents' => $subtotalCents,
                'discount_cents' => $discountCents,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, string|null>  $overrides
     * @param  array<string, string>  $defaultDescriptions
     * @return array{key:string, label:string, detail:string, description:string, value_cents:int}
     */
    private static function line(
        string $key,
        string $label,
        string $detail,
        int $valueCents,
        array $overrides,
        array $defaultDescriptions,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'detail' => $detail,
            'description' => self::resolveDescription($key, $overrides, $defaultDescriptions),
            'value_cents' => $valueCents,
        ];
    }

    /**
     * @param  array<string, string|null>  $overrides
     * @param  array<string, string>  $defaultDescriptions
     */
    public static function resolveDescription(
        string $key,
        array $overrides,
        array $defaultDescriptions,
        string $catalogFallback = '',
    ): string {
        if (array_key_exists($key, $overrides) && filled($overrides[$key])) {
            return (string) $overrides[$key];
        }

        if (isset($defaultDescriptions[$key]) && filled($defaultDescriptions[$key])) {
            return (string) $defaultDescriptions[$key];
        }

        return $catalogFallback;
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
     * @param  array<int, array{key:string, label:string, detail:string, description:string, value_cents:int}>  $lines
     * @return array<string, array{key:string, label:string, detail:string, description:string, value_cents:int}>
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
