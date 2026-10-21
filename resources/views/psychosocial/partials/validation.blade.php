@if(!empty($validation))
    <h2>Validação</h2>
    <table>
        <tbody>
            @if(($validation['path'] ?? null) === 'in_system')
                <tr><th>Caminho</th><td>Validação no sistema</td></tr>
                <tr><th>Validado por</th><td>{{ $validation['validated_by']['name'] ?? '—' }}</td></tr>
                <tr><th>Data</th><td>{{ \Illuminate\Support\Carbon::parse($validation['validated_at'])->format('d/m/Y H:i') }}</td></tr>
                <tr><th>Declaração aceita</th><td>{{ $validation['declaration'] }}</td></tr>
            @else
                <tr><th>Caminho</th><td>Validação informada</td></tr>
                <tr><th>Nome</th><td>{{ $validation['name'] }}</td></tr>
                <tr><th>Função</th><td>{{ $validation['role'] }}</td></tr>
                <tr><th>Registro profissional</th><td>{{ filled($validation['registry'] ?? null) ? $validation['registry'] : 'Não informado' }}</td></tr>
                <tr><th>Data da validação</th><td>{{ \Illuminate\Support\Carbon::parse($validation['date'])->format('d/m/Y') }}</td></tr>
                <tr><th>Comprovante</th><td>{{ $validation['proof']['file_name'] }} (SHA-256 {{ $validation['proof']['sha256'] }})</td></tr>
                <tr><th>Registrado por</th><td>{{ $validation['recorded_by']['name'] ?? '—' }}</td></tr>
            @endif
        </tbody>
    </table>
@endif
