<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório executivo - {{ $survey->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #2a1042; }
        h1 { font-size: 20px; color: #4a2070; }
        .muted { color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f0fa; }
    </style>
</head>
<body>
    @php
        $riskLevelLabel = fn (?string $l) => match ($l) {
            'green' => 'Situação favorável',
            'yellow' => 'Risco intermediário',
            'red' => 'Risco elevado',
            default => strtoupper((string) $l),
        };
    @endphp
    <h1>Relatório executivo NR-1</h1>
    <p class="muted">Empresa: {{ $survey->company->name }} &mdash; Campanha: {{ $survey->title }}</p>
    <p class="muted">Período: {{ optional($survey->starts_at)->format('d/m/Y') }} a {{ optional($survey->ends_at)->format('d/m/Y') }}</p>

    @php
        $overall = $survey->results->first(fn($r) => $r->survey_template_section_id === null && $r->department_id === null);
    @endphp

    @if($overall)
        <p><strong>Indicador geral de risco (1–5):</strong> {{ number_format($overall->average_score, 2) }} ({{ $riskLevelLabel($overall->risk_level) }})</p>
        <p><strong>Respondentes:</strong> {{ $overall->respondent_count }}</p>
    @endif

    <h2>Dimensões</h2>
    <table>
        <thead>
            <tr>
                <th>Dimensão</th>
                <th>Média (1–5)</th>
                <th>Nível de risco</th>
            </tr>
        </thead>
        <tbody>
            @foreach($survey->results->whereNotNull('survey_template_section_id')->whereNull('department_id') as $row)
                <tr>
                    <td>{{ $row->meta['section_title'] ?? '—' }}</td>
                    <td>{{ number_format($row->average_score, 1) }}</td>
                    <td>{{ $riskLevelLabel($row->risk_level) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Insights</h2>
    <ul>
        @forelse($survey->insights as $insight)
            <li>{{ $insight->message }}</li>
        @empty
            <li>Nenhum insight registrado.</li>
        @endforelse
    </ul>

    <h2>Conformidade NR-1 e PGR</h2>
    <p class="muted">
        A <strong>NR-1</strong> (Portaria SEPRT nº 1.419, de 27 de agosto de 2024) integra a identificação de perigos e a avaliação de riscos ocupacionais,
        incluindo <strong>fatores de riscos relacionados à saúde mental dos trabalhadores</strong> decorrentes das condições de trabalho, no âmbito do
        Programa de Gerenciamento de Riscos (PGR) ou documento equivalente, conforme porte e classificação da empresa.
    </p>
    <p><strong>Uso deste relatório na documentação do PGR:</strong></p>
    <ul>
        <li>Evidência de levantamento participativo / percepção dos trabalhadores sobre o ambiente de trabalho (dados agregados).</li>
        <li>Apoio à priorização de medidas de prevenção nas dimensões com indicador &quot;Risco intermediário&quot; ou &quot;Risco elevado&quot;.</li>
        <li>Registro de campanha, período e unidade organizacional (setores, quando aplicável).</li>
        <li>Manter rastreabilidade com plano de ação, responsáveis e prazos definidos internamente.</li>
    </ul>
    <p><strong>Checklist sugerido (não exaustivo):</strong></p>
    <ul>
        <li>Inventário de riscos psicossociais atualizado com base em metodologia reconhecida.</li>
        <li>Participação dos trabalhadores no ciclo de gerenciamento de riscos.</li>
        <li>Integração com DDS, análise de acidentes/doenças, absenteísmo e reclamações, quando pertinente.</li>
        <li>Revisão periódica após mudanças organizacionais relevantes.</li>
    </ul>
    <p style="margin-top: 14px; padding: 10px; border: 1px solid #ccc; background: #fafafa; font-size: 10px;">
        <strong>Aviso legal:</strong> este documento é um relatório gerado pela plataforma com base em respostas agregadas e anônimas.
        Não substitui avaliação técnica por profissionais legalmente habilitados, laudos, inspeções do MTE nem outras obrigações legais da empresa.
        A responsabilidade pela validação, complementação e assinatura do PGR permanece com o empregador e seus consultores.
    </p>
</body>
</html>
