<?php

namespace App\Services\Commercial;

use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Support\BrlExtenso;

class ContractPlaceholderService
{
    /**
     * Retorna substituições alinhadas ao PDF da proposta comercial: apenas serviços efetivamente
     * contratados, quantidade de colaboradores nos detalhes e totais da proposta.
     *
     * @return array<string, string>
     */
    public function placeholders(CommercialProposal $proposal): array
    {
        $proposal->loadMissing('seller:id,name,email');
        $settings = CommercialSetting::current();

        $totalCents = (int) $proposal->total_final_cents;
        $totalReais = 'R$ '.number_format($totalCents / 100, 2, ',', '.');

        $lines = CommercialProposalServiceLines::forProposal($proposal);
        $byKey = CommercialProposalServiceLines::indexByKey($lines);

        $servicosLista = $this->buildServicosLista($lines);
        $servicosHtml = $this->buildServicosDetalhadaHtml($lines, $totalCents);
        $servicosListaBulletsHtml = $this->buildServicosListaBulletsHtml($lines);
        $servicosRotulos = $this->buildServicosApenasRotulos($lines);

        $validade = now()->copy()->addDays((int) $settings->pdf_validade_dias);

        $p = $settings->default_prazo_dias;
        $prazoDias = $p !== null && $p !== '' ? (string) $p : '—';

        $clientAddr = trim((string) ($proposal->client_address ?? ''));
        $clientRepr = trim((string) ($proposal->client_representative ?? ''));
        $empReprLine = trim((string) ($settings->company_representative_line ?? ''));
        $empRepr = $empReprLine !== ''
            ? $empReprLine
            : 'neste ato representada na forma de seus documentos societários';

        $forum = trim((string) ($settings->company_forum_city_state ?? ''));
        if ($forum === '') {
            $forum = trim((string) ($settings->company_city_state ?? ''));
        }
        if ($forum === '') {
            $forum = 'Várzea Paulista – SP';
        }

        $validadeDiasNum = max(1, (int) ($settings->pdf_validade_dias ?? 10));

        $emitida = $proposal->created_at;
        $emitidaFmt = $emitida ? $emitida->timezone(config('app.timezone'))->format('d/m/Y') : '—';

        $base = [
            'cliente_nome' => $proposal->client_name ?? '',
            'cliente_cnpj' => $proposal->client_cnpj ?? '',
            'cliente_email' => $proposal->client_email ?? '',
            'cliente_telefone' => $proposal->client_phone ?? '',
            'cliente_endereco' => $clientAddr !== '' ? $clientAddr : '—',
            'cliente_representante' => $clientRepr !== '' ? $clientRepr : '—',
            'numero_funcionarios' => (string) ($proposal->employee_count ?? 0),

            'servicos_lista' => $servicosLista,
            'servicos_lista_html' => $servicosListaBulletsHtml,
            'servicos_rotulos' => $servicosRotulos,
            'servicos_detalhada_html' => $servicosHtml,

            'total_reais' => $totalReais,
            'total_extenso' => BrlExtenso::fromCents($totalCents),

            'empresa_nome' => (string) ($settings->company_name ?? ''),
            'empresa_cnpj' => (string) ($settings->company_cnpj ?? ''),
            'empresa_endereco' => (string) ($settings->company_address ?? ''),
            'empresa_telefone' => (string) ($settings->company_phone ?? ''),
            'empresa_email' => (string) ($settings->company_email ?? ''),
            'empresa_representacao' => $empRepr,
            'cidade_estado' => (string) ($settings->company_city_state ?? ''),
            'foro_comarca' => $forum,
            'forma_pagamento' => (string) ($settings->default_payment_terms ?? ''),
            'prazo_dias' => $prazoDias,
            'validade_proposta_dias' => (string) $validadeDiasNum,

            'vendedor_nome' => $proposal->seller?->name ?? '—',
            'vendedor_email' => $proposal->seller?->email ?? '—',
            'validade_data' => $validade->format('d/m/Y'),
            'data_hoje' => now()->format('d/m/Y'),
            'proposta_codigo' => $proposal->code ?? '',
            'proposta_emitida_em' => $emitidaFmt,
            'proposta_indicacao' => (string) ($proposal->indication ?? ''),
            'proposta_observacoes' => (string) ($proposal->notes ?? ''),

            'comissao_percent' => number_format((float) ($proposal->commission_percent ?? 0), 2, ',', '.'),
            'comissao_reais' => 'R$ '.number_format(((int) ($proposal->commission_cents ?? 0)) / 100, 2, ',', '.'),
        ];

        return array_merge($base, $this->perServicePlaceholders($byKey));
    }

