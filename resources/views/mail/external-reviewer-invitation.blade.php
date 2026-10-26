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
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td style="padding: 28px 32px 32px; text-align: left;">
                            <p style="margin: 0 0 16px;">Olá, {{ $user->name }},</p>
                            <p style="margin: 0 0 16px;">A Talents criou um acesso de <strong>revisor externo</strong> para você, para a revisão documental dos relatórios NR-1.</p>
                            <p style="margin: 0 0 16px;">Seu usuário é o e-mail: <strong>{{ $user->email }}</strong></p>
                            <p style="margin: 0 0 16px; text-align: center;">
                                <a href="{{ $setPasswordUrl }}" style="display: inline-block; background: #632a7e; color: #ffffff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: 600;">Definir minha senha</a>
                            </p>
                            <p style="margin: 0 0 16px; font-size: 14px;">O acesso aos documentos só é liberado quando a revisão externa estiver ativa na plataforma (exige verificação em duas etapas). Você verá apenas os documentos que a Talents compartilhar com você.</p>
                            <p style="margin: 0; font-size: 13px; color: #6b7280;">Se você não reconhece este cadastro, ignore este e-mail.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
