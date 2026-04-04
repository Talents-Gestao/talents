<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ReceitaWsService
{
    public function lookupCnpj(string $cnpj): array
    {
        $digits = preg_replace('/\D/', '', $cnpj) ?? '';

        if (strlen($digits) !== 14) {
            throw new InvalidArgumentException('Informe um CNPJ com 14 dígitos.');
        }

        $token = config('services.receitaws.token');
        if (empty($token)) {
            throw new RuntimeException('Consulta CNPJ não está configurada no servidor.');
        }

        $baseUrl = rtrim((string) config('services.receitaws.base_url'), '/');
        $timeout = (int) config('services.receitaws.timeout', 60);

        $url = $baseUrl.'/cnpj/'.$digits;

        $response = Http::timeout($timeout)
            ->withToken($token)
            ->get($url);

        if ($response->status() === 402) {
            throw new RuntimeException('Limite de consultas da API ReceitaWS atingido.');
        }

        if ($response->status() === 504) {
            throw new RuntimeException('Consulta em fila ou indisponível no momento. Tente novamente em instantes.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Não foi possível consultar o CNPJ no momento.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Resposta da consulta inválida.');
        }

        if (($data['status'] ?? '') === 'ERROR') {
            throw new RuntimeException($data['message'] ?? 'CNPJ não encontrado ou inválido.');
        }

        if (($data['status'] ?? '') !== 'OK') {
            throw new RuntimeException('Não foi possível obter os dados deste CNPJ.');
        }

        $legalName = $data['nome'] ?? '';
        $fantasia = trim((string) ($data['fantasia'] ?? ''));
        $name = $fantasia !== '' ? $fantasia : $legalName;

        $atividadePrincipal = $data['atividade_principal'][0]['text'] ?? '';
        $segment = mb_substr((string) $atividadePrincipal, 0, 120);

        $cnpjFormatted = $data['cnpj'] ?? $digits;

        $email = trim((string) ($data['email'] ?? ''));

        return array_merge([
            'legal_name' => $legalName,
            'name' => $name,
            'cnpj' => $cnpjFormatted,
            'segment' => $segment,
            'contact_email' => $email !== '' ? $email : '',
            'tax_regime' => $this->inferTaxRegime($data),
        ], $this->parseAddressParts($data));
    }

    /**
     * @return array{address_street: string, address_neighborhood: string, address_city: string, address_state: string, address_zip: string}
     */
    private function parseAddressParts(array $data): array
    {
        $log = trim((string) ($data['logradouro'] ?? ''));
        $num = trim((string) ($data['numero'] ?? ''));
        $comp = trim((string) ($data['complemento'] ?? ''));

        $street = $log;
        if ($num !== '') {
            $street = $log !== '' ? $log.', '.$num : $num;
        }
        if ($comp !== '') {
            $street = $street !== '' ? $street.' — '.$comp : $comp;
        }

        $bairro = trim((string) ($data['bairro'] ?? ''));
        $mun = trim((string) ($data['municipio'] ?? ''));
        $uf = trim((string) ($data['uf'] ?? ''));

        $cepRaw = preg_replace('/\D/', '', (string) ($data['cep'] ?? ''));
        $cepFmt = strlen($cepRaw) === 8
            ? substr($cepRaw, 0, 5).'-'.substr($cepRaw, 5)
            : trim((string) ($data['cep'] ?? ''));

        $ufShort = strtoupper(mb_substr($uf, 0, 2));

        return [
            'address_street' => mb_substr($street, 0, 255),
            'address_neighborhood' => mb_substr($bairro, 0, 120),
            'address_city' => mb_substr($mun, 0, 120),
            'address_state' => $ufShort,
            'address_zip' => mb_substr($cepFmt, 0, 12),
        ];
    }

    private function inferTaxRegime(array $data): string
    {
        $mei = $data['mei'] ?? null;
        if (is_array($mei) && (($mei['optante'] ?? null) === true)) {
            return 'MEI';
        }

        $simei = $data['simei'] ?? null;
        if (is_array($simei) && (($simei['optante'] ?? null) === true)) {
            return 'Optante pelo SIMEI (MEI)';
        }

        $simples = $data['simples'] ?? null;
        if (is_array($simples) && array_key_exists('optante', $simples)) {
            return ($simples['optante'] === true)
                ? 'Optante pelo Simples Nacional'
                : 'Não optante pelo Simples Nacional';
        }

        return '';
    }
}
