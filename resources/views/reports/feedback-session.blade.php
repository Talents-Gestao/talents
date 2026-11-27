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
        .note { color: #64748b; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ $session->title }}</h1>
    <table class="meta">
        <tr><td><strong>Colaborador(a):</strong></td><td>{{ $session->collaboratorDisplayName() }}</td></tr>
        <tr><td><strong>Cargo:</strong></td><td>{{ $session->employee?->position?->name ?? '—' }}</td></tr>
        <tr><td><strong>Líder:</strong></td><td>{{ $session->leader?->name }}</td></tr>
        <tr><td><strong>Data:</strong></td><td>{{ $session->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
        <tr><td><strong>Próximo alinhamento:</strong></td><td>{{ $session->next_alignment_at?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td><strong>Modelo:</strong></td><td>{{ $version->title }} (versão {{ $version->number }})</td></tr>
    </table>
    @if ($version->description)
        <p>{{ $version->description }}</p>
    @endif

    @foreach ($sections as $section)
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
                @include('reports.partials.feedback-answer-value', ['value' => $answer ? ($answer->value_json ?? $answer->value_text) : null])
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

    @if (count($outOfVersionAnswers) || count($outOfVersionExtras))
        <h2>Fora da versão atual</h2>
        <p class="note">Respostas guardadas de uma versão anterior do modelo; estas perguntas não estão na versão {{ $version->number }}.</p>
        @foreach ($outOfVersionAnswers as $item)
            <div class="question">
                <div class="label">{{ $item['body'] }}@if ($item['section_title']) <span class="note">({{ $item['section_title'] }})</span>@endif</div>
                @include('reports.partials.feedback-answer-value', ['value' => $item['value']])
            </div>
        @endforeach
        @foreach ($outOfVersionExtras as $extra)
            <div class="question">
                <div class="label">{{ $extra['question'] ?: 'Pergunta extra' }}@if ($extra['section_title']) <span class="note">({{ $extra['section_title'] }})</span>@endif</div>
                <div class="value">{{ $extra['answer'] ?: '—' }}</div>
            </div>
        @endforeach
    @endif

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
