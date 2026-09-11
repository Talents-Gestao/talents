<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Parecer técnico - {{ $survey->title }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; line-height: 1.45; }
        h1 { font-size: 18px; color: #4a2070; margin: 8px 0 4px; }
        h2 { font-size: 13px; color: #4a2070; margin: 18px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .muted { color: #64748b; font-size: 10px; }
        .header { text-align: center; margin-bottom: 12px; }
        .header img { max-height: 48px; width: auto; }
        .meta { margin-bottom: 10px; }
        .draft {
            margin: 0 0 12px;
            padding: 8px 10px;
            border: 1px solid #f59e0b;
            background: #fffbeb;
            color: #92400e;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
        }
        .opinion { margin-top: 6px; padding: 8px; border: 1px solid #e2e8f0; background: #fafafa; font-size: 10px; }
        .opinion p { margin: 0 0 6px; }
    </style>
</head>
<body>
    @php
        $scenarioLabel = $scenarioConfig['short_label'] ?? null;
    @endphp

    <div class="header">
        @if(!empty($logoBase64))
            <img src="{{ $logoBase64 }}" alt="Talents">
        @endif
    </div>

    @if(!empty($isDraft))
        <div class="draft">Rascunho — este documento ainda não foi publicado para a empresa.</div>
    @endif

    <h1>Parecer técnico NR-1</h1>
    <div class="meta">
        <p class="muted">Empresa: {{ $survey->company->name }} — Campanha: {{ $survey->title }}</p>
        <p class="muted">Data de emissão: {{ now()->format('d/m/Y') }}</p>
        @if(!empty($scenarioLabel))
            <p class="muted">Cenário: {{ $scenarioLabel }}</p>
        @endif
    </div>

    @if(!empty($technicalOpinion))
        <h2>Parecer técnico</h2>
        <div class="opinion">
            {!! $technicalOpinion !!}
        </div>
    @else
        <h2>Parecer técnico</h2>
        <p class="muted">Nenhum parecer técnico informado neste documento.</p>
    @endif
</body>
</html>
