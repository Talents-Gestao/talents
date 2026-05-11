<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Contrato — {{ $code }}</title>
    <style>
        /* Margem inferior maior reserva espaço para o rodapé fixo (DomPDF) */
        @page { margin: 12mm 16mm 22mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #0f172a;
            line-height: 1.45;
            margin: 0;
            padding: 0 0 6mm;
            box-sizing: border-box;
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
        .contract-body { margin-top: 4px; }
        .contract-body table { border-collapse: collapse; }
        /* Rodapé colado ao rodapé físico da página (pouco texto ou última página) */
        .footer-wrap {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            margin: 0;
            padding: 0;
            page-break-inside: avoid;
            z-index: 10;
        }
        .footer-meta {
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            padding: 4px 0 4px;
        }
        .footer-band {
            width: 100%;
            background: #4a2070;
            color: #fff;
            font-size: 10px;
            padding: 8px 0;
        }
        .footer-band table { width: 100%; border-collapse: collapse; }
        .footer-band td { vertical-align: middle; padding: 4px 16px; color: #fff; font-weight: bold; }
        .footer-band .col-left { text-align: left; }
        .footer-band .col-right { text-align: right; }
    </style>
</head>
<body>
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

    <div class="footer-wrap">
        <div class="footer-meta">
            Contrato {{ $code }} — gerado em {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="footer-band">
            <table>
                <tr>
                    <td class="col-left">WhatsApp (11) 97570-3032</td>
                    <td class="col-right">contato@talentsgestao.com</td>
                </tr>
            </table>
        </div>
    </div>

</body>
</html>
