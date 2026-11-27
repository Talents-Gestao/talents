<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Entrevista — {{ $interview->candidate_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0 0 8px; color: #0f172a; }
        h2 { font-size: 13px; margin: 18px 0 8px; color: #334155; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .meta { margin-bottom: 16px; }
        .meta p { margin: 2px 0; }
        .question { font-weight: bold; margin-top: 10px; }
        .answer { margin: 4px 0 0; white-space: pre-wrap; }
        .transcript { margin-top: 24px; font-size: 10px; white-space: pre-wrap; }
        .page-break { page-break-before: always; }
        .unreviewed { margin: 3px 0 0; font-size: 9px; font-style: italic; color: #b45309; }
        .quote { margin: 3px 0 0; font-size: 9px; font-style: italic; color: #475569; }
        .note { margin: 3px 0 0; font-size: 9px; color: #334155; }
        .review-pending { margin: 0 0 12px; padding: 6px 8px; border: 1px solid #fcd34d; background: #fffbeb; color: #92400e; }
    </style>
</head>
<body>
    <h1>Relatório de Entrevista</h1>
    <div class="meta">
        <p><strong>Candidato:</strong> {{ $interview->candidate_name }}</p>
        @if($interview->position_title)
            <p><strong>Vaga:</strong> {{ $interview->position_title }}</p>
        @endif
        <p><strong>Roteiro:</strong> {{ $interview->questionnaire->name }}</p>
        @if($interview->company)
            <p><strong>Empresa:</strong> {{ $interview->company->name }}</p>
        @endif
        <p><strong>Data:</strong> {{ $interview->finished_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? '—' }}</p>
        <p><strong>Revisão humana:</strong> {{ $reviewLine }}</p>
    </div>

    @unless($interview->isReviewed())
        <p class="review-pending">Revisão humana pendente. Respostas marcadas como «{{ $unreviewedLabel }}» ainda não foram conferidas por uma pessoa.</p>
    @endunless

    @foreach($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        @foreach($section['items'] as $item)
            <div class="question">{{ $item['question'] }}</div>
            @if($item['unreviewed_ai'])
                <div class="unreviewed">{{ $unreviewedLabel }}</div>
            @endif
            <div class="answer">{{ $item['answer'] }}</div>
            @if($item['raw_quote'])
                <div class="quote">Evidência: “{{ $item['raw_quote'] }}”</div>
            @endif
            @if($item['reviewer_note'])
                <div class="note">Observação: {{ $item['reviewer_note'] }}</div>
            @endif
        @endforeach
    @endforeach

    @if($interview->transcript_text)
        <div class="page-break"></div>
        <h2>Transcrição completa</h2>
        <div class="transcript">{{ $interview->transcript_text }}</div>
    @endif
</body>
</html>
