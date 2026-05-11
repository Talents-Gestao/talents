<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Proposta Comercial — {{ $proposal->code }}</title>
    <style>
        @page { margin: 16mm 16mm 18mm 16mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #0f172a;
            line-height: 1.5;
            margin: 0;
            padding: 0 0 32mm;
        }
        .top-stripe {
            height: 5mm;
            background: #4a2070;
            margin: 0 0 14px;
            width: 100%;
        }
        .header { width: 100%; padding-bottom: 16px; margin-bottom: 8px; border-bottom: 1px solid #e5e7eb; }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .header img { max-height: 64px; width: auto; display: block; }
        .header .meta { text-align: right; vertical-align: top; padding-top: 4px; }
        .meta-row { margin-bottom: 6px; }
        .meta-lbl {
            display: block;
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
        }
        .meta-val { font-size: 12px; color: #0f172a; font-weight: 600; }
        h1 {
            font-size: 26px;
            font-weight: 700;
            color: #4a2070;
            margin: 20px 0 0;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }
        .accent-line {
            width: 56px;
            height: 3px;
            background: #f4b400;
            margin-top: 10px;
            margin-bottom: 0;
        }
        h2 {
            font-size: 13px;
            color: #4a2070;
            margin: 28px 0 10px;
            padding: 0;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
        }
        .muted { color: #64748b; font-size: 11px; line-height: 1.55; }
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #475569;
        }
        .badge-open { border-color: #e2e8f0; color: #64748b; }
        .badge-closed { border-color: #86efac; color: #166534; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { vertical-align: top; padding: 6px 0; }
        .grid .label {
            color: #94a3b8;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        .grid .value { color: #0f172a; font-size: 12px; font-weight: 600; margin-top: 2px; }
        table.services { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.services th, table.services td {
            border-bottom: 1px solid #f1f5f9;
            padding: 11px 4px;
            text-align: left;
        }
        table.services th {
            background: transparent;
            color: #4a2070;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
            border-bottom: 1px solid #4a2070;
        }
        table.services td.value, table.services th.value {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        table.services tr.total td {
            background: transparent;
            font-weight: 700;
            font-size: 16px;
            color: #4a2070;
            border-top: 2px solid #4a2070;
            border-bottom: none;
            padding-top: 14px;
        }
        .commission-inline {
            margin-top: 16px;
            font-size: 11px;
            color: #475569;
            line-height: 1.5;
        }
        .notes {
            margin-top: 14px;
            padding: 0 0 0 14px;
            border-left: 3px solid #f4b400;
            font-size: 11px;
            color: #475569;
            background: transparent;
        }
        .notes strong { color: #0f172a; }
        .notes-proposal {
            border-left-color: #06b6d4;
            color: #475569;
        }
        .accept {
            margin-top: 12px;
            padding: 0;
            background: transparent;
            border: none;
            font-size: 11px;
            color: #475569;
            text-align: justify;
            line-height: 1.55;
        }
        .signature { margin-top: 64px; }
        .signature table { width: 100%; border-collapse: collapse; }
        .signature td { width: 50%; text-align: center; padding: 0 18px; vertical-align: top; }
        .signature .line {
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            font-size: 10px;
            color: #475569;
        }
        .footer-meta {
            position: fixed;
            bottom: 15mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
        }
        .footer-band {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 12mm;
            background: #4a2070;
            color: #fff;
            font-size: 10px;
        }
        .footer-band table { width: 100%; height: 100%; border-collapse: collapse; }
        .footer-band td { vertical-align: middle; padding: 0 16mm; color: #fff; font-weight: 600; }
        .footer-band .col-left { text-align: left; }
        .footer-band .col-right { text-align: right; }
    </style>
</head>
<body>
    @php
        $brl = fn (int $cents) => 'R$ '.number_format($cents / 100, 2, ',', '.');
    @endphp

    <div class="top-stripe"></div>

    <div class="header">
        <table>
            <tr>
                <td style="width: 48%;">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="Talents">
                    @endif
                </td>
                <td class="meta">
                    <div class="meta-row">
                        <span class="meta-lbl">Proposta</span>
                        <span class="meta-val">{{ $proposal->code }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-lbl">Emitida em</span>
                        <span class="meta-val">{{ optional($proposal->created_at)->format('d/m/Y') }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-lbl">Válida até</span>
                        <span class="meta-val">{{ $validityDate->format('d/m/Y') }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h1>Proposta Comercial</h1>
    <div class="accent-line"></div>

    <p class="muted" style="margin-top: 14px;">
        @if($proposal->is_closed)
            <span class="badge badge-closed">Fechada</span>
        @else
            <span class="badge badge-open">Em negociação</span>
        @endif
        &nbsp;Esta proposta é nominal e personalizada para o cliente abaixo.
    </p>

    <h2>Dados do cliente</h2>
    <table class="grid">
        <tr>
            <td style="width: 60%;">
                <div class="label">Razão social / Nome</div>
                <div class="value">{{ $proposal->client_name }}</div>
            </td>
            <td style="width: 40%;">
                <div class="label">CNPJ</div>
                <div class="value">{{ $proposal->client_cnpj ?: '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">E-mail</div>
                <div class="value">{{ $proposal->client_email ?: '—' }}</div>
            </td>
            <td>
                <div class="label">Telefone</div>
                <div class="value">{{ $proposal->client_phone ?: '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Indicação</div>
                <div class="value">{{ $proposal->indication ?: '—' }}</div>
            </td>
            <td>
                <div class="label">Nº de funcionários</div>
                <div class="value">{{ $proposal->employee_count }}</div>
            </td>
        </tr>
    </table>

    <h2>Serviços contratados</h2>
    @if(empty($services))
        <p class="muted">Nenhum serviço selecionado nesta proposta.</p>
    @else
        <table class="services">
            <thead>
                <tr>
                    <th style="width: 40%;">Serviço</th>
                    <th style="width: 40%;">Detalhe</th>
                    <th class="value" style="width: 20%;">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($services as $line)
                    <tr>
                        <td>{{ $line['label'] }}</td>
                        <td class="muted">{{ $line['detail'] }}</td>
                        <td class="value">{{ $brl($line['value_cents']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="2">Honorário Total</td>
                    <td class="value">{{ $brl((int) $proposal->total_final_cents) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if($proposal->seller)
        <p class="commission-inline">
            <strong>Vendedor responsável:</strong> {{ $proposal->seller->name }}
            @if($proposal->commission_percent > 0)
                &nbsp;|&nbsp; Comissão: {{ number_format((float) $proposal->commission_percent, 2, ',', '.') }}%
                ({{ $brl((int) $proposal->commission_cents) }})
            @endif
        </p>
    @endif

    @if($settings->pdf_observacoes)
        <div class="notes">
            <strong>Observações:</strong><br>
            {!! nl2br(e($settings->pdf_observacoes)) !!}
        </div>
    @endif

    @if($proposal->notes)
        <div class="notes notes-proposal">
            <strong>Observações específicas desta proposta:</strong><br>
            {!! nl2br(e($proposal->notes)) !!}
        </div>
    @endif

    <h2>Aceite</h2>
    <div class="accept">
        {{ $settings->pdf_aceite_texto ?: 'Declaro estar de acordo com os termos, valores e prazos descritos nesta proposta comercial.' }}
    </div>

    <div class="signature">
        <table>
            <tr>
                <td>
                    <div class="line">Cliente — {{ $proposal->client_name }}</div>
                </td>
                <td>
                    <div class="line">Talents &mdash; {{ optional($proposal->seller)->name ?? 'Responsável Comercial' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer-meta">
        Proposta {{ $proposal->code }} &mdash; gerada em {{ now()->format('d/m/Y H:i') }} &mdash; documento válido com assinatura.
    </div>

    <div class="footer-band">
        <table>
            <tr>
                <td class="col-left">WhatsApp (11) 97570-3032</td>
                <td class="col-right">contato@talentsgestao.com</td>
            </tr>
        </table>
    </div>
</body>
</html>
