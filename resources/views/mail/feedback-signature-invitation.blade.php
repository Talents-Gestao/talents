<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Assinatura de feedback</title>
</head>
<body style="font-family: Figtree, Arial, sans-serif; color: #1e293b; line-height: 1.5;">
    <p>Olá, <strong>{{ $signature->signer_name }}</strong>,</p>
    <p>
        O feedback de alinhamento
        @if ($session->employee)
            com <strong>{{ $session->employee->name }}</strong>
        @endif
        está pronto para a sua assinatura digital.
    </p>
    <p>
        <a href="{{ route('feedback.sign.show', $signature->token) }}"
           style="display:inline-block;background:#632a7e;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">
            Abrir e assinar
        </a>
    </p>
    <p style="font-size:12px;color:#64748b;">
        Se o botão não funcionar, copie e cole este link no navegador:<br>
        {{ route('feedback.sign.show', $signature->token) }}
    </p>
</body>
</html>
