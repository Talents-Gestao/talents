<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Contrato — {{ $code }}</title>
    <style>
        /* Margem inferior maior reserva espaço para o rodapé do timbrado (DomPDF) */
        /* Direita maior: reserva faixa para a borboleta do timbrado (não sobrepor o texto) */
        @page { margin: 12mm 16mm 38mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #0f172a;
            line-height: 1.45;
            margin: 0;
            padding: 0 0 10mm;
            box-sizing: border-box;
        }
        .doc-main {
            position: relative;
            z-index: 3;
            /* Folga à direita (DomPDF respeita melhor margin do que padding) */
            margin-right: 24mm;
            width: auto;
        }
        .top-stripe {
            height: 3mm;
            background: #4a2070;
            margin: 0 0 8px;
            width: 100%;
        }
        .header { width: 100%; padding-bottom: 10px; margin-bottom: 8px; border-bottom: 1px solid #e5e7eb; }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .header img { max-height: 52px; width: auto; display: block; }
        .header .meta { text-align: right; vertical-align: top; padding-top: 4px; }
        .meta-row { margin-bottom: 6px; }
        .meta-lbl {
            display: block;
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: bold;
        }
        .meta-val { font-size: 12px; color: #0f172a; font-weight: bold; }
        .contract-body { margin-top: 4px; padding-bottom: 8mm; }
        .contract-body table { border-collapse: collapse; }

        /* Canto inferior direito, atrás do conteúdo — só decoração do timbrado */
        .butterfly-decor {
            position: fixed;
            right: 2mm;
            bottom: 42mm;
            width: 20mm;
            z-index: 1;
            opacity: 0.4;
            pointer-events: none;
        }
        .butterfly-decor img {
            width: 100%;
            max-height: 28mm;
            height: auto;
            display: block;
        }

        /* Rodapé do timbrado oficial */
        .footer-wrap {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            margin: 0;
            padding: 8px 0 0;
            page-break-inside: avoid;
            z-index: 10;
        }
        .footer-tagline {
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            color: #510E62;
            margin: 0 0 10px;
            line-height: 1.5;
        }
        .footer-contacts {
            text-align: center;
            font-size: 7px;
            color: #510E62;
            margin: 0 0 10px;
            line-height: 1.7;
            font-weight: 700;
        }
        .footer-meta {
            text-align: center;
            font-size: 7px;
            color: #94a3b8;
            padding: 0 0 10px;
            line-height: 1.5;
        }
        .footer-band {
            width: 100%;
            background: #4a2070;
            color: #fff;
            font-size: 9px;
            padding: 8px 0;
        }
        .footer-band table { width: 100%; border-collapse: collapse; }
        .footer-band td { vertical-align: middle; padding: 6px 16px; color: #fff; font-weight: bold; }
        .footer-band .col-left { text-align: left; }
        .footer-band .col-right { text-align: right; }
    </style>
</head>
<body>
    @php
        $footerAddress = filled($settings->company_address ?? null)
            ? trim((string) $settings->company_address)
            : 'Av. Fernão Dias Paes Leme, 1300 – Centro – Várzea Paulista – SP';

        if (filled($settings->company_city_state ?? null) && ! str_contains($footerAddress, (string) $settings->company_city_state)) {
            $footerAddress .= ' – '.$settings->company_city_state;
        }

        $footerEmail = filled($settings->company_email ?? null)
            ? trim((string) $settings->company_email)
            : 'contato@talentsgestao.com';

        $footerWebsite = 'www.talentsgestao.com';
        $footerWhatsapp = '(11) 97570-3032';
    @endphp

    <div class="doc-main">
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
                            <span class="meta-lbl">Contrato</span>
                            <span class="meta-val">{{ $code }}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-lbl">Emitido em</span>
                            <span class="meta-val">{{ $generatedAt->format('d/m/Y') }}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="contract-body">
            {!! $content_html !!}
        </div>
    </div>

    @if(!empty($butterflyBase64))
        <div class="butterfly-decor">
            <img src="{{ $butterflyBase64 }}" alt="">
        </div>
    @endif

    <div class="footer-wrap">
        <p class="footer-tagline">Conectando Talentos e Transformando Negócios.</p>
        <p class="footer-contacts">
            {{ $footerAddress }}
            | {{ $footerWebsite }}
        </p>
        <div class="footer-meta">
            Contrato {{ $code }} — gerado em {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="footer-band">
            <table>
                <tr>
                    <td class="col-left">WhatsApp {{ $footerWhatsapp }}</td>
                    <td class="col-right">{{ $footerEmail }}</td>
                </tr>
            </table>
        </div>
    </div>

</body>
</html>
