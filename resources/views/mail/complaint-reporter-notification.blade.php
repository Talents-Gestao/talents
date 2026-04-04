<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1f2937;">
    <p>Olá,</p>
    <p>{{ $bodyLine }}</p>
    <p style="font-size: 0.875rem; color: #4b5563;">Empresa: <strong>{{ $companyName }}</strong><br>
    Protocolo: <span style="font-family: ui-monospace, monospace;">{{ $protocol }}</span></p>
    <p style="margin: 24px 0;">
        <a href="{{ $trackUrl }}" style="display: inline-block; background: #632a7e; color: #fff; padding: 0.75rem 1.25rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;">
            Acompanhar denúncia
        </a>
    </p>
    <p style="font-size: 0.875rem; color: #6b7280;">Se o botão não funcionar, copie e cole este endereço no navegador:<br>{{ $trackUrl }}</p>
</body>
</html>
