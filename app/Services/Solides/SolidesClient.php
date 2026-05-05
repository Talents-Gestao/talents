<?php

namespace App\Services\Solides;

use App\Models\SolidesSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SolidesClient
{
    public function __construct(
        private ?SolidesSetting $setting = null,
    ) {}

    public function request(string $method, string $path, array $options = []): Response
    {
        $setting = $this->setting ?? SolidesSetting::current();
        $baseUrl = $setting?->effectiveBaseUrl()
            ?? rtrim((string) config('solides.base_url'), '/').'/'.config('solides.locale', 'pt-BR').'/api/v1';
        $token = $setting?->safeApiToken();

        if (! $token) {
            throw new \RuntimeException('Token da API Sólides não configurado.');
        }

        $url = $baseUrl.'/'.ltrim($path, '/');
        $timeout = (int) ($options['timeout'] ?? config('solides.timeout', 30));

        $pending = Http::timeout($timeout)
            ->withHeaders([
                'Authorization' => 'Token token='.$token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->acceptJson();

        $query = $options['query'] ?? [];
        if (is_array($query) && $query !== []) {
            $pending = $pending->withQueryParameters($query);
        }

        $payload = $options['body'] ?? [];
        $method = strtoupper($method);

        return match ($method) {
            'GET' => $pending->get($url),
            'POST' => $pending->post($url, is_array($payload) ? $payload : []),
            'PUT' => $pending->put($url, is_array($payload) ? $payload : []),
            'PATCH' => $pending->patch($url, is_array($payload) ? $payload : []),
            'DELETE' => $pending->delete($url),
            default => throw new \InvalidArgumentException('Método HTTP não suportado: '.$method),
        };
    }

    /**
     * @return array{ok: bool, status: int|null, message: string, endpoint: string}
     */
    public function testConnection(): array
    {
        $endpoints = ['/cargos', '/departamentos'];
        $lastStatus = null;
        $lastMessage = 'Falha ao testar conexão com Sólides.';
        $testedEndpoint = $endpoints[0];

        foreach ($endpoints as $endpoint) {
            $testedEndpoint = $endpoint;
            try {
                $response = $this->request('GET', $endpoint);
                $status = $response->status();
                $lastStatus = $status;

                if ($response->successful()) {
                    return [
                        'ok' => true,
                        'status' => $status,
                        'message' => 'Conexão com Sólides OK via '.$endpoint.'.',
                        'endpoint' => $endpoint,
                    ];
                }

                if (in_array($status, [401, 403], true)) {
                    return [
                        'ok' => false,
                        'status' => $status,
                        'message' => 'Token inválido ou sem permissão para acessar a API Sólides.',
                        'endpoint' => $endpoint,
                    ];
                }

                $lastMessage = 'HTTP '.$status.': '.Str::limit($response->body(), 240);
            } catch (ConnectionException $e) {
                $lastStatus = null;
                $lastMessage = 'Sem conexão com a API Sólides (DNS/rede/timeout): '.$e->getMessage();
            } catch (\Throwable $e) {
                $lastStatus = null;
                $lastMessage = $e->getMessage();
            }
        }

        return [
            'ok' => false,
            'status' => $lastStatus,
            'message' => $lastMessage,
            'endpoint' => $testedEndpoint,
        ];
    }
}
