Olá, {{ $user->name }},

A verificação em duas etapas da sua conta Talents foi resetada por {{ $by }}.

O aplicativo autenticador e os códigos de recuperação antigos não valem mais. Na próxima vez que o sistema pedir, faça uma nova inscrição.

Se você não esperava este reset, avise a Talents imediatamente.

Login: {{ url('/login') }}
