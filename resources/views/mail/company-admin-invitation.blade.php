<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1f2937;">
    <p>Olá,</p>
    <p>Foi criado o acesso ao <strong>portal da empresa cliente</strong> da plataforma Talents para <strong>{{ $company->name }}</strong>.</p>
    <p>Seu usuário é o e-mail: <strong>{{ $user->email }}</strong></p>
    <p>Para <strong>definir sua senha</strong> e entrar no portal, use o link abaixo (válido por tempo limitado):</p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetPasswordUrl }}" style="display: inline-block; background: #632a7e; color: #fff; padding: 0.75rem 1.25rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;">
            Definir senha e acessar
        </a>
    </p>
    <p style="font-size: 0.875rem; color: #6b7280;">Depois de definir a senha, faça login em: <a href="{{ url('/login') }}">{{ url('/login') }}</a></p>
    <p style="font-size: 0.875rem; color: #6b7280;">Se você não reconhece este cadastro, ignore este e-mail.</p>
</body>
</html>
