@php
    $lines = preg_split('/\r?\n/', (string) $text);
    $inList = false;
@endphp
@foreach($lines as $line)
    @php
        $trimmed = ltrim($line);
        // Bullet tipográfico (•) ou hífen; mb_* / regex Unicode evita corromper UTF-8
        // (substr de 1 byte em «•» deixa bytes inválidos → � no PDF).
        $isBullet = $trimmed !== '' && (str_starts_with($trimmed, '•') || str_starts_with($trimmed, '-'));
        $bulletBody = $isBullet
            ? ltrim((string) preg_replace('/^[•\-]\s*/u', '', $trimmed))
            : '';
    @endphp
    @if($isBullet)
        @if(!$inList)
            <ul class="pdf-list desc-bullets">
            @php $inList = true; @endphp
        @endif
        <li>{{ $bulletBody }}</li>
    @else
        @if($inList)
            </ul>
            @php $inList = false; @endphp
        @endif
        @if($trimmed !== '')
            @if(str_starts_with($trimmed, 'Objetivo:'))
                <p class="desc-paragraph"><strong>Objetivo:</strong>{{ mb_substr($trimmed, 9) }}</p>
            @elseif($trimmed === 'O que contempla:' || str_starts_with($trimmed, 'O que contempla:'))
                <p class="desc-paragraph"><strong>O que contempla:</strong></p>
            @elseif($trimmed === 'Temas abordados:' || str_starts_with($trimmed, 'Temas abordados:'))
                <p class="desc-paragraph"><strong>Temas abordados:</strong></p>
            @else
                <p class="desc-paragraph">{{ $trimmed }}</p>
            @endif
        @endif
    @endif
@endforeach
@if($inList)
    </ul>
@endif
