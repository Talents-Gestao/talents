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
        .signatures { margin-top: 24px; }
        .signatures img { max-height: 60px; }
    </style>
</head>
<body>
    <h1>{{ $session->title }}</h1>
    <table class="meta">
        <tr><td><strong>Colaborador(a):</strong></td><td>{{ $session->employee?->name }}</td></tr>
        <tr><td><strong>Cargo:</strong></td><td>{{ $session->employee?->position?->name ?? '—' }}</td></tr>
        <tr><td><strong>Líder:</strong></td><td>{{ $session->leader?->name }}</td></tr>
        <tr><td><strong>Data:</strong></td><td>{{ $session->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
        <tr><td><strong>Próximo alinhamento:</strong></td><td>{{ $session->next_alignment_at?->format('d/m/Y') ?? '—' }}</td></tr>
    </table>

    @foreach ($session->template->sections as $section)
        @if ($section->section_type === 'intro')
            <h2>{{ $section->title }}</h2>
            @if ($section->description)
                <p>{{ $section->description }}</p>
            @endif
            @continue
        @endif

        <h2>{{ $section->title }}</h2>
        @foreach ($section->questions as $question)
            @php $answer = $answersByQuestion->get($question->id); @endphp
            <div class="question">
                <div class="label">{{ $question->body }}</div>
                <div class="value">
                    @if (! $answer)
                        —
                    @elseif ($answer->value_json)
                        @if (is_array($answer->value_json))
                            @foreach ($answer->value_json as $k => $v)
                                @if (is_array($v))
                                    {{ is_string($k) ? ucfirst($k).': ' : '' }}{{ json_encode($v, JSON_UNESCAPED_UNICODE) }}<br>
                                @else
                                    • {{ $v }}<br>
                                @endif
                            @endforeach
                        @endif
                    @else
                        {{ $answer->value_text }}
                    @endif
                </div>
            </div>
        @endforeach
        @php $extra = $sectionExtras[(string) $section->id] ?? null; @endphp
        @if ($extra && (($extra['question'] ?? '') !== '' || ($extra['answer'] ?? '') !== ''))
            <div class="question">
                <div class="label">{{ $extra['question'] ?: 'Pergunta extra' }}</div>
                <div class="value">{{ $extra['answer'] ?: '—' }}</div>
            </div>
        @endif
    @endforeach

    <div class="signatures">
        <h2>Assinaturas</h2>
        @foreach ($session->signatures as $sig)
            <p>
                <strong>{{ $sig->role->label() }}:</strong> {{ $sig->signer_name }}<br>
                @if ($sig->signed_at)
                    Assinado em {{ $sig->signed_at->format('d/m/Y H:i') }}
                    @if ($sig->signature_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($sig->signature_path))
                        <br><img src="data:image/png;base64,{{ base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get($sig->signature_path)) }}" alt="Assinatura">
                    @endif
                @else
                    Pendente
                @endif
            </p>
        @endforeach
    </div>
</body>
</html>
