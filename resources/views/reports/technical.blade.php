<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório técnico - {{ $survey->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; color: #4a2070; }
        h2 { font-size: 14px; margin-top: 16px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>Relatório técnico (RH / SESMT)</h1>
    <p class="muted">Empresa: {{ $survey->company->name }} &mdash; Campanha: {{ $survey->title }}</p>
    <p class="muted">Respondentes completos: {{ $survey->responses->whereNotNull('completed_at')->count() }}</p>

    @foreach($survey->template->sections as $section)
        <h2>{{ $section->title }}</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pergunta</th>
                    <th>Inversão</th>
                    <th>Escala</th>
                </tr>
            </thead>
            <tbody>
                @foreach($section->questions as $q)
                    <tr>
                        <td>{{ $q->sort_order + 1 }}</td>
                        <td>{{ $q->body }}</td>
                        <td>{{ $q->reverse_score ? 'Sim' : 'Não' }}</td>
                        <td>{{ ($q->response_scale ?? 'frequency') === 'agreement' ? 'Concordância' : 'Frequência' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <h2>Resultados agregados</h2>
    <table>
        <thead>
            <tr>
                <th>Escopo</th>
                <th>Média</th>
                <th>Nível de risco</th>
                <th>N</th>
            </tr>
        </thead>
        <tbody>
            @foreach($survey->results as $row)
                <tr>
                    <td>
                        @if($row->department_id)
                            Depto #{{ $row->department_id }}
                        @elseif($row->survey_template_section_id)
                            {{ $row->meta['section_title'] ?? 'Seção' }}
                        @else
                            Geral
                        @endif
                    </td>
                    <td>{{ number_format($row->average_score, 2) }}</td>
                    <td>{{ $riskLevelLabel($row->risk_level) }}</td>
                    <td>{{ $row->respondent_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $overall = $survey->results->first(fn($r) => $r->survey_template_section_id === null && $r->department_id === null);
        $referral = $scenarioConfig['technical_referral'] ?? [];
    @endphp

    <h2>NR-1, riscos psicossociais e PGR</h2>
    <p class="muted">
        Referência: Portaria SEPRT nº 1.419/2024 (NR-1). Os fatores psicossociais no trabalho devem ser considerados no processo de gerenciamento de riscos
        ocupacionais, com participação dos trabalhadores e documentação compatível com o PGR (ou documento equivalente).
    </p>

    @if($overall)
        <p style="padding: 8px; border: 1px solid #ccc; background: #f5f5f5;">
            <strong>{{ $scenarioConfig['short_label'] ?? 'Cenário geral' }}:</strong>
            média {{ number_format($overall->average_score, 2) }} ({{ $riskLevelLabel($overall->risk_level) }}).
            @if(!empty($referral['conduct']))
                {{ $referral['conduct'] }}
            @endif
        </p>
    @endif

    <p><strong>Mapeamento orientativo — dimensão da pesquisa e foco no PGR:</strong></p>
    <table>
        <thead>
            <tr>
                <th>Nível de risco (agregado)</th>
                <th>Conduta sugerida no ciclo PGR</th>
            </tr>
        </thead>
        <tbody>
            @php
                $pgrRows = [
                    'red' => ['Risco elevado', 'Priorizar análise aprofundada, medidas de controle e prazo definido; comunicação com CIPA/participação dos trabalhadores quando aplicável.'],
                    'yellow' => ['Risco intermediário', 'Planejar ações preventivas, monitoramento em nova rodada de coleta ou indicadores correlatos.'],
                    'green' => ['Situação favorável', 'Manter práticas, revisar periodicamente e registrar evolução histórica no PGR.'],
                ];
                $highlight = $scenario ?? 'green';
            @endphp
            @foreach($pgrRows as $level => [$label, $conduct])
                <tr @if($level === $highlight) style="background: #fff8e6; font-weight: bold;" @endif>
                    <td>{{ $label }}@if($level === $highlight) (cenário atual)@endif</td>
                    <td>{{ $conduct }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin-top: 12px; font-size: 10px; padding: 8px; border: 1px solid #ccc; background: #f9f9f9;">
        <strong>Disclaimer:</strong> as médias deste relatório refletem escala Likert 1–5 (metodologia COPSOQ), com pesos e inversões configurados no template. Quanto maior a média, maior o risco.
        A interpretação final para fins de PGR, exposição e controles deve ser feita por equipe técnica competente e integrada aos demais dados da organização.
    </p>
</body>
</html>
