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
            padding: 24mm 12mm 42mm;
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
            width: 100%;
        }

        /* Metadados discretos (1ª página) */
        .meta-inline {
            font-size: 7.5px;
            color: #94a3b8;
            text-align: right;
            margin: 0 0 12px;
            line-height: 1.45;
            letter-spacing: 0.02em;
        }

        .meta-inline span {
            margin-left: 12px;
        }

        .meta-inline strong {
            font-weight: 600;
            color: #64748b;
        }

        h1 {
            font-size: 16px;
            font-weight: 700;
            color: #4a2070;
            margin: 0 0 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            line-height: 1.2;
        }

        .subtitle {
            font-size: 12px;
            font-weight: 700;
            color: #1e1e1e;
            margin: 0 0 8px;
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
            color: #4a2070;
        }

        .intro-block {
            margin: 0 0 4px;
        }

        /* Títulos de secção — coluna alinhada ao conteúdo */
        .pdf-section {
            margin: 16px 0 0;
            padding: 0;
            width: 100%;
        }

        .pdf-section-title,
        h2 {
            font-size: 11px;
            font-weight: 700;
            color: #4a2070;
            margin: 0 0 8px;
            padding: 0 0 5px;
            border: none;
            border-bottom: 1.5px solid #4a2070;
            text-transform: none;
            letter-spacing: 0.01em;
            line-height: 1.3;
        }

        .pdf-card .pdf-section-title {
            margin-bottom: 6px;
        }

        .pdf-card {
            background: #f8f5fc;
            border: 1px solid #e4d8ef;
            padding: 9px 10px;
            margin-top: 16px;
            width: 100%;
        }

        .muted {
            color: #64748b;
            font-size: 11px;
            line-height: 1.45;
            margin: 0 0 8px;
        }

        .section-text {
            font-size: 11px;
            color: #1e1e1e;
            line-height: 1.5;
            margin: 0;
        }

        /* Listas — indentação única */
        .pdf-list,
        .desc-bullets {
            margin: 2px 0 4px;
            padding: 0 0 0 16px;
            font-size: 11px;
            color: #1e1e1e;
            line-height: 1.45;
            list-style-type: disc;
        }

        .pdf-list li,
        .desc-bullets li {
            margin: 0 0 4px;
            padding: 0;
        }

        .pdf-list li:last-child,
        .desc-bullets li:last-child {
            margin-bottom: 0;
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

        /* Blocos de serviço */
        .service-block {
            page-break-inside: avoid;
            margin: 0 0 12px;
            padding: 0;
        }

        .service-block:last-child {
            margin-bottom: 0;
        }

        h3.service-title,
        table.service-heading {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 6px;
            font-size: 11px;
            line-height: 1.35;
        }

        table.service-heading td {
            vertical-align: baseline;
            padding: 0;
            border: none;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.35;
            color: #1e1e1e;
        }

        table.service-heading td.service-num {
            width: 18px;
            padding-right: 4px;
            color: #4a2070;
            white-space: nowrap;
        }

        table.service-heading td.service-label {
            color: #1e1e1e;
        }

        .investment-row {
            margin: 0 0 6px;
            padding: 5px 8px;
            background: #faf8fc;
            border-left: 2.5px solid #4a2070;
        }

        .investment {
            font-size: 11px;
            color: #1e1e1e;
            margin: 0;
            line-height: 1.4;
        }

        .investment-original {
            text-decoration: line-through;
            color: #94a3b8;
            margin-right: 6px;
        }

        .investment-discount {
            font-size: 10.5px;
            color: #047857;
            margin: 2px 0 0;
        }

        .investment-final {
            font-size: 11px;
            color: #1e1e1e;
            font-weight: bold;
            margin: 2px 0 0;
        }

        .service-observation {
            font-size: 10.5px;
            color: #334155;
            margin: 0 0 6px;
            line-height: 1.45;
        }

        .service-observation strong {
            font-weight: 600;
            color: #1e293b;
        }

        .service-description {
            margin: 4px 0 0;
        }

        table.services {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 11px;
            page-break-inside: avoid;
        }

        table.services th,
        table.services td {
            border-bottom: 1px solid #e2e8f0;
            padding: 7px 4px;
            text-align: left;
        }

        table.services th {
            background: transparent;
            color: #4a2070;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border-bottom: 1.5px solid #4a2070;
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
            padding-top: 9px;
        }

        .commission-inline {
            margin-top: 14px;
            font-size: 10.5px;
            color: #64748b;
            line-height: 1.45;
        }

        .closing-text {
            margin-top: 14px;
            font-size: 11px;
            color: #1e1e1e;
            line-height: 1.55;
        }

        /* Rodapé fixo — tagline → contactos → banda */
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
            font-size: 10.5px;
            font-weight: 700;
            color: #4a2070;
            margin: 0 0 3px;
            letter-spacing: 0.01em;
        }

        .footer-contacts {
            text-align: center;
            font-size: 7.5px;
            color: #64748b;
            margin: 0 0 5px;
            line-height: 1.4;
        }

        .footer-meta {
            text-align: center;
            font-size: 6.5px;
            color: #94a3b8;
            padding: 0 0 4px;
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
        $paymentConditions = $proposal->paymentMethodPdfBullet();
        if ($paymentConditions === null && filled($settings->pdf_condicoes_pagamento)) {
            $paymentConditions = $settings->pdf_condicoes_pagamento;
        }
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

        $footerWebsite = 'www.talentsgestao.com';

        // Uma única lista tipográfica: pagamento + permanência + validade (sem «•» solto em <p>)
        $paymentConditionItems = [];
        if (filled($paymentConditions)) {
            foreach (preg_split('/\r?\n/', (string) $paymentConditions) as $line) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }
                $body = ltrim((string) preg_replace('/^[•\-]\s*/u', '', $trimmed));
                if ($body !== '') {
                    $paymentConditionItems[] = $body;
                }
            }
        }
        if ($proposal->include_minimum_stay ?? true) {
            $stay = \App\Support\CommercialProposalPdfDefaults::defaultMinimumStayCondition();
            $paymentConditionItems[] = ltrim((string) preg_replace('/^[•\-]\s*/u', '', $stay));
        }
        $paymentConditionItems[] = 'Prazo de validade desta proposta: '.($settings->pdf_validade_dias ?? 7).' dias.';
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

        <div class="intro-block">
            <h1>Proposta Comercial</h1>
            @if($proposal->pdf_subtitle)
                <p class="subtitle">{{ $proposal->pdf_subtitle }}</p>
            @endif

            <p class="company-line">{{ $companyDisplayName }}</p>
            <p class="client-line"><strong>Cliente:</strong> {{ $proposal->client_name }}</p>
        </div>

        @if($proposal->include_publico_atendido ?? true)
            <div class="pdf-section">
                <h2 class="pdf-section-title">Público Atendido</h2>
                <p class="section-text">Serão contemplados {{ number_format((int) $proposal->employee_count, 0, ',', '.') }} colaboradores.</p>
            </div>
        @endif

        @if($proposal->pdf_objetivo)
            <div class="pdf-section">
                <h2 class="pdf-section-title">Objetivo</h2>
                <p class="section-text">{!! nl2br(e($proposal->pdf_objetivo)) !!}</p>
            </div>
        @endif

        @if(empty($services))
            <div class="pdf-section">
                <h2 class="pdf-section-title">Serviços</h2>
                <p class="muted">Nenhum serviço selecionado nesta proposta.</p>
            </div>
        @else
            <div class="pdf-section">
                <h2 class="pdf-section-title">Serviços</h2>
                @foreach($services as $index => $line)
                    <div class="service-block">
                        <table class="service-heading">
                            <tr>
                                <td class="service-num">{{ $index + 1 }}.</td>
                                <td class="service-label">{{ $line['label'] }}</td>
                            </tr>
                        </table>
                        @if(!empty($line['observation']))
                            <p class="service-observation">
                                <strong>Observação:</strong><br>
                                {!! nl2br(e($line['observation'])) !!}
                            </p>
                        @endif
                        <div class="investment-row">
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
                        </div>
                        @if(!empty($line['description']))
                            <div class="service-description">
                                @include('reports.partials.description-text', ['text' => $line['description']])
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="pdf-section">
                <h2 class="pdf-section-title">Resumo do Investimento</h2>
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
            </div>
        @endif

        @if($proposal->isRecurringService())
            <div class="pdf-card">
                <h2 class="pdf-section-title">Condições do serviço (recorrente)</h2>
                <ul class="pdf-list">
                    <li>
                        Serviço recorrente ao longo de
                        {{ (int) $proposal->recurring_months }}
                        {{ (int) $proposal->recurring_months === 1 ? 'mês' : 'meses' }}.
                    </li>
                    <li>Valor mensal: {{ $brl((int) $proposal->recurring_monthly_cents) }}.</li>
                    <li>Valor total do período: {{ $brl((int) $proposal->total_final_cents) }}.</li>
                    <li>
                        O pagamento é mensal durante a vigência. Não se trata de entrega única —
                        o acompanhamento ocorre ao longo do período contratado.
                    </li>
                </ul>
                @if(filled($proposal->recurring_notes))
                    <p class="section-text" style="margin-top: 10px;">
                        {!! nl2br(e($proposal->recurring_notes)) !!}
                    </p>
                @endif
            </div>
        @endif

        @php
            $visibleOptionalSections = array_values(array_filter(
                $optionalSections ?? [],
                static fn ($section): bool => filled(trim((string) ($section['label'] ?? '')))
                    || filled(trim((string) ($section['text'] ?? '')))
            ));
        @endphp
        @if(count($visibleOptionalSections) > 0)
            <div class="pdf-section">
                <h2 class="pdf-section-title">Projetos e serviços complementares</h2>
                <p class="muted">
                    Os itens abaixo não estão inclusos no investimento acima e poderão ser contratados conforme a necessidade da empresa.
                </p>
                @foreach($visibleOptionalSections as $index => $section)
                    <div class="service-block">
                        <table class="service-heading">
                            <tr>
                                <td class="service-num">{{ $index + 1 }}.</td>
                                <td class="service-label">{{ $section['label'] }}</td>
                            </tr>
                        </table>
                        @if(filled(trim((string) ($section['text'] ?? ''))))
                            <div class="service-description">
                                @include('reports.partials.description-text', ['text' => $section['text']])
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="pdf-card">
            <h2 class="pdf-section-title">Condições de Pagamento</h2>
            <ul class="pdf-list">
                @foreach($paymentConditionItems as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>

        @if(filled($proposal->notes))
            <div class="pdf-section">
                <h2 class="pdf-section-title">Observações</h2>
                <p class="section-text">{!! nl2br(e($proposal->notes)) !!}</p>
            </div>
        @endif

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
