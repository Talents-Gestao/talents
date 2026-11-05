<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Subsídio AEP/PGR - {{ $snapshot['survey']['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #2a1042; }
        h1 { font-size: 18px; color: #4a2070; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 16px; margin-bottom: 6px; color: #4a2070; }
        h3 { font-size: 11px; margin: 10px 0 4px; color: #4a2070; }
        .muted { color: #666; font-size: 10px; }
        .box { margin: 8px 0; padding: 8px 10px; border: 1px solid #ddd; background: #f9f6fc; }
        .warning { margin: 10px 0; padding: 8px 10px; border: 1px solid #f59e0b; background: #fef3c7; font-weight: bold; }
        .alert { margin: 10px 0; padding: 8px 10px; border: 1px solid #ef4444; background: #fee2e2; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #ddd; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #f5f0fa; width: 32%; }
        thead th { width: auto; }
        .disclaimer { color: #555; font-size: 9px; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 9px; color: #555; }
        .header { text-align: center; margin-bottom: 12px; }
        .header img { max-height: 48px; width: auto; }
        ul.compact { margin: 4px 0 0 16px; padding: 0; }
        ul.compact li { margin: 2px 0; }
    </style>
</head>
<body>
    @php
        $score = fn ($value) => $value === null ? '—' : str_replace('.', ',', (string) $value);
        $eligible = $snapshot['eligible'];
        $registration = $snapshot['registration'] ?? [];
        $pgr = $snapshot['pgr'] ?? [];
        $inventoryLabels = \App\Services\Psychosocial\PgrSubsidySnapshotBuilder::INVENTORY_FIELDS;
        $legalName = $registration['legal_name_snapshot'] ?? ($snapshot['company']['name'] ?? null);
        $identification = array_filter([
            'Unidade' => $registration['unit_name'] ?? null,
            'Processo' => $registration['process_name'] ?? null,
            'Razão social' => $legalName,
            'CNPJ' => $registration['cnpj_snapshot'] ?? null,
            'Origem dos elegíveis' => $registration['eligible_source'] ?? null,
            'Grupos de exposição' => ! empty($registration['exposure_groups'])
                ? implode(', ', $registration['exposure_groups'])
                : null,
            'Resumo da AEP' => $registration['aep_summary'] ?? null,
            'Resumo da observação' => $registration['observation_summary'] ?? null,
            'Controles existentes' => $registration['existing_controls'] ?? null,
            'Responsável técnico' => $registration['technical_responsible_name'] ?? null,
            'Função' => $registration['technical_responsible_role'] ?? null,
            'Registro' => $registration['technical_responsible_registration'] ?? null,
            'Organização que aprova' => $registration['approving_organization_name'] ?? null,
        ], fn ($value) => filled($value));
        $pendingLabels = [
            'unit_name' => 'Unidade',
            'process_name' => 'Processo',
            'eligible_source' => 'Origem dos elegíveis',
            'exposure_groups' => 'Grupos de exposição',
            'aep_summary' => 'Resumo da AEP',
            'observation_summary' => 'Resumo da observação',
            'existing_controls' => 'Controles existentes',
            'technical_responsible_name' => 'Responsável técnico',
            'technical_responsible_role' => 'Função',
            'technical_responsible_registration' => 'Registro',
            'approving_organization_name' => 'Organização que aprova',
        ];
        $pending = [];
        foreach ($pendingLabels as $field => $label) {
            $value = $registration[$field] ?? null;
            if ($field === 'exposure_groups') {
                if (empty($value)) {
                    $pending[] = $label;
                }
                continue;
            }
            if (! filled($value)) {
                $pending[] = $label;
            }
        }
        if (! filled($eligible['count'] ?? null)) {
            $pending[] = 'Total de elegíveis';
        }
        $priorities = $pgr['priorities'] ?? [];
        $sectorPriorities = $pgr['sector_priorities'] ?? [];
    @endphp

    @if(!empty($logoBase64))
        <div class="header">
            <img src="{{ $logoBase64 }}" alt="Talents">
        </div>
    @endif

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
    <p>{{ $pgr['purpose'] ?? 'Este documento reúne os achados da pesquisa como subsídio para a AEP e para o inventário de riscos do PGR. Não substitui a avaliação do responsável técnico.' }}</p>

    @if($snapshot['polarity_warning'])
        <div class="warning">{{ $snapshot['polarity_warning'] }}</div>
    @endif

    @if($eligible['alert'])
        <div class="alert">{{ $eligible['alert'] }}</div>
    @endif

    <h2>1. Identificação</h2>
    <table>
        <tbody>
            @foreach($identification as $label => $value)
                <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
            @endforeach
            <tr>
                <th>Respondentes</th>
                <td>
                    {{ $snapshot['respondents'] }}
                    @if($eligible['participation_label']) ({{ $eligible['participation_label'] }}) @endif
                </td>
            </tr>
            <tr>
                <th>Total de elegíveis</th>
                <td>
                    {{ $eligible['value_label'] }}
                    @if($eligible['hint']) &mdash; {{ $eligible['hint'] }} @endif
                </td>
            </tr>
        </tbody>
    </table>
    @if(count($pending) > 0)
        <p class="muted" style="margin-top: 6px;">
            <strong>Pendências do cadastro técnico:</strong> {{ implode('; ', $pending) }}.
            Complete o cadastro da campanha e liberar uma nova versão para incorporar esses campos.
        </p>
    @endif

    <h2>2. Panorama da pesquisa</h2>
    <div class="box">
        @if($snapshot['overall'])
            <strong>Índice geral:</strong>
            {{ $score($snapshot['overall']['score']) }} ({{ $snapshot['overall']['band_label'] }})
            &mdash; n = {{ $snapshot['overall']['n'] }}
        @else
            Resultados ainda não calculados.
        @endif
        <br><span class="disclaimer">{{ $snapshot['band_disclaimer'] }}</span>
        <br><span class="muted"><strong>Legenda ({{ $snapshot['method']['code'] }}):</strong> {{ $snapshot['method']['legend'] }}</span>
    </div>

    @if(! empty($pgr['scenario']))
        <div class="box">
            <strong>{{ $pgr['scenario']['label'] }}</strong>
            @if($pgr['scenario']['body'])
                <br>{{ $pgr['scenario']['body'] }}
            @endif
            @if($pgr['scenario']['conduct'])
                <br><span class="muted">{{ $pgr['scenario']['conduct'] }}</span>
            @endif
        </div>
    @endif

    <h2>3. Prioridades para o inventário</h2>
    <p class="disclaimer">{{ $snapshot['band_disclaimer'] }} Dimensões e setores em Atenção/Moderado ou Maior sinalização/Grave, ordenados do maior índice para o menor.</p>
    @if(count($priorities) === 0 && count($sectorPriorities) === 0)
        <p>Nenhuma dimensão ou setor acima da faixa leve nesta campanha (após privacidade de grupos pequenos).</p>
    @else
        @if(count($priorities) > 0)
            <h3>Dimensões a avaliar</h3>
            <table>
                <thead>
                    <tr>
                        <th>Dimensão</th>
                        <th>Índice</th>
                        <th>Faixa</th>
                        <th>Decisão solicitada ao responsável técnico</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($priorities as $finding)
                        <tr>
                            <td>{{ $finding['cut'] }}</td>
                            <td>{{ $score($finding['score']) }} (n = {{ $finding['n'] }})</td>
                            <td>{{ $finding['band_label'] }}</td>
                            <td>{{ $finding['decision_requested'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if(count($sectorPriorities) > 0)
            <h3>Setores a avaliar</h3>
            <table>
                <thead>
                    <tr>
                        <th>Setor</th>
                        <th>Índice</th>
                        <th>Faixa</th>
                        <th>Decisão solicitada ao responsável técnico</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sectorPriorities as $sector)
                        <tr>
                            <td>{{ $sector['department_name'] }}</td>
                            <td>{{ $score($sector['score']) }} (n = {{ $sector['n'] }})</td>
                            <td>{{ $sector['band_label'] }}</td>
                            <td>{{ $sector['decision_requested'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <h2>4. Achados por dimensão</h2>
    <p class="disclaimer">{{ $snapshot['band_disclaimer'] }}</p>
    <table>
        <thead>
            <tr>
                <th>Dimensão</th>
                <th>Índice / faixa</th>
                <th>Evidência</th>
                <th>Decisão solicitada</th>
            </tr>
        </thead>
        <tbody>
            @forelse($snapshot['findings'] as $finding)
                <tr>
                    <td>{{ $finding['cut'] }}</td>
                    <td>{{ $score($finding['score']) }} — {{ $finding['band_label'] }}</td>
                    <td>{{ $finding['evidence_label'] ?? ('n = '.($finding['evidence_n'] ?? $finding['n'])) }}</td>
                    <td>{{ $finding['decision_requested'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Resultados ainda não calculados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>5. Setores (visão completa)</h2>
    <p class="muted">Setores com menos de {{ $snapshot['min_group_size'] }} respondentes não são exibidos; setores pequenos podem ser ocultados para impedir identificação por subtração.</p>
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
                        <td>{{ $sector['band_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>6. Medidas já registradas</h2>
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
                        <td>{{ filled($measure['responsible_text'] ?? null) ? $measure['responsible_text'] : '—' }}</td>
                        <td>{{ $measure['suggested_due_date'] ? \Illuminate\Support\Carbon::parse($measure['suggested_due_date'])->format('d/m/Y') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>7. Inventário de riscos (responsável técnico)</h2>
    <p class="muted">{{ $pgr['inventory_guidance'] ?? 'Campos abaixo ficam em branco para o responsável técnico.' }}</p>
    <table>
        <tbody>
            @foreach($inventoryLabels as $key => $label)
                <tr><th>{{ $label }}</th><td></td></tr>
            @endforeach
        </tbody>
    </table>

    @include('psychosocial.partials.validation', ['validation' => $validation ?? null])

    <div class="footer">{{ $snapshot['reference'] }}</div>
</body>
</html>
