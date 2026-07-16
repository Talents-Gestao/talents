<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ViaCepService
{
    /**
     * @return array{
     *   address_zip: string,
     *   address_street: string,
     *   address_neighborhood: string,
     *   address_city: string,
     *   address_state: string,
     *   address_complement: string
     * }
     */
    public function lookup(string $cep): array
    {
        $digits = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($digits) !== 8) {
            throw new InvalidArgumentException('Informe um CEP com 8 dígitos.');
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->get("https://viacep.com.br/ws/{$digits}/json/");

        if (! $response->successful()) {
            throw new RuntimeException('Não foi possível consultar o CEP no momento.');
        }

        $data = $response->json();
        if (! is_array($data) || ($data['erro'] ?? false) === true || ($data['erro'] ?? '') === 'true') {
            throw new RuntimeException('CEP não encontrado.');
        }

        $zip = substr($digits, 0, 5).'-'.substr($digits, 5);

        return [
            'address_zip' => $zip,
            'address_street' => trim((string) ($data['logradouro'] ?? '')),
            'address_neighborhood' => trim((string) ($data['bairro'] ?? '')),
            'address_city' => trim((string) ($data['localidade'] ?? '')),
            'address_state' => strtoupper(trim((string) ($data['uf'] ?? ''))),
            'address_complement' => trim((string) ($data['complemento'] ?? '')),
        ];
    }
}
