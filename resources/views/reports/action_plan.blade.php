<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Plano de ação - {{ $survey->title }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; line-height: 1.45; }
        h1 { font-size: 18px; color: #4a2070; margin: 8px 0 4px; }
        h2 { font-size: 13px; color: #4a2070; margin: 18px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        h3 { font-size: 12px; color: #632a7e; margin: 12px 0 6px; }
        .muted { color: #64748b; font-size: 10px; }
        .header { text-align: center; margin-bottom: 12px; }
        .header img { max-height: 48px; width: auto; }
        .meta { margin-bottom: 10px; }
        .score-box {
            margin: 10px 0;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .score-big { font-size: 28px; font-weight: bold; color: #4a2070; }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        .legend { margin: 6px 0 10px; font-size: 9px; color: #64748b; }
        .legend span { margin-right: 10px; }
        .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 3px; vertical-align: middle; }
        .two-col { width: 100%; }
        .two-col td { vertical-align: top; }
        .dim-list { width: 100%; border-collapse: collapse; }
        .dim-list td { padding: 6px 8px; border: 1px solid #e2e8f0; font-size: 10px; }
        .dim-score {
            display: inline-block;
            min-width: 36px;
            text-align: center;
            padding: 2px 6px;
            border-radius: 4px;
            color: #fff;
            font-weight: bold;
            font-size: 10px;
        }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: top; font-size: 10px; }
        table.data th { background: #f1f5f9; color: #334155; text-align: left; }
        .bar-row { margin: 4px 0 8px; }
        .bar-label { font-size: 10px; margin-bottom: 2px; }
        .bar-track { background: #e2e8f0; height: 12px; width: 100%; }
        .bar-fill { height: 12px; }
        .likert-track { background: #e2e8f0; height: 10px; width: 55%; display: inline-block; vertical-align: middle; }
        .likert-fill { height: 10px; background: #7b4fa2; }
        .heatmap { text-align: center; }
        .heat-cell {
            display: inline-block;
            min-width: 34px;
            padding: 3px 5px;
            border-radius: 3px;
            font-size: 9px;
            font-family: DejaVu Sans Mono, monospace;
        }
        .opinion { margin-top: 6px; padding: 8px; border: 1px solid #e2e8f0; background: #fafafa; font-size: 10px; }
        .opinion p { margin: 0 0 6px; }
        .notice {
            margin-top: 14px;
            font-size: 9px;
            padding: 8px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
        }
        .radar-wrap { text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $actionPlan = $scenarioConfig['action_plan'] ?? [];
    @endphp

    <div class="header">
        @if(!empty($logoBase64))
            <img src="{{ $logoBase64 }}" alt="Talents">
        @endif
    </div>

    <h1>Plano de ação NR-1</h1>
    <div class="meta">
        <p class="muted">Empresa: {{ $survey->company->name }} — Campanha: {{ $survey->title }}</p>
        <p class="muted">Data de emissão: {{ now()->format('d/m/Y') }}</p>
        @if(!empty($scenarioConfig['short_label']))
            <p class="muted">Cenário: {{ $scenarioConfig['short_label'] }}</p>
        @endif
    </div>

    <h2>Indicador geral de risco (1–5)</h2>
    @if($overall)
        <div class="score-box">
            <span class="score-big">{{ number_format($overall['average_score'], 2, ',', '.') }}</span>
            &nbsp;
            <span class="badge" style="background: {{ $riskBg($overall['risk_level']) }}; color: {{ $riskColor($overall['risk_level']) }};">
                {{ $healthLevelLabel($overall['risk_level']) }}
            </span>
            <p class="muted" style="margin: 6px 0 0;">
                Respondentes: {{ $overall['respondent_count'] }}
                · Faixas: 1,00–2,33 favorável · 2,34–3,66 intermediário · 3,67–5,00 elevado
            </p>
        </div>
    @else
        <p class="muted">Indicador geral ainda não calculado.</p>
    @endif

    @if(count($bySection) > 0)
        <h2>Dimensões</h2>
        <div class="legend">
            <span><span class="dot" style="background:#10b981;"></span> Favorável (1,00–2,33)</span>
            <span><span class="dot" style="background:#f59e0b;"></span> Intermediário (2,34–3,66)</span>
            <span><span class="dot" style="background:#ef4444;"></span> Elevado (3,67–5,00)</span>
        </div>

        <table class="two-col">
            <tr>
                <td style="width: 55%;">
                    @if($radarSvg)
                        <div class="radar-wrap">{!! $radarSvg !!}</div>
                    @endif
                </td>
                <td style="width: 45%; padding-left: 8px;">
                    <table class="dim-list">
                        @foreach($bySection as $row)
                            <tr style="background: {{ $riskBg($row['risk_level']) }};">
                                <td>{{ $row['section_title'] }}</td>
                                <td style="width: 52px; text-align: right;">
                                    <span class="dim-score" style="background: {{ $riskColor($row['risk_level']) }};">
                                        {{ number_format($row['average_score'], 2, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @endif

    @if(count($departmentParticipation) > 0)
        <h2>Participação por setor</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Setor</th>
                    <th style="width: 20%;">Respondentes</th>
                    <th style="width: 28%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($departmentParticipation as $row)
                    <tr>
                        <td>{{ $row['department_name'] }}</td>
                        <td>{{ $row['respondent_count'] }}</td>
                        <td>
                            @if($row['meets_minimum'])
                                Exibido nos gráficos
                            @else
                                Aguardando mínimo (1)
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(count($deptOveralls) > 0)
        <h2>Risco por setor (média geral)</h2>
        @foreach($deptOveralls as $row)
            @php
                $pct = max(0, min(100, (($row['average_score'] - 1) / 4) * 100));
            @endphp
            <div class="bar-row">
                <div class="bar-label">
                    <strong>{{ $row['department_name'] }}</strong>
                    — {{ number_format($row['average_score'], 2, ',', '.') }}
                    ({{ $healthLevelLabel($row['risk_level']) }})
                </div>
                <div class="bar-track">
                    <div class="bar-fill" style="width: {{ number_format($pct, 1, '.', '') }}%; background: {{ $riskColor($row['risk_level']) }};"></div>
                </div>
            </div>
        @endforeach
    @endif

    @if(count($deptOveralls) > 0 && count($bySection) > 0 && count($deptSectionsByDepartment) > 0)
        <h2>Tabela de risco por setor e dimensão</h2>
        <table class="data heatmap">
            <thead>
                <tr>
                    <th style="text-align: left;">Setor</th>
                    @foreach($bySection as $sec)
                        <th style="text-align: center; font-size: 8px;">{{ $sec['section_title'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($deptOveralls as $dept)
                    <tr>
                        <td style="text-align: left; font-weight: bold;">{{ $dept['department_name'] }}</td>
                        @foreach($bySection as $sec)
                            @php
                                $cell = $heatmapCell(
                                    $deptSectionsByDepartment,
                                    (int) $dept['department_id'],
                                    (int) $sec['survey_template_section_id']
                                );
                            @endphp
                            <td>
                                @if($cell)
                                    <span class="heat-cell" style="background: {{ $riskBg($cell['risk_level']) }}; color: {{ $riskColor($cell['risk_level']) }};">
                                        {{ number_format($cell['average_score'], 2, ',', '.') }}
                                    </span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(count($questionDistributions) > 0)
        <div class="page-break"></div>
        <h2>Detalhamento por pergunta</h2>
        <p class="muted">Quantidade de votos por opção da escala, no total da campanha.</p>

        @foreach($questionDistributions as $section)
            <h3>{{ $section['section_title'] }}</h3>
            @foreach($section['questions'] as $question)
                <p style="margin: 10px 0 2px;"><strong>{{ $question['body'] }}</strong></p>
                <p class="muted" style="margin: 0 0 6px;">
                    {{ $question['total'] }} resposta{{ $question['total'] === 1 ? '' : 's' }}
                    · Escala de {{ ($question['response_scale'] ?? 'frequency') === 'agreement' ? 'concordância' : 'frequência' }}
                </p>
                @if($question['total'] > 0)
                    @foreach([1, 2, 3, 4, 5] as $value)
                        @php
                            $count = (int) ($question['counts'][$value] ?? 0);
                            $pct = $question['total'] > 0 ? round(($count / $question['total']) * 100) : 0;
                            $barPct = $question['total'] > 0
                                ? max(($count / $question['total']) * 100, $count > 0 ? 2 : 0)
                                : 0;
                        @endphp
                        <div style="margin: 3px 0;">
                            <span style="display: inline-block; width: 28%; font-size: 9px; color: #475569;">
                                {{ $likertLabel($question['response_scale'] ?? 'frequency', $value) }}
                            </span>
                            <span class="likert-track">
                                <span class="likert-fill" style="display: inline-block; width: {{ number_format($barPct, 1, '.', '') }}%;"></span>
                            </span>
                            <span style="font-size: 9px; margin-left: 6px;">{{ $count }} ({{ $pct }}%)</span>
                        </div>
                    @endforeach
                @else
                    <p class="muted">Nenhuma resposta ainda.</p>
                @endif
            @endforeach
        @endforeach
    @endif

    <h2>Insights</h2>
    <ul>
        @forelse($insights as $insight)
            <li>{{ is_object($insight) ? ($insight->message ?? '') : ($insight['message'] ?? '') }}</li>
        @empty
            <li class="muted">Nenhum insight gerado ainda.</li>
        @endforelse
    </ul>

    @if(!empty($technicalOpinion))
        <h2>Parecer técnico</h2>
        <div class="opinion">
            {!! $technicalOpinion !!}
        </div>
    @endif

    @if($includeActions ?? true)
        <h2>Ações</h2>
        <p>{{ $actionPlan['intro'] ?? 'Plano de ação derivado dos resultados da pesquisa psicossocial.' }}</p>

        <table class="data">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 28%;">Ação</th>
                    <th>Descrição</th>
                    <th style="width: 14%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->description ?? '—' }}</td>
                        <td>{{ match($item->status) {
                            'done' => 'Concluída',
                            'in_progress' => 'Em andamento',
                            default => 'Pendente',
                        } }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Nenhuma ação cadastrada. O administrador Talents deve publicar o plano de ação na plataforma.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="notice">
            <strong>Aviso:</strong> este plano deve ser validado pela equipe de SST e integrado ao PGR da organização, com responsáveis e prazos definidos internamente.
        </p>
    @endif
</body>
</html>
