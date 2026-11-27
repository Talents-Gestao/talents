<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Dossiê da candidatura — {{ $candidate?->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0 0 8px; color: #0f172a; }
        h2 { font-size: 13px; margin: 18px 0 8px; color: #334155; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .meta { margin-bottom: 16px; }
        .meta p { margin: 2px 0; }
        .text { margin: 4px 0 0; white-space: pre-wrap; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; vertical-align: top; text-align: left; }
        th { background: #f1f5f9; font-size: 10px; }
        td { font-size: 10px; }
        .decision { margin-top: 6px; padding: 6px 8px; border: 1px solid #cbd5e1; background: #f8fafc; }
        .note { margin-top: 22px; padding: 8px; border: 1px solid #cbd5e1; background: #f8fafc; font-size: 9px; color: #334155; }
    </style>
</head>
<body>
    <h1>Dossiê da candidatura</h1>
    <div class="meta">
        <p><strong>Candidato:</strong> {{ $candidate?->name ?? '—' }}</p>
        @if($candidate?->current_role || $candidate?->current_company)
            <p><strong>Cargo e empresa atuais:</strong> {{ collect([$candidate?->current_role, $candidate?->current_company])->filter()->join(' · ') }}</p>
        @endif
        <p><strong>Vaga:</strong> {{ $vacancy?->title ?? '—' }}</p>
        <p><strong>Empresa:</strong> {{ $vacancy?->company?->name ?? '—' }}</p>
        @if($vacancy?->hiringProcess)
            <p><strong>Processo:</strong> {{ $vacancy->hiringProcess->title }}</p>
        @endif
        <p><strong>Etapa atual:</strong> {{ $stage?->name ?? '—' }}</p>
        <p><strong>Aprovado em:</strong>
            {{ $assessment?->approved_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? '—' }}
            @if($assessment?->approvedBy) por {{ $assessment->approvedBy->name }} @endif
        </p>
    </div>

    <h2>Perfil de sucesso</h2>
    <div class="text">{{ $assessment?->success_profile ?: '—' }}</div>

    <h2>Case</h2>
    <p><strong>Descrição</strong></p>
    <div class="text">{{ $assessment?->case_description ?: '—' }}</div>
    <p><strong>Resultado</strong></p>
    <div class="text">{{ $assessment?->case_result ?: '—' }}</div>

    <h2>Critérios e evidências</h2>
    @if(count($criteria))
        <table>
            <thead>
                <tr><th>Critério</th><th>Evidência</th><th>Origem</th><th>Avaliação</th></tr>
            </thead>
            <tbody>
                @foreach($criteria as $c)
                    <tr>
                        <td>{{ $c['criterio'] }}</td>
                        <td>{{ $c['evidencia'] ?: '—' }}</td>
                        <td>{{ $c['origem'] ?: '—' }}</td>
                        <td>{{ $c['avaliacao'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Nenhum critério registrado.</p>
    @endif

    <h2>Entrevistas da candidatura</h2>
    @if(count($interviews))
        <table>
            <thead>
                <tr><th>Data</th><th>Roteiro</th><th>Fonte</th><th>Revisão humana</th></tr>
            </thead>
            <tbody>
                @foreach($interviews as $i)
                    <tr>
                        <td>{{ $i['date'] ?? '—' }}</td>
                        <td>{{ $i['questionnaire'] }}</td>
                        <td>{{ $i['source'] }}</td>
                        <td>{{ $i['reviewed'] ? 'Concluída' : 'Pendente' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Nenhuma entrevista vinculada a esta candidatura.</p>
    @endif

    <h2>Síntese da entrevistadora</h2>
    <div class="text">{{ $assessment?->interviewer_summary ?: '—' }}</div>

    <h2>Limites da avaliação</h2>
    <div class="text">{{ $assessment?->limitations ?: '—' }}</div>

    <h2>Decisão humana</h2>
    <div class="decision">{{ $assessment?->decision?->label() ?? '—' }}</div>

    <div class="note">{{ $methodologyNote }}</div>
    <p class="muted" style="margin-top: 8px; font-size: 9px;">Gerado em {{ $generatedAt }} · uso interno da Talents.</p>
</body>
</html>
