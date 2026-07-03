<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Encaminhamento técnico - {{ $survey->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; color: #4a2070; }
        h2 { font-size: 14px; margin-top: 16px; }
        .muted { color: #666; font-size: 11px; }
        .scenario { margin: 12px 0; padding: 10px; border: 1px solid #ccc; background: #f9f9f9; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px; }
        th { background: #eee; }
    </style>
</head>
<body>
    @php
        $overall = $survey->results->first(fn($r) => $r->survey_template_section_id === null && $r->department_id === null);
        $referral = $scenarioConfig['technical_referral'] ?? [];
        $prioritySections = $survey->results
            ->whereNotNull('survey_template_section_id')
            ->whereNull('department_id')
            ->sortByDesc('average_score')
            ->take(5);
    @endphp

    <h1>{{ $referral['heading'] ?? 'Encaminhamento técnico' }}</h1>
    <p class="muted">Empresa: {{ $survey->company->name }} &mdash; Campanha: {{ $survey->title }}</p>
    <p class="muted">Data de emissão: {{ now()->format('d/m/Y') }}</p>

    @if($overall)
        <div class="scenario">
            <strong>{{ $scenarioConfig['short_label'] ?? 'Cenário geral' }}</strong>
            <br>
            <strong>Indicador geral (1–5):</strong> {{ number_format($overall->average_score, 2) }} — {{ $riskLevelLabel($overall->risk_level) }}
            <br>
            <strong>Respondentes:</strong> {{ $overall->respondent_count }}
        </div>
    @endif

    <h2>Encaminhamento</h2>
    <p>{{ $referral['body'] ?? '' }}</p>
    <p><strong>Conduta sugerida:</strong> {{ $referral['conduct'] ?? '' }}</p>

    <h2>Dimensões com maior indicador</h2>
    <table>
        <thead>
            <tr>
                <th>Dimensão</th>
                <th>Média (1–5)</th>
                <th>Nível de risco</th>
            </tr>
        </thead>
        <tbody>
            @forelse($prioritySections as $row)
                <tr>
                    <td>{{ $row->meta['section_title'] ?? '—' }}</td>
                    <td>{{ number_format($row->average_score, 2) }}</td>
                    <td>{{ $riskLevelLabel($row->risk_level) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Sem resultados por dimensão disponíveis.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>NR-1 e PGR</h2>
    <p class="muted">
        Referência: Portaria SEPRT nº 1.419/2024 (NR-1). Os fatores psicossociais no trabalho devem ser considerados no processo de gerenciamento de riscos
        ocupacionais, com participação dos trabalhadores e documentação compatível com o PGR (ou documento equivalente).
    </p>

    <p style="margin-top: 12px; font-size: 10px; padding: 8px; border: 1px solid #ccc; background: #f9f9f9;">
        <strong>Disclaimer:</strong> este encaminhamento reflete dados agregados da pesquisa COPSOQ (escala Likert 1–5).
        A interpretação final para fins de PGR, exposição e controles deve ser feita por equipe técnica competente e integrada aos demais dados da organização.
    </p>
</body>
</html>
