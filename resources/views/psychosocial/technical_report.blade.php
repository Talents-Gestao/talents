<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Inventário de Riscos Psicossocial - {{ $snapshot['survey']['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #2a1042; }
        h1 { font-size: 18px; color: #4a2070; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 18px; color: #4a2070; }
        h3 { font-size: 11px; margin: 10px 0 4px; color: #4a2070; }
        .muted { color: #666; font-size: 9.5px; }
        .box { margin: 10px 0; padding: 8px 10px; border: 1px solid #ddd; background: #f9f6fc; }
        .warning { margin: 10px 0; padding: 8px 10px; border: 1px solid #f59e0b; background: #fef3c7; font-weight: bold; }
        .alert { margin: 10px 0; padding: 8px 10px; border: 1px solid #ef4444; background: #fee2e2; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f5f0fa; font-size: 9.5px; }
        td.num { text-align: right; white-space: nowrap; }
        .small td, .small th { font-size: 8.5px; padding: 3px; }
        .footer { margin-top: 22px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 9px; color: #555; }
        .header { text-align: center; margin-bottom: 12px; }
        .header img { max-height: 48px; width: auto; }
        table.id th { width: 32%; }
    </style>
</head>
<body>
    @php
        $orGap = fn ($value) => filled($value) ? $value : 'Não informado';
        $date = fn ($value) => filled($value) ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '—';
        $registration = $snapshot['registration'];
        $eligible = $snapshot['eligible'];
        $meta = $meta ?? [];
        $company = $snapshot['company'] ?? [];
        $legalName = $registration['legal_name_snapshot'] ?? ($company['legal_name'] ?? $company['name'] ?? null);
        $cnpj = $registration['cnpj_snapshot'] ?? ($company['cnpj'] ?? null);
        $addressParts = array_filter([
            $company['address_street'] ?? null,
            $company['address_neighborhood'] ?? null,
            filled($company['address_city'] ?? null) || filled($company['address_state'] ?? null)
                ? trim(($company['address_city'] ?? '').(filled($company['address_state'] ?? null) ? '/'.$company['address_state'] : ''))
                : null,
            filled($company['address_zip'] ?? null) ? 'CEP '.$company['address_zip'] : null,
        ], fn ($value) => filled($value));
        $identification = array_filter([
            'Razão social' => $legalName,
            'Nome fantasia' => $company['name'] ?? null,
            'CNPJ' => $cnpj,
            'Endereço' => $addressParts !== [] ? implode(' · ', $addressParts) : null,
            'Estabelecimento / unidade' => $registration['unit_name'] ?? null,
            'Processo' => $registration['process_name'] ?? null,
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
    @endphp

    @if(!empty($logoBase64))
        <div class="header">
            <img src="{{ $logoBase64 }}" alt="Talents">
        </div>
    @endif

    <h1>Inventário de Riscos Psicossocial</h1>
    <p class="muted">Fatores psicossociais relacionados ao trabalho · Pesquisa Talents NR-1</p>
    <p class="muted">
        Campanha: {{ $snapshot['survey']['title'] }}
        @if($snapshot['survey']['starts_at'] || $snapshot['survey']['ends_at'])
            · Período: {{ $date($snapshot['survey']['starts_at']) }} a {{ $date($snapshot['survey']['ends_at']) }}
        @endif
        · Gerado em {{ \Illuminate\Support\Carbon::parse($snapshot['released_at'])->format('d/m/Y H:i') }}
        @if(! empty($meta['public_identifier'])) · Versão {{ $meta['version_number'] }} ({{ $meta['public_identifier'] }}) @endif
    </p>

    @if($snapshot['polarity_warning'])
        <div class="warning">{{ $snapshot['polarity_warning'] }}</div>
    @endif
    @if($eligible['alert'])
        <div class="alert">{{ $eligible['alert'] }}</div>
    @endif

    <h2>1. Dados da empresa</h2>
    <table class="id">
        <tbody>
            @forelse($identification as $label => $value)
                <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
            @empty
                <tr><th>Organização</th><td>{{ $orGap($company['name'] ?? null) }}</td></tr>
            @endforelse
            <tr>
                <th>Respondentes</th>
                <td>
                    {{ $snapshot['respondents'] }}
                    @if(! empty($eligible['participation_label'])) ({{ $eligible['participation_label'] }}) @endif
                </td>
            </tr>
            <tr>
                <th>Total de elegíveis</th>
                <td>
                    {{ $eligible['value_label'] ?? ($eligible['count'] ?? 'Não informado') }}
                    @if(! empty($eligible['hint'])) — {{ $eligible['hint'] }} @endif
                </td>
            </tr>
        </tbody>
    </table>

    <h2>2. Objetivo e metodologia</h2>
    <p>
        O levantamento identifica percepções agregadas de condições de organização do trabalho relacionadas a fatores psicossociais,
        para subsidiar a gestão preventiva e o processo de GRO/PGR. O instrumento tem {{ $snapshot['instrument']['item_count'] }} afirmações
        em {{ $snapshot['instrument']['dimension_count'] }} dimensões, respondidas em escala de frequência
        (<strong>Nunca, Raramente, Às vezes, Frequentemente e Sempre</strong>), pontuadas de 1 a 5. Enunciados desfavoráveis mantêm a pontuação direta;
        enunciados favoráveis têm pontuação invertida (6 menos o valor da resposta). Maior índice indica maior percepção desfavorável.
        Cada dimensão é a média dos escores válidos de suas perguntas; o índice geral é a média de todas as perguntas respondidas.
    </p>
    <p>
        <strong>Faixas de interpretação:</strong> {{ $snapshot['method']['legend'] }}. {{ $snapshot['band_disclaimer'] }}
        Os limites são critérios internos de triagem da Talents, não uma classificação ocupacional automática.
        Os percentuais favoráveis usam Frequentemente/Sempre nas afirmações positivas e Nunca/Raramente nas desfavoráveis.
        Os indicadores são agregados; grupos pequenos são reunidos em «Outros Setores» e a base mínima de {{ $snapshot['min_group_size'] }} respondentes por grupo é respeitada.
    </p>

    <h2>3. Participação e resultados</h2>
    <p>
        <strong>Respostas completas:</strong> {{ $snapshot['respondents'] }}
        · <strong>Elegíveis:</strong> {{ $eligible['count'] ?? 'Não informado' }}
        @if(! empty($eligible['participation_percent_label'])) · <strong>Adesão:</strong> {{ $eligible['participation_percent_label'] }} @endif
        @if($snapshot['overall'])
            · <strong>Índice geral:</strong> {{ $snapshot['overall']['score_label'] }} / 5 ({{ $snapshot['overall']['band_label'] }})
        @endif
    </p>

    @if(! $snapshot['has_results'])
        <p>Os resultados desta campanha ainda não foram calculados.</p>
    @else
        <table>
            <thead><tr><th>Dimensão</th><th>N</th><th>Índice</th><th>Leitura</th></tr></thead>
            <tbody>
            @foreach($snapshot['dimensions'] as $dimension)
                <tr>
                    <td>{{ $dimension['title'] }}</td>
                    <td class="num">{{ $dimension['n'] }}</td>
                    <td class="num">{{ $dimension['score_label'] }}</td>
                    <td>{{ $dimension['band_label'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if(count($snapshot['groups']['items']))
        <h3>Participação por grupo</h3>
        <table>
            <thead><tr><th>Grupo</th><th>Respostas</th><th>% do total</th></tr></thead>
            <tbody>
            @foreach($snapshot['groups']['items'] as $group)
                <tr>
                    <td>{{ $group['name'] }}@if(! empty($group['composition'])) <span class="muted">(reúne: {{ implode(', ', $group['composition']) }})</span>@endif</td>
                    <td class="num">{{ $group['n'] }}</td>
                    <td class="num">{{ $group['percent_label'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    @if($snapshot['groups']['note'])<p class="muted">{{ $snapshot['groups']['note'] }}</p>@endif

    @if(count($snapshot['sectors']))
        <h3>Setores com resultado publicável</h3>
        <table>
            <thead><tr><th>Setor</th><th>N</th><th>Índice</th><th>Leitura</th></tr></thead>
            <tbody>
            @foreach($snapshot['sectors'] as $sector)
                <tr>
                    <td>{{ $sector['department_name'] }}</td>
                    <td class="num">{{ $sector['n'] }}</td>
                    <td class="num">{{ $sector['score_label'] }}</td>
                    <td>{{ $sector['band_label'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>4. Pontos fortes relatados</h2>
    @if(count($snapshot['strengths']))
        <ul>
            @foreach($snapshot['strengths'] as $strength)
                <li><strong>{{ $strength['favorable_pct_label'] }}</strong> em «{{ $strength['body'] }}» (N = {{ $strength['n'] }})</li>
            @endforeach
        </ul>
    @else
        <p>Ainda não há respostas para destacar pontos fortes.</p>
    @endif

    <h2>5. Principais situações de atenção</h2>
    @if(count($snapshot['attention']))
        <ol>
            @foreach($snapshot['attention'] as $dimension)
                <li>{{ $dimension['title'] }} — índice {{ $dimension['score_label'] ?? $dimension['score'] }} ({{ $dimension['band_label'] }}; N = {{ $dimension['n'] }})</li>
            @endforeach
        </ol>
    @else
        <p>Nenhuma dimensão ficou em atenção nesta pesquisa.</p>
    @endif
    <p class="muted">{{ $snapshot['priority_note'] }}</p>

    <h2>6. Plano de ação e acompanhamento</h2>
    @if(count($snapshot['plan']) === 0)
        <p>{{ $snapshot['measures_empty_text'] }}</p>
    @else
        <table class="small">
            <thead>
            <tr><th>Fator</th><th>Medida</th><th>Responsável</th><th>Prazo</th><th>Status</th><th>Evidências e eficácia</th></tr>
            </thead>
            <tbody>
            @foreach($snapshot['plan'] as $measure)
                <tr>
                    <td>{{ $measure['factor_label'] }}</td>
                    <td>
                        {{ $measure['measure_description'] }}
                        @if($measure['objective'])<br><strong>Objetivo:</strong> {{ $measure['objective'] }}@endif
                        @if($measure['expected_result'])<br><strong>Resultado esperado:</strong> {{ $measure['expected_result'] }}@endif
                        @if($measure['indicator'])<br><strong>Indicador:</strong> {{ $measure['indicator'] }}@endif
                    </td>
                    <td>
                        <strong>Sugerido:</strong> {{ $orGap($measure['suggested_responsible']) }}<br>
                        <strong>Confirmado:</strong> {{ $measure['confirmed_responsible'] ?: 'Ainda não confirmado' }}
                    </td>
                    <td>
                        <strong>Sugerido:</strong> {{ $date($measure['suggested_due_date']) }}<br>
                        <strong>Confirmado:</strong> {{ $measure['confirmed_due_date'] ? $date($measure['confirmed_due_date']).' (aprovado em '.$date($measure['confirmed_at']).')' : 'Ainda não confirmado' }}
                    </td>
                    <td>{{ $measure['status_label'] }}</td>
                    <td>
                        @if($measure['evidence_count'] > 0)
                            @foreach($measure['evidences'] as $evidence)
                                • {{ $evidence['description'] }}@if($evidence['file_name']) ({{ $evidence['file_name'] }})@endif<br>
                            @endforeach
                        @else
                            Sem evidência registrada.
                        @endif
                        @if($measure['effectiveness_result'])
                            <strong>Eficácia:</strong> {{ $measure['effectiveness_result'] }} ({{ $date($measure['effectiveness_checked_at']) }}) — {{ $measure['effectiveness_note'] }}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    <p class="muted">{{ $snapshot['plan_note'] }}</p>

    <p class="footer">{{ $snapshot['reference'] }}</p>
</body>
</html>
