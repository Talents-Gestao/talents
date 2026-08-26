Olá,

@if ($user->hasCompletedRegistration())
Foi solicitada a redefinição de senha do seu acesso ao portal da empresa cliente da plataforma Talents para {{ $company->name }}.

Seu usuário é o e-mail: {{ $user->email }}

Para redefinir sua senha e entrar no portal, acesse:
{{ $resetPasswordUrl }}

Depois de redefinir a senha, faça login em: {{ url('/login') }}

Se você não solicitou esta redefinição, ignore este e-mail.
@else
Foi criado o acesso ao portal da empresa cliente da plataforma Talents para {{ $company->name }}.

Seu usuário é o e-mail: {{ $user->email }}

Para definir sua senha e entrar no portal, acesse:
{{ $resetPasswordUrl }}

Depois de definir a senha, faça login em: {{ url('/login') }}

Se você não reconhece este cadastro, ignore este e-mail.
@endif
