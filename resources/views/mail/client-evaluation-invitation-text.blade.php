Olá, {{ $evaluation->respondent_name }},

A Talents gostaria de ouvir a sua avaliação sobre o nosso trabalho@if ($evaluation->company) com {{ $evaluation->company->name }}@endif no ciclo {{ $evaluation->cycleLabel() }}. Leva cerca de 2 minutos.

{{ $evaluation->publicUrl() }}

O link é pessoal, aceita uma resposta e vale até {{ $evaluation->expires_at?->timezone(config('app.timezone'))->format('d/m/Y') }}.
