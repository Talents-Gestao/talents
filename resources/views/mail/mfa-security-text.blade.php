Olá, {{ $user->name }},

{{ $what }}

Quando: {{ $when }}@if($ip)

Endereço IP: {{ $ip }}@endif


Se foi você, não precisa fazer nada. Se não reconhece esta ação, avise a Talents imediatamente e peça o reset da verificação em duas etapas.

Login: {{ url('/login') }}
