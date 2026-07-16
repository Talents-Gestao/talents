<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 16px; color: #632a7e; margin-bottom: 4px; }
        h2 { font-size: 13px; color: #632a7e; margin-top: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .meta { margin-bottom: 12px; }
        .meta td { padding: 2px 8px 2px 0; vertical-align: top; }
        .question { margin: 8px 0; }
        .label { font-weight: bold; color: #475569; }
        .value { margin-top: 2px; white-space: pre-wrap; }
        .hint { font-size: 10px; color: #64748b; font-style: italic; }
    </style>
</head>
<body>
    <h1>Pesquisa de Desligamento</h1>
    <table class="meta">
        <tr><td><strong>Empresa:</strong></td><td>{{ $interview->company?->name ?? '—' }}</td></tr>
        <tr><td><strong>Colaborador(a):</strong></td><td>{{ $interview->collaboratorDisplayName() }}</td></tr>
        <tr><td><strong>E-mail:</strong></td><td>{{ $interview->employee_email ?: ($interview->employee?->email ?? '—') }}</td></tr>
        <tr><td><strong>Data da entrevista:</strong></td><td>{{ $interview->interview_date?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td><strong>Status:</strong></td><td>{{ $interview->status->label() }}</td></tr>
        @if ($interview->employee_submitted_at)
            <tr><td><strong>Respondida pelo colaborador em:</strong></td><td>{{ $interview->employee_submitted_at->format('d/m/Y H:i') }}</td></tr>
        @endif
    </table>

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        @foreach ($section['questions'] as $question)
            @php $value = trim((string) ($answers[$question['key']] ?? '')); @endphp
            <div class="question">
                <div class="label">{{ $question['body'] }}</div>
                @if (! empty($question['hint']))
                    <div class="hint">{{ $question['hint'] }}</div>
                @endif
                <div class="value">{{ $value !== '' ? $value : '—' }}</div>
            </div>
        @endforeach
    @endforeach

    @if ($includeConsultantNotes)
        <h2>Anotações da consultora (uso interno)</h2>
        @foreach ($consultantNoteFields as $field)
            @php $note = trim((string) ($consultantNotes[$field['key']] ?? '')); @endphp
            <div class="question">
                <div class="label">{{ $field['label'] }}</div>
                <div class="value">{{ $note !== '' ? $note : '—' }}</div>
            </div>
        @endforeach
    @endif
</body>
</html>
