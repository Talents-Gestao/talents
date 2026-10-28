<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Talents</title>
</head>
<body style="margin: 0; padding: 0; background: #f4f1f7; font-family: system-ui, -apple-system, Segoe UI, sans-serif; line-height: 1.5; color: #1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f4f1f7; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);">
                    <tr>
                        <td align="center" style="padding: 28px 32px 8px;">
                            <img
                                src="{{ asset('images/logo.png') }}"
                                alt="Talents — Gestão de Pessoas"
                                width="168"
                                style="display: block; width: 168px; max-width: 70%; height: auto; border: 0;"
                            >
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 32px 32px; text-align: left;">
                            <p style="margin: 0 0 16px;">Olá,</p>
                            @if ($user->hasCompletedRegistration())
                                <p style="margin: 0 0 16px;">Foi solicitada a <strong>redefinição de senha</strong> do seu acesso ao <strong>portal da empresa cliente</strong> da plataforma Talents para <strong>{{ $company->name }}</strong>.</p>
                                <p style="margin: 0 0 16px;">Seu usuário é o e-mail: <strong>{{ $user->email }}</strong></p>
                                <p style="margin: 0 0 8px;">Para <strong>redefinir sua senha</strong> e entrar no portal, use o link abaixo:</p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $resetPasswordUrl }}" style="display: inline-block; background: #632a7e; color: #ffffff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px;">
                                                Redefinir senha e acessar
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin: 0 0 12px; font-size: 0.875rem; color: #6b7280;">Depois de redefinir a senha, faça login em: <a href="{{ url('/login') }}" style="color: #632a7e;">{{ url('/login') }}</a></p>
                                <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">Se você não solicitou esta redefinição, ignore este e-mail.</p>
                            @else
                                <p style="margin: 0 0 16px;">Foi criado o acesso ao <strong>portal da empresa cliente</strong> da plataforma Talents para <strong>{{ $company->name }}</strong>.</p>
                                <p style="margin: 0 0 16px;">Seu usuário é o e-mail: <strong>{{ $user->email }}</strong></p>
                                <p style="margin: 0 0 8px;">Para <strong>definir sua senha</strong> e entrar no portal, use o link abaixo (permanece válido até você concluir o cadastro da senha):</p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $resetPasswordUrl }}" style="display: inline-block; background: #632a7e; color: #ffffff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px;">
                                                Definir senha e acessar
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin: 0 0 12px; font-size: 0.875rem; color: #6b7280;">Depois de definir a senha, faça login em: <a href="{{ url('/login') }}" style="color: #632a7e;">{{ url('/login') }}</a></p>
                                <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">Se você não reconhece este cadastro, ignore este e-mail.</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
