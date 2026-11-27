{{-- Valor de uma resposta de feedback no PDF. $value: array (value_json), texto (value_text) ou nulo. --}}
<div class="value">
    @if ($value === null || $value === '' || $value === [])
        —
    @elseif (is_array($value))
            @php
                $json = $value;
                $isList = array_is_list($json);
                $first = $isList ? ($json[0] ?? null) : null;
                $isActionTable = is_array($first) && array_key_exists('action', $first);
                $ccmLabels = ['start' => 'Começar', 'continue' => 'Continuar', 'improve' => 'Melhorar', 'stop' => 'Cessar'];
                $isCcm = ! $isList && collect(array_keys($ccmLabels))->contains(fn ($key) => array_key_exists($key, $json));
            @endphp
            @if ($isActionTable)
                @foreach ($json as $i => $row)
                    @if (is_array($row))
                        @php
                            $action = trim((string) ($row['action'] ?? ''));
                            $responsible = trim((string) ($row['responsible'] ?? ''));
                            $deadline = trim((string) ($row['deadline'] ?? ''));
                        @endphp
                        @if ($action !== '' || $responsible !== '' || $deadline !== '')
                            {{ $i + 1 }}. {{ $action !== '' ? $action : '—' }} · Responsável: {{ $responsible !== '' ? $responsible : '—' }} · Prazo: {{ $deadline !== '' ? $deadline : '—' }}<br>
                        @endif
                    @endif
                @endforeach
            @elseif ($isCcm)
                @foreach ($ccmLabels as $key => $label)
                    {{ $label }}: {{ trim((string) ($json[$key] ?? '')) !== '' ? $json[$key] : '—' }}<br>
                @endforeach
            @else
                @foreach ($json as $k => $v)
                    @if (is_array($v))
                        {{ is_string($k) ? ucfirst($k).': ' : '' }}{{ json_encode($v, JSON_UNESCAPED_UNICODE) }}<br>
                    @else
                        • {{ $v }}<br>
                    @endif
                @endforeach
            @endif
    @else
        {{ $value }}
    @endif
