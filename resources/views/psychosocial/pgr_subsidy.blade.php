<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Subsídio AEP/PGR - {{ $snapshot['survey']['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2a1042; }
        h1 { font-size: 18px; color: #4a2070; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 18px; color: #4a2070; }
        .muted { color: #666; font-size: 10px; }
        .box { margin: 10px 0; padding: 8px 10px; border: 1px solid #ddd; background: #f9f6fc; }
        .warning { margin: 10px 0; padding: 8px 10px; border: 1px solid #f59e0b; background: #fef3c7; font-weight: bold; }
        .alert { margin: 10px 0; padding: 8px 10px; border: 1px solid #ef4444; background: #fee2e2; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f5f0fa; width: 32%; }
        thead th { width: auto; }
        .disclaimer { color: #555; font-size: 9px; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 9px; color: #555; }
    </style>
</head>
<body>
    @php
        $score = fn ($value) => $value === null ? '—' : str_replace('.', ',', (string) $value);
        $orGap = fn ($value) => filled($value) ? $value : 'Não informado';
        $eligible = $snapshot['eligible'];
        $registration = $snapshot['registration'];
        $inventoryLabels = \App\Services\Psychosocial\PgrSubsidySnapshotBuilder::INVENTORY_FIELDS;
    @endphp

    <h1>Subsídio AEP/PGR — riscos psicossociais</h1>
    <p class="muted">
        Empresa: {{ $snapshot['company']['name'] }} &mdash; Campanha: {{ $snapshot['survey']['title'] }}
        @if($snapshot['survey']['starts_at'] || $snapshot['survey']['ends_at'])
            &mdash; Período: {{ $snapshot['survey']['starts_at'] ? \Illuminate\Support\Carbon::parse($snapshot['survey']['starts_at'])->format('d/m/Y') : '—' }}
            a {{ $snapshot['survey']['ends_at'] ? \Illuminate\Support\Carbon::parse($snapshot['survey']['ends_at'])->format('d/m/Y') : '—' }}
        @endif
    </p>
    <p class="muted">
        Liberado em {{ \Illuminate\Support\Carbon::parse($snapshot['released_at'])->format('d/m/Y H:i') }} &mdash;
        {{ empty($validation) ? 'Aguardando validação do responsável técnico.' : 'Versão validada; o conteúdo abaixo é o mesmo da versão liberada.' }}
    </p>
    <p>Este documento reúne os achados da pesquisa como subsídio para a avaliação ergonômica preliminar (AEP) e para o inventário de riscos do PGR. Não substitui a avaliação do responsável técnico.</p>

    @if($snapshot['polarity_warning'])
        <div class="warning">{{ $snapshot['polarity_warning'] }}</div>
    @endif

    @if($eligible['alert'])
        <div class="alert">{{ $eligible['alert'] }}</div>
    @endif

    <h2>Identificação e cadastro técnico</h2>
    <table>
        <tbody>
            <tr><th>Unidade</th><td>{{ $orGap($registration['unit_name']) }}</td></tr>
            <tr><th>Processo</th><td>{{ $orGap($registration['process_name']) }}</td></tr>
            <tr><th>Razão social</th><td>{{ $orGap($registration['legal_name_snapshot']) }}</td></tr>
            <tr><th>CNPJ</th><td>{{ $orGap($registration['cnpj_snapshot']) }}</td></tr>
            <tr><th>Total de elegíveis</th><td>{{ $eligible['value_label'] }}@if($eligible['hint']) &mdash; {{ $eligible['hint'] }}@endif</td></tr>
            <tr><th>Origem dos elegíveis</th><td>{{ $orGap($registration['eligible_source']) }}</td></tr>
            <tr>
                <th>Respondentes</th>
                <td>
                    {{ $snapshot['respondents'] }}
                    @if($eligible['participation_label']) ({{ $eligible['participation_label'] }}) @endif
                    @if($eligible['respondents_hint']) &mdash; {{ $eligible['respondents_hint'] }} @endif
                </td>
            </tr>
            <tr><th>Grupos de exposição</th><td>{{ $registration['exposure_groups'] ? implode(', ', $registration['exposure_groups']) : 'Não informado' }}</td></tr>
            <tr><th>Resumo da AEP</th><td>{{ $orGap($registration['aep_summary']) }}</td></tr>
            <tr><th>Resumo da observação</th><td>{{ $orGap($registration['observation_summary']) }}</td></tr>
            <tr><th>Controles existentes</th><td>{{ $orGap($registration['existing_controls']) }}</td></tr>
            <tr><th>Responsável técnico</th><td>{{ $orGap($registration['technical_responsible_name']) }}</td></tr>
            <tr><th>Função</th><td>{{ $orGap($registration['technical_responsible_role']) }}</td></tr>
            <tr><th>Registro</th><td>{{ $orGap($registration['technical_responsible_registration']) }}</td></tr>
            <tr><th>Organização que aprova</th><td>{{ $orGap($registration['approving_organization_name']) }}</td></tr>
        </tbody>
    </table>

    <h2>Índice geral</h2>
    <div class="box">
        @if($snapshot['overall'])
            {{ $score($snapshot['overall']['score']) }} ({{ $snapshot['overall']['band_label'] }}) &mdash; n = {{ $snapshot['overall']['n'] }}
            <br><span class="disclaimer">{{ $snapshot['band_disclaimer'] }}</span>
        @else
            Resultados ainda não calculados.
        @endif
    </div>
    <p class="muted"><strong>Legenda ({{ $snapshot['method']['code'] }}):</strong> {{ $snapshot['method']['legend'] }}</p>

    <h2>Achados da pesquisa</h2>
    <table>
        <thead>
            <tr><th>Achado da pesquisa</th><th>Evidência</th><th>Decisão solicitada</th></tr>
        </thead>
        <tbody>
            @forelse($snapshot['findings'] as $finding)
                <tr>
                    <td>
                        <strong>{{ $finding['cut'] }}</strong>: {{ $score($finding['score']) }} &mdash; {{ $finding['band_label'] }} &mdash; n = {{ $finding['n'] }}
                        <br><span class="disclaimer">{{ $snapshot['band_disclaimer'] }}</span>
                    </td>
                    <td>n gravado = {{ $finding['evidence_n'] }}</td>
                    <td>{{ $finding['decision_requested'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Resultados ainda não calculados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Setores</h2>
    <p class="muted">Setores com menos de {{ $snapshot['min_group_size'] }} respondentes não são exibidos, e outros setores pequenos podem ser ocultados para impedir a identificação por subtração.</p>
    @if(count($snapshot['sectors']) === 0)
        <p>Nenhum setor pode ser exibido nesta campanha.</p>
    @else
        <table>
            <thead>
                <tr><th>Setor</th><th>n</th><th>Índice</th><th>Faixa</th></tr>
            </thead>
            <tbody>
                @foreach($snapshot['sectors'] as $sector)
                    <tr>
                        <td>{{ $sector['department_name'] }}</td>
                        <td>{{ $sector['n'] }}</td>
                        <td>{{ $score($sector['score']) }}</td>
                        <td>{{ $sector['band_label'] }}<br><span class="disclaimer">{{ $snapshot['band_disclaimer'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Medidas</h2>
    @if(count($snapshot['measures']) === 0)
        <p>{{ $snapshot['measures_empty_text'] }}</p>
    @else
        <table>
            <thead>
                <tr><th>Fator</th><th>Medida</th><th>Status</th><th>Responsável</th><th>Prazo sugerido</th></tr>
            </thead>
            <tbody>
                @foreach($snapshot['measures'] as $measure)
                    <tr>
                        <td>{{ $measure['factor_label'] }}</td>
                        <td>{{ $measure['measure_description'] }}</td>
                        <td>{{ $measure['status_label'] }}</td>
                        <td>{{ $orGap($measure['responsible_text']) }}</td>
                        <td>{{ $measure['suggested_due_date'] ? \Illuminate\Support\Carbon::parse($measure['suggested_due_date'])->format('d/m/Y') : 'Não informado' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Inventário de riscos (a preencher pelo responsável técnico)</h2>
    <table>
        <tbody>
            @foreach($inventoryLabels as $key => $label)
                <tr><th>{{ $label }}</th><td>{{ $orGap($snapshot['inventory'][$key] ?? null) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    @include('psychosocial.partials.validation', ['validation' => $validation ?? null])

    <div class="footer">{{ $snapshot['reference'] }}</div>
</body>
</html>
