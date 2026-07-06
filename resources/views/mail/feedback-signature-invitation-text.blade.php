Olá, {{ $signature->signer_name }},

O feedback de alinhamento @if ($session->employee) com {{ $session->employee->name }} @endif está pronto para a sua assinatura digital.

Abra o link abaixo para revisar e assinar:

{{ route('feedback.sign.show', $signature->token) }}
