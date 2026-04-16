<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1f2937;">
    <p>Alguém pediu para saber mais sobre a <strong>Talents</strong> pela landing.</p>
    <p><strong>Nome:</strong> {{ $submitterName }}</p>
    <p><strong>E-mail:</strong> <a href="mailto:{{ $submitterEmail }}">{{ $submitterEmail }}</a></p>
    <p><strong>Empresa:</strong> {{ $company ?? '—' }}</p>
    <p><strong>Mensagem:</strong></p>
    <p style="white-space: pre-wrap;">{{ $message ?? '—' }}</p>
</body>
</html>
