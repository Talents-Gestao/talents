<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Plano de ação - {{ $survey->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; color: #4a2070; }
        h2 { font-size: 14px; margin-top: 16px; }
        .muted { color: #666; font-size: 11px; }
        .scenario { margin: 12px 0; padding: 10px; border: 1px solid #ccc; background: #f9f9f9; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 8px; vertical-align: top; }
        th { background: #eee; }
    </style>
</head>
<body>
    @php
        $overall = $survey->results->first(fn($r) => $r->survey_template_section_id === null && $r->department_id === null);
        $actionPlan = $scenarioConfig['action_plan'] ?? [];
    @endphp

    <h1>Plano de ação NR-1</h1>
    <p class="muted">Empresa: {{ $survey->company->name }} &mdash; Campanha: {{ $survey->title }}</p>
    <p class="muted">Data de emissão: {{ now()->format('d/m/Y') }}</p>

    @if($overall)
        <div class="scenario">
            <strong>{{ $scenarioConfig['short_label'] ?? 'Cenário geral' }}</strong>
            <br>
            <strong>Indicador geral (1–5):</strong> {{ number_format($overall->average_score, 2) }} — {{ $riskLevelLabel($overall->risk_level) }}
        </div>
    @endif

    <p>{{ $actionPlan['intro'] ?? 'Plano de ação derivado dos resultados da pesquisa psicossocial.' }}</p>

    <h2>Ações</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 30%;">Ação</th>
                <th>Descrição</th>
                <th style="width: 12%;">Status</th>
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

    <p style="margin-top: 14px; font-size: 10px; padding: 8px; border: 1px solid #ccc; background: #f9f9f9;">
        <strong>Aviso:</strong> este plano deve ser validado pela equipe de SST e integrado ao PGR da organização, com responsáveis e prazos definidos internamente.
    </p>
</body>
</html>
