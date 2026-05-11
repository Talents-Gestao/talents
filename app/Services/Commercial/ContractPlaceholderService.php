<?php

namespace App\Services\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Support\BrlExtenso;

class ContractPlaceholderService
{
    /**
     * @return array<string, string>
     */
    public function placeholders(CommercialProposal $proposal): array
    {
        $proposal->loadMissing('seller:id,name,email');
        $settings = CommercialSetting::current();

        $totalCents = (int) $proposal->total_final_cents;
        $totalReais = 'R$ '.number_format($totalCents / 100, 2, ',', '.');

        $lines = CommercialProposalServiceLines::forProposal($proposal);
        $servicosLista = $this->buildServicosLista($lines);
        $servicosHtml = $this->buildServicosDetalhadaHtml($lines, $totalCents);

        $validade = now()->copy()->addDays((int) $settings->pdf_validade_dias);

        $p = $settings->default_prazo_dias;
        $prazoDias = $p !== null && $p !== '' ? (string) $p : '—';

        return [
            'cliente_nome' => $proposal->client_name ?? '',
            'cliente_cnpj' => $proposal->client_cnpj ?? '',
            'cliente_email' => $proposal->client_email ?? '',
            'cliente_telefone' => $proposal->client_phone ?? '',
            'cliente_endereco' => '—',
            'numero_funcionarios' => (string) ($proposal->employee_count ?? 0),

            'servicos_lista' => $servicosLista,
            'servicos_detalhada_html' => $servicosHtml,

            'total_reais' => $totalReais,
            'total_extenso' => BrlExtenso::fromCents($totalCents),

            'empresa_nome' => (string) ($settings->company_name ?? ''),
            'empresa_cnpj' => (string) ($settings->company_cnpj ?? ''),
            'empresa_endereco' => (string) ($settings->company_address ?? ''),
            'empresa_telefone' => (string) ($settings->company_phone ?? ''),
            'empresa_email' => (string) ($settings->company_email ?? ''),
            'cidade_estado' => (string) ($settings->company_city_state ?? ''),
            'forma_pagamento' => (string) ($settings->default_payment_terms ?? ''),
            'prazo_dias' => $prazoDias,

            'vendedor_nome' => $proposal->seller?->name ?? '—',
            'validade_data' => $validade->format('d/m/Y'),
            'data_hoje' => now()->format('d/m/Y'),
            'proposta_codigo' => $proposal->code ?? '',
        ];
    }

    /**
     * @param  array<int, array{label:string, detail:string, value_cents:int}>  $lines
     */
    private function buildServicosLista(array $lines): string
    {
        if ($lines === []) {
            return 'Nenhum serviço selecionado.';
        }
        $parts = [];
        foreach ($lines as $line) {
            $brl = 'R$ '.number_format($line['value_cents'] / 100, 2, ',', '.');
            $parts[] = $line['label'].' — '.$brl;
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<int, array{label:string, detail:string, value_cents:int}>  $lines
     */
    private function buildServicosDetalhadaHtml(array $lines, int $totalFinalCents): string
    {
        if ($lines === []) {
            return '<p style="color:#64748b;font-size:11px;">Nenhum serviço selecionado nesta proposta.</p>';
        }

        $brl = fn (int $cents) => 'R$ '.number_format($cents / 100, 2, ',', '.');

        $rows = '';
        foreach ($lines as $line) {
            $label = e($line['label']);
            $detail = e($line['detail']);
            $val = e($brl($line['value_cents']));
            $rows .= "<tr><td style=\"padding:7px 4px;border-bottom:1px solid #f1f5f9;\">{$label}</td>"
                ."<td style=\"padding:7px 4px;border-bottom:1px solid #f1f5f9;color:#64748b;font-size:11px;\">{$detail}</td>"
                ."<td style=\"padding:7px 4px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:600;\">{$val}</td></tr>";
        }

        $total = e($brl($totalFinalCents));

        return '<table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:11px;">'
            .'<thead><tr>'
            .'<th style="text-align:left;border-bottom:1px solid #4a2070;color:#4a2070;font-size:9px;text-transform:uppercase;padding:6px 4px;">Serviço</th>'
            .'<th style="text-align:left;border-bottom:1px solid #4a2070;color:#4a2070;font-size:9px;text-transform:uppercase;padding:6px 4px;">Detalhe</th>'
            .'<th style="text-align:right;border-bottom:1px solid #4a2070;color:#4a2070;font-size:9px;text-transform:uppercase;padding:6px 4px;">Valor</th>'
            .'</tr></thead><tbody>'
            .$rows
            .'<tr><td colspan="2" style="padding-top:10px;font-weight:700;color:#4a2070;border-top:2px solid #4a2070;">Honorário total</td>'
            ."<td style=\"padding-top:10px;text-align:right;font-weight:700;color:#4a2070;border-top:2px solid #4a2070;font-size:14px;\">{$total}</td></tr>"
            .'</tbody></table>';
    }
}
