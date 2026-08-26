Olá,

@if ($user->hasCompletedRegistration())
@if ($company)
Foi solicitada a redefinição de senha do seu acesso ao portal Talents para a empresa {{ $company->name }}.
@else
Foi solicitada a redefinição de senha do seu acesso à equipe administrativa da plataforma Talents.
@endif

Seu usuário é o e-mail: {{ $user->email }}

Para redefinir sua senha, acesse:
{{ $resetPasswordUrl }}

Login: {{ url('/login') }}

Se você não solicitou esta redefinição, ignore este e-mail.
@else
@if ($company)
Foi criado um acesso ao portal Talents para a empresa {{ $company->name }}.
@else
Foi criado um acesso à equipe administrativa da plataforma Talents.
@endif

Seu usuário é o e-mail: {{ $user->email }}

Para definir sua senha, acesse:
{{ $resetPasswordUrl }}

Login: {{ url('/login') }}

Se você não reconhece este cadastro, ignore este e-mail.
@endif
