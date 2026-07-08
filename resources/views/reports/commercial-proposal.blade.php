<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Proposta Comercial — {{ $proposal->code }}</title>
    <style>
        @page {
            margin: 8mm 10mm 10mm 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e1e1e;
            line-height: 1.5;
            margin: 0;
            padding: 24mm 10mm 42mm;
        }

        /* Moldura roxa em todas as páginas */
        .page-frame {
            position: fixed;
            top: 5mm;
            left: 5mm;
            right: 5mm;
            bottom: 5mm;
            border: 1.5px solid #4a2070;
            z-index: 0;
        }

        /* Logo centralizado em todas as páginas */
        .header-fixed {
            position: fixed;
            top: 9mm;
            left: 0;
            right: 0;
            text-align: center;
            z-index: 5;
        }

        .header-fixed img {
            max-height: 46px;
            width: auto;
            display: inline-block;
        }

        /* Borboleta decorativa */
        .butterfly-decor {
            position: fixed;
            bottom: 22mm;
            right: 10mm;
            z-index: 2;
        }

        .butterfly-decor img {
            width: 72px;
            height: auto;
            opacity: 0.45;
        }

        .doc-main {
            position: relative;
            z-index: 3;
        }

        /* Metadados discretos (1ª página) */
        .meta-inline {
            font-size: 8px;
            color: #64748b;
            text-align: right;
            margin: 0 0 10px;
            line-height: 1.4;
        }

        .meta-inline span {
            margin-left: 10px;
        }

        h1 {
            font-size: 16px;
            font-weight: 700;
            color: #4a2070;
            margin: 0 0 4px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            line-height: 1.2;
        }

        .subtitle {
            font-size: 12px;
            font-weight: 700;
            color: #1e1e1e;
            margin: 0 0 10px;
            line-height: 1.35;
        }

        .company-line,
        .client-line {
            font-size: 11px;
            color: #1e1e1e;
            margin: 0 0 2px;
        }

        .client-line strong {
            font-weight: 700;
        }

        .section-divider {
            border: none;
            border-top: 1px solid #cbd5e1;
            margin: 14px 0;
        }

        h2 {
            font-size: 11px;
            color: #1e1e1e;
            margin: 0 0 4px;
            padding: 0;
            border: none;
            text-transform: none;
            letter-spacing: normal;
            font-weight: 700;
        }

        h3.service-title {
            font-size: 11px;
            color: #1e1e1e;
            margin: 14px 0 4px;
            font-weight: 700;
        }

        .muted {
            color: #64748b;
            font-size: 11px;
            line-height: 1.45;
        }

        .section-text {
            font-size: 11px;
            color: #1e1e1e;
            line-height: 1.5;
            margin: 0 0 0;
        }

        .investment {
            font-size: 11px;
            color: #1e1e1e;
            margin: 4px 0;
        }

        .investment-original {
            text-decoration: line-through;
            color: #94a3b8;
            margin-right: 6px;
        }

        .investment-discount {
            font-size: 11px;
            color: #047857;
            margin: 2px 0;
        }

        .investment-final {
            font-size: 11px;
            color: #1e1e1e;
            font-weight: bold;
            margin: 2px 0 4px;
        }

        .service-detail {
            font-size: 11px;
            color: #1e1e1e;
            margin: 0 0 2px;
        }

        .service-block {
            page-break-inside: avoid;
            margin-bottom: 2px;
        }

        .desc-bullets {
            margin: 4px 0 6px 16px;
            padding: 0;
            font-size: 11px;
            color: #1e1e1e;
            line-height: 1.45;
            list-style-type: disc;
        }

        .desc-bullets li {
            margin-bottom: 3px;
        }

        .desc-bullets ul {
            margin: 3px 0 3px 14px;
            padding: 0;
            list-style-type: circle;
        }

        .desc-paragraph {
            margin: 4px 0 6px;
            font-size: 11px;
            color: #1e1e1e;
            line-height: 1.45;
        }

        .desc-paragraph strong {
            font-weight: 700;
        }

        table.services {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 11px;
            page-break-inside: avoid;
        }

        table.services th,
        table.services td {
            border-bottom: 1px solid #e2e8f0;
            padding: 6px 4px;
            text-align: left;
        }

        table.services th {
            background: transparent;
            color: #4a2070;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border-bottom: 1px solid #4a2070;
        }

        table.services td.value,
        table.services th.value {
            text-align: right;
        }

        table.services tr.total td {
            background: transparent;
            font-weight: 700;
            font-size: 12px;
            color: #4a2070;
            border-top: 2px solid #4a2070;
            border-bottom: none;
            padding-top: 8px;
        }

        .commission-inline {
            margin-top: 10px;
            font-size: 11px;
            color: #475569;
            line-height: 1.45;
        }

        .closing-text {
            margin-top: 12px;
            font-size: 11px;
            color: #1e1e1e;
            line-height: 1.55;
        }

        /* Rodapé fixo */
        .footer-wrap {
            position: fixed;
            bottom: 7mm;
            left: 8mm;
            right: 8mm;
            width: auto;
            margin: 0;
            padding: 0;
            page-break-inside: avoid;
            z-index: 10;
        }

        .footer-tagline {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            color: #4a2070;
            margin: 0 0 4px;
        }

        .footer-contacts {
            text-align: center;
            font-size: 7.5px;
            color: #475569;
            margin: 0 0 4px;
            line-height: 1.4;
        }

        .footer-meta {
            text-align: center;
            font-size: 7px;
            color: #94a3b8;
            padding: 2px 0;
        }

        .footer-band {
            width: 100%;
            background: #4a2070;
            color: #fff;
            font-size: 9px;
            padding: 6px 0;
        }

        .footer-band table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-band td {
            vertical-align: middle;
            padding: 3px 12px;
            color: #fff;
            font-weight: bold;
        }

        .footer-band .col-left {
            text-align: left;
        }

        .footer-band .col-right {
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $brl = fn ($cents) => 'R$ '.number_format(((int) $cents) / 100, 2, ',', '.');
        $paymentConditions = filled($settings->pdf_condicoes_pagamento)
            ? $settings->pdf_condicoes_pagamento
            : \App\Support\CommercialProposalPdfDefaults::defaultPaymentConditions();
        $closingText = filled($settings->pdf_texto_encerramento)
            ? $settings->pdf_texto_encerramento
            : \App\Support\CommercialProposalPdfDefaults::defaultClosingText();

        $companyDisplayName = filled($settings->company_name)
            ? $settings->company_name
            : 'Talents Gestão de Pessoas';

        $footerAddress = filled($settings->company_address)
            ? trim((string) $settings->company_address)
            : 'Av. Fernão Dias Paes Leme, 1300 – Centro – Várzea Paulista – SP';

        if (filled($settings->company_city_state) && ! str_contains($footerAddress, (string) $settings->company_city_state)) {
            $footerAddress .= ' – '.$settings->company_city_state;
        }

        $footerEmail = filled($settings->company_email)
            ? trim((string) $settings->company_email)
            : 'contato@talentsgestao.com';

        $footerPhone = filled($settings->company_phone)
            ? trim((string) $settings->company_phone)
            : '(11) 3109-6843';

        $footerWebsite = 'www.talentsgestao.com';
    @endphp

    <div class="page-frame"></div>

    <div class="header-fixed">
        @if($logoBase64)
            <img src="{{ $logoBase64 }}" alt="Talents">
        @endif
    </div>

    @if(!empty($butterflyBase64))
        <div class="butterfly-decor">
            <img src="{{ $butterflyBase64 }}" alt="">
        </div>
    @endif

    <div class="doc-main">
        <p class="meta-inline">
            <span><strong>Proposta:</strong> {{ $proposal->code }}</span>
            <span><strong>Emitida em:</strong> {{ optional($proposal->created_at)->format('d/m/Y') }}</span>
            <span><strong>Válida até:</strong> {{ $validityDate->format('d/m/Y') }}</span>
        </p>

        <h1>Proposta Comercial</h1>
        @if($proposal->pdf_subtitle)
            <p class="subtitle">{{ $proposal->pdf_subtitle }}</p>
        @endif

        <p class="company-line">{{ $companyDisplayName }}</p>
        <p class="client-line"><strong>Cliente:</strong> {{ $proposal->client_name }}</p>

        <hr class="section-divider">

        <h2>Público Atendido</h2>
        <p class="section-text">Serão contemplados {{ number_format((int) $proposal->employee_count, 0, ',', '.') }} colaboradores.</p>

        @if($proposal->pdf_objetivo)
            <hr class="section-divider">
            <h2>Objetivo</h2>
            <p class="section-text">{!! nl2br(e($proposal->pdf_objetivo)) !!}</p>
        @endif

        @if(empty($services))
            <hr class="section-divider">
            <h2>Serviços</h2>
            <p class="muted">Nenhum serviço selecionado nesta proposta.</p>
        @else
            @foreach($services as $index => $line)
                <hr class="section-divider">
                <div class="service-block">
                    <h3 class="service-title">{{ $index + 1 }}. {{ $line['label'] }}</h3>
                    @if(!empty($line['detail']))
                        <p class="service-detail">{{ $line['detail'] }}</p>
                    @endif
                    @if(!empty($line['discount_cents']) && (int) $line['discount_cents'] > 0)
                        <p class="investment">
                            <strong>Investimento:</strong>
                            <span class="investment-original">{{ $brl((int) ($line['subtotal_cents'] ?? $line['value_cents'])) }}</span>
                        </p>
                        <p class="investment-discount"><strong>Desconto:</strong> −{{ $brl((int) $line['discount_cents']) }}</p>
                        <p class="investment-final"><strong>Valor final:</strong> {{ $brl($line['value_cents']) }}</p>
                    @else
                        <p class="investment"><strong>Investimento:</strong> {{ $brl($line['value_cents']) }}</p>
                    @endif
                    @if(!empty($line['description']))
                        <div class="service-description">
                            @include('reports.partials.description-text', ['text' => $line['description']])
                        </div>
                    @endif
                </div>
            @endforeach

            <hr class="section-divider">
            <h2>Resumo do Investimento</h2>
            <table class="services">
                <thead>
                    <tr>
                        <th style="width: 70%;">Serviço</th>
                        <th class="value" style="width: 30%;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($services as $line)
                        <tr>
                            <td>{{ $line['label'] }}</td>
                            <td class="value">{{ $brl($line['value_cents']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>Honorário Total</td>
                        <td class="value">{{ $brl((int) $proposal->total_final_cents) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif

        @if(!empty($optionalSections))
            <hr class="section-divider">
            <h2>Projetos e serviços complementares</h2>
            <p class="muted" style="margin-bottom: 10px;">
                Os itens abaixo não estão inclusos no investimento acima e poderão ser contratados conforme a necessidade da empresa.
            </p>
            @foreach($optionalSections as $index => $section)
                <div class="service-block">
                    <h3 class="service-title">{{ $index + 1 }}. {{ $section['label'] }}</h3>
                    @if(!empty($section['text']))
                        <div class="service-description">
                            @include('reports.partials.description-text', ['text' => $section['text']])
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

        <hr class="section-divider">
        <h2>Condições de Pagamento</h2>
        <div class="section-text">
            @include('reports.partials.description-text', ['text' => $paymentConditions])
            <p class="desc-paragraph">• Prazo de validade desta proposta: {{ $settings->pdf_validade_dias ?? 7 }} dias.</p>
        </div>

        @if($proposal->seller)
            <p class="commission-inline">
                <strong>Vendedor responsável:</strong> {{ $proposal->seller->name }}
            </p>
        @endif

        @if($closingText)
            <div class="closing-text">
                {!! nl2br(e($closingText)) !!}
            </div>
        @endif
    </div>

    <div class="footer-wrap">
        <p class="footer-tagline">Conectando Talentos e Transformando Negócios.</p>
        <p class="footer-contacts">
            {{ $footerAddress }}
            | {{ $footerEmail }}
            | {{ $footerWebsite }}
            | {{ $footerPhone }}
        </p>
        <div class="footer-meta">
            Proposta {{ $proposal->code }} — gerada em {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="footer-band">
            <table>
                <tr>
                    <td class="col-left">WhatsApp (11) 97570-3032</td>
                    <td class="col-right">{{ $footerEmail }}</td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