    /**
     * Placeholders por tipo de serviço (Palestra, Consultoria implícita nos blocos, etc.).
     * Serviço não contratado: linhas vazias, "Não", valores "—".
     *
     * @param  array<string, array{key:string, label:string, detail:string, value_cents:int}>  $byKey
     * @return array<string, string>
     */
    private function perServicePlaceholders(array $byKey): array
    {
        $out = [];
        $fmt = fn (int $cents) => 'R$ '.number_format($cents / 100, 2, ',', '.');

        foreach (CommercialProposalServiceLines::SERVICE_KEYS as $key) {
            $label = CommercialProposalServiceLines::labelForKey($key);
            $line = $byKey[$key] ?? null;
            $ativo = $line !== null;
            $detail = $line['detail'] ?? '—';
            $valor = $line !== null ? $fmt((int) $line['value_cents']) : '—';

            $out["svc_ativo_{$key}"] = $ativo ? 'Sim' : 'Não';
            $out["svc_detalhe_{$key}"] = $detail;
            $out["svc_valor_{$key}"] = $valor;
            $out["svc_linha_{$key}"] = $ativo
                ? "{$label}: {$detail} — {$valor}"
                : '';
            $out["svc_bloco_{$key}_html"] = $ativo
                ? '<p style="margin:6px 0;font-size:11px;line-height:1.45;color:#0f172a;">'
                .'<strong style="color:#4a2070;">'.e($label).'</strong><br>'
                .'<span style="color:#64748b;">'.e($detail).'</span><br>'
                .'<span style="font-weight:700;">'.e($valor).'</span>'
                .'</p>'
                : '';
        }

        return $out;
    }

    /**
     * @param  array<int, array{key:string, label:string, detail:string, value_cents:int}>  $lines
     */
    private function buildServicosLista(array $lines): string
    {
        if ($lines === []) {
            return 'Nenhum serviço selecionado nesta proposta.';
        }
        $parts = [];
        foreach ($lines as $line) {
            $brl = 'R$ '.number_format($line['value_cents'] / 100, 2, ',', '.');
            $parts[] = $line['label'].' — '.$line['detail'].' — '.$brl;
        }

        return implode('; ', $parts);
    }

    /**
     * Apenas os nomes dos serviços contratados (sem valores).
     *
     * @param  array<int, array{key:string, label:string, detail:string, value_cents:int}>  $lines
     */
    private function buildServicosApenasRotulos(array $lines): string
    {
        if ($lines === []) {
            return '—';
        }

        return implode(', ', array_map(fn ($l) => $l['label'], $lines));
    }

    /**
     * Lista em HTML com marcadores — só serviços contratados (alinhado ao resumo da proposta).
     *
     * @param  array<int, array{key:string, label:string, detail:string, value_cents:int}>  $lines
     */
    private function buildServicosListaBulletsHtml(array $lines): string
    {
        if ($lines === []) {
            return '<p style="color:#64748b;font-size:11px;">Nenhum serviço selecionado nesta proposta.</p>';
        }
        $brl = fn (int $cents) => 'R$ '.number_format($cents / 100, 2, ',', '.');
        $items = '';
        foreach ($lines as $line) {
            $label = e($line['label']);
            $detail = e($line['detail']);
            $v = e($brl((int) $line['value_cents']));
            $items .= "<li style=\"margin:4px 0;\"><strong>{$label}</strong> — {$detail} — <strong>{$v}</strong></li>";
        }

        return '<ul style="margin:8px 0;padding-left:18px;font-size:11px;color:#0f172a;">'.$items.'</ul>';
    }

    /**
     * Tabela no mesmo espírito do PDF da proposta (serviços + total).
     *
     * @param  array<int, array{key:string, label:string, detail:string, value_cents:int}>  $lines
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

        return '<table class="services" style="width:100%;border-collapse:collapse;margin-top:10px;font-size:11px;page-break-inside:avoid;">'
            .'<thead><tr>'
            .'<th style="text-align:left;border-bottom:1px solid #4a2070;color:#4a2070;font-size:9px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;padding:6px 4px;">Serviço</th>'
            .'<th style="text-align:left;border-bottom:1px solid #4a2070;color:#4a2070;font-size:9px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;padding:6px 4px;">Detalhe</th>'
            .'<th style="text-align:right;border-bottom:1px solid #4a2070;color:#4a2070;font-size:9px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;padding:6px 4px;">Valor</th>'
            .'</tr></thead><tbody>'
            .$rows
            .'<tr><td colspan="2" style="padding-top:10px;font-weight:700;color:#4a2070;border-top:2px solid #4a2070;font-size:14px;">Honorário total</td>'
            ."<td style=\"padding-top:10px;text-align:right;font-weight:700;color:#4a2070;border-top:2px solid #4a2070;font-size:14px;\">{$total}</td></tr>"
            .'</tbody></table>';
    }
}
