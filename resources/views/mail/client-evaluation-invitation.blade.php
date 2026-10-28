<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Sua avaliação sobre a Talents</title>
</head>
<body style="font-family: Figtree, Arial, sans-serif; color: #1e293b; line-height: 1.5;">
    <p>Olá, <strong>{{ $evaluation->respondent_name }}</strong>,</p>
    <p>
        A Talents gostaria de ouvir a sua avaliação sobre o nosso trabalho
        @if ($evaluation->company)
            com <strong>{{ $evaluation->company->name }}</strong>
        @endif
        no ciclo {{ $evaluation->cycleLabel() }}. Leva cerca de 2 minutos.
    </p>
    <p>
        <a href="{{ $evaluation->publicUrl() }}"
           style="display:inline-block;background:#632a7e;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">
            Avaliar agora
        </a>
    </p>
    <p style="font-size:12px;color:#64748b;">
        Se o botão não funcionar, copie e cole este link no navegador:<br>
        {{ $evaluation->publicUrl() }}
    </p>
    <p style="font-size:12px;color:#64748b;">
        O link é pessoal, aceita uma resposta e vale até {{ $evaluation->expires_at?->timezone(config('app.timezone'))->format('d/m/Y') }}.
    </p>
</body>
</html>
