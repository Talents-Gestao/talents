<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Panorama NR-1 - {{ $dashboard['header']['company_name'] }} - {{ $dashboard['header']['campaign_title'] }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 1.45; color: #1f1233; background: #fff; }
        main { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 22px; margin: 4px 0; color: #3b1a5c; }
        h2 { font-size: 15px; margin: 24px 0 8px; color: #3b1a5c; break-after: avoid; }
        .muted { color: #5b5468; font-size: 12px; }
        .toolbar { display: flex; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .toolbar button { padding: 8px 14px; border: 1px solid #3b1a5c; background: #3b1a5c; color: #fff; border-radius: 6px; font-size: 13px; cursor: pointer; }
        .toolbar button:focus-visible { outline: 3px solid #f59e0b; outline-offset: 2px; }
        .badge { display: inline-block; padding: 1px 8px; border: 1px solid #bbb; border-radius: 6px; font-size: 11px; font-weight: bold; }
        .badge.green { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
        .badge.yellow { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
        .badge.red { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
        .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 10px 12px; break-inside: avoid; }
        .card .label { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #5b5468; }
        .card .value { font-size: 26px; font-weight: bold; color: #3b1a5c; margin-top: 4px; }
        .card .value small { font-size: 13px; font-weight: normal; color: #5b5468; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #f5f0fa; font-size: 12px; }
        tr { break-inside: avoid; }
        .bar { height: 8px; background: #eee; border-radius: 4px; min-width: 90px; }
        .bar > span { display: block; height: 8px; border-radius: 4px; background: #6b7280; }
        .bar.green > span { background: #10b981; } .bar.yellow > span { background: #f59e0b; } .bar.red > span { background: #ef4444; }
        .warning { margin: 10px 0; padding: 8px 10px; border: 1px solid #f59e0b; background: #fffbeb; font-weight: bold; }
        .alert { margin: 10px 0; padding: 8px 10px; border: 1px solid #ef4444; background: #fef2f2; }
        .note { margin-top: 8px; padding: 6px 8px; background: #f3f4f6; font-size: 12px; }
        .footer { margin-top: 28px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 11px; color: #5b5468; }
        ol.plain { padding-left: 20px; margin: 0; }
        .page-break { break-before: page; }
        @media (max-width: 640px) { .cards { grid-template-columns: repeat(2, 1fr); } body { padding: 12px; } }
        @media print {
            body { padding: 0; font-size: 11px; }
            .toolbar { display: none; }
            a { color: inherit; text-decoration: none; }
            @page { margin: 14mm; }
        }
    </style>
</head>
<body>
@php
    $header = $dashboard['header'];
    $cards = $dashboard['cards'];
    $bandClass = fn ($level) => in_array($level, ['green', 'yellow', 'red'], true) ? $level : '';
@endphp
<main>
    <div class="toolbar">
        <span class="muted">Use “Imprimir” e escolha “Salvar como PDF” se quiser um arquivo.</span>
        <button type="button" onclick="window.print()">Imprimir panorama</button>
    </div>

    <p class="muted">Pesquisa psicossocial · NR-1</p>
    <h1>{{ $header['company_name'] ?? 'Empresa' }}</h1>
    <p>
        @if($header['unit_name'])Estabelecimento: {{ $header['unit_name'] }} · @endif
        {{ $header['campaign_title'] }}
        @if($header['period_label']) · {{ $header['period_label'] }}@endif
        · <span class="badge">{{ $header['status']['label'] }}</span>
    </p>

    @if($dashboard['polarity_warning'])
        <div class="warning">{{ $dashboard['polarity_warning'] }}</div>
    @endif
    @if($dashboard['eligible']['alert'])
        <div class="alert">{{ $dashboard['eligible']['alert'] }}</div>
    @endif

    @if(! $dashboard['has_results'])
        <p class="note">Os resultados desta campanha ainda não foram calculados.</p>
    @endif

    <h2>Resumo</h2>
    <div class="cards">
        <div class="card">
            <div class="label">Índice geral</div>
            @if($cards['overall'])
                <div class="value">{{ $cards['overall']['score_label'] }} <small>/ 5</small></div>
                <span class="badge {{ $bandClass($cards['overall']['risk_level']) }}">{{ $cards['overall']['band_label'] }}</span>
            @else
                <div class="value">—</div>
            @endif
        </div>
        <div class="card">
            <div class="label">Participação</div>
            <div class="value">{{ $cards['participation']['percent_label'] ?? '—' }}</div>
            <div class="muted">
                {{ $cards['participation']['respondents'] }} {{ $cards['participation']['respondents'] === 1 ? 'resposta' : 'respostas' }}
                @if($cards['participation']['eligible'] !== null) · {{ $cards['participation']['eligible'] }} elegíveis @endif
            </div>
        </div>
        <div class="card">
            <div class="label">Dimensões em atenção</div>
            <div class="value">
                @if($cards['attention']['count'] === null) — @else {{ $cards['attention']['count'] }} <small>/ {{ $cards['attention']['total'] }}</small> @endif
            </div>
        </div>
        <div class="card">
            <div class="label">Plano de ação</div>
            <div class="value">{{ $cards['plan']['confirmed'] }} <small>/ {{ $cards['plan']['total'] }}</small></div>
            <div class="muted">Medidas com responsável confirmado · {{ $cards['plan']['implemented'] }} concluídas</div>
        </div>
    </div>
    <p class="muted" style="margin-top:8px">{{ $dashboard['band_disclaimer'] }} {{ $dashboard['method']['legend'] }}</p>

    <h2>Indicadores das dimensões</h2>
    @if(count($dashboard['dimensions']))
        <table>
            <thead><tr><th>Dimensão</th><th>Índice</th><th>Faixa</th><th aria-hidden="true">Escala 1–5</th></tr></thead>
            <tbody>
            @foreach($dashboard['dimensions'] as $dimension)
                <tr>
                    <td>{{ $dimension['title'] }}</td>
                    <td>{{ $dimension['score_label'] }}</td>
                    <td><span class="badge {{ $bandClass($dimension['risk_level']) }}">{{ $dimension['band_label'] }}</span></td>
                    <td aria-hidden="true"><div class="bar {{ $bandClass($dimension['risk_level']) }}"><span style="width: {{ $dimension['bar_percent'] }}%"></span></div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Os indicadores aparecem depois que as respostas forem calculadas.</p>
    @endif

    <h2>O que merece atenção</h2>
    @if(count($dashboard['attention']))
        <ol class="plain">
            @foreach($dashboard['attention'] as $dimension)
                <li>{{ $dimension['title'] }} — {{ $dimension['score_label'] }}
                    <span class="badge {{ $bandClass($dimension['risk_level']) }}">{{ $dimension['band_label'] }}</span></li>
            @endforeach
        </ol>
    @else
        <p class="muted">{{ $dashboard['has_results'] ? 'Nenhuma dimensão ficou em atenção nesta pesquisa.' : 'As prioridades aparecem depois que as respostas forem calculadas.' }}</p>
    @endif

    <h2>Pontos fortes</h2>
    <p class="muted">Percentual de respostas favoráveis; o N é a base de cada pergunta.</p>
    @if(count($dashboard['strengths']))
        <ol class="plain">
            @foreach($dashboard['strengths'] as $strength)
                <li>{{ $strength['body'] }} — <strong>{{ $strength['favorable_pct_label'] }}</strong> (N = {{ $strength['n'] }})</li>
            @endforeach
        </ol>
    @else
        <p class="muted">Ainda não há respostas para destacar pontos fortes.</p>
    @endif

    <h2>Participação por grupo</h2>
    @if(count($dashboard['groups']['items']))
        <table>
            <thead><tr><th>Grupo</th><th>Respostas</th><th>% do total</th></tr></thead>
            <tbody>
            @foreach($dashboard['groups']['items'] as $group)
                <tr>
                    <td>
                        {{ $group['name'] }}
                        @if($group['composition'])<div class="muted">Reúne: {{ implode(', ', $group['composition']) }}.</div>@endif
                    </td>
                    <td>{{ $group['n'] }}</td>
                    <td>{{ $group['percent_label'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Nenhum grupo pode ser exibido separadamente nesta campanha.</p>
    @endif
    @if($dashboard['groups']['note'])<p class="note">{{ $dashboard['groups']['note'] }}</p>@endif

    @if(count($dashboard['sectors']))
        <h2>Setores</h2>
        <p class="muted">Setores com menos de {{ $dashboard['min_group_size'] }} respondentes não são exibidos.</p>
        <table>
            <thead><tr><th>Setor</th><th>N</th><th>Índice</th><th>Faixa</th></tr></thead>
            <tbody>
            @foreach($dashboard['sectors'] as $sector)
                <tr>
                    <td>{{ $sector['department_name'] }}</td>
                    <td>{{ $sector['n'] }}</td>
                    <td>{{ $sector['score_label'] }}</td>
                    <td><span class="badge {{ $bandClass($sector['risk_level']) }}">{{ $sector['band_label'] }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if(count($dashboard['items']))
        <h2 class="page-break">As {{ count($dashboard['items']) }} perguntas</h2>
        <p class="muted">Distribuição da campanha inteira. Favorável respeita o sentido de cada afirmação.</p>
        <table>
            <thead>
            <tr>
                <th>#</th><th>Pergunta</th><th>N</th><th>Nunca</th><th>Raramente</th><th>Às vezes</th><th>Freq.</th><th>Sempre</th><th>Favorável</th>
            </tr>
            </thead>
            <tbody>
            @foreach($dashboard['items'] as $item)
                <tr>
                    <td>{{ $item['position'] }}</td>
                    <td>{{ $item['body'] }}<div class="muted">{{ $item['section_title'] }}</div></td>
                    <td>{{ $item['n'] }}</td>
                    @foreach([1, 2, 3, 4, 5] as $value)
                        <td>{{ $item['distribution'][$value] === null ? '—' : str_replace('.', ',', (string) $item['distribution'][$value]).'%' }}</td>
                    @endforeach
                    <td>{{ $item['favorable_pct_label'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">
        Gerado em {{ $generatedAt->format('d/m/Y H:i') }}. Os resultados são agregados: respostas individuais não aparecem.
        {{ $dashboard['band_disclaimer'] }}
    </p>
</main>
</body>
</html>
