<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RhidClient
{
    public function __construct(
        private RhidAuthService $auth,
        private RhidAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     *                                         as_json (bool, default true)
     *                                         query (array)
     *                                         body (array) — quando as_json true
     *                                         raw_body (string) — quando definido, envia corpo bruto
     *                                         content_type (string) — junto de raw_body
     *                                         headers (array<string, string>)
     *                                         accept (string)
     *                                         timeout (int)
     */
    public function request(
        Company $company,
        ?User $user,
        string $method,
        string $path,
        array $options = [],
    ): Response {
        if (! $company->rhidConfigured()) {
            throw new RhidApiException('Integração RHID não configurada para esta empresa.');
        }

        $auditAction = $options['auditAction'] ?? 'rhid.request';

        $method = strtoupper($method);
        $attempt = 0;

        retry_request:
        $attempt++;
        $token = $this->auth->getAccessToken($company, refresh: $attempt > 1);

        $base = $this->auth->baseUrl($company);
        $query = $options['query'] ?? [];
        $url = $this->buildUrl($base, $path, $query);

        $timeout = (int) ($options['timeout'] ?? config('rhid.timeout'));
        $headers = array_merge([
            'Authorization' => 'Bearer '.$token,
            'Accept' => $options['accept'] ?? 'application/json',
        ], $options['headers'] ?? []);

        $pending = Http::timeout($timeout)->withHeaders($headers);

        $asJson = $options['as_json'] ?? true;
        if ($asJson && ! isset($options['raw_body'])) {
            $pending = $pending->asJson()->acceptJson();
        }

        $body = $options['body'] ?? [];

        if (isset($options['raw_body'])) {
            $pending = $pending->withBody(
                $options['raw_body'],
                $options['content_type'] ?? 'application/json',
            );
        }

        try {
            $response = match ($method) {
                'GET' => $pending->get($url),
                'DELETE' => $pending->delete($url),
                'POST' => isset($options['raw_body'])
                    ? $pending->post($url)
                    : ($asJson
                        ? $pending->post($url, is_array($body) ? $body : [])
                        : $pending->post($url)),
                'PUT' => $pending->put($url, is_array($body) ? $body : []),
                default => throw new RhidApiException('Método HTTP não suportado: '.$method),
            };
        } catch (ConnectionException $e) {
            throw new RhidApiException(
                'Sem conexao com o servidor RHID (rede, DNS ou timeout). Tente novamente.',
                null,
                null,
                0,
                $e,
            );
        }

        if ($response->status() === 401 && $attempt < 2) {
            $this->auth->forgetToken($company);

            goto retry_request;
        }

        $expectJson = (bool) ($options['expect_json'] ?? true);
        try {
            $this->audit->log(
                $company,
                $user,
                $auditAction,
                $url,
                RhidAuditLogger::maskSensitive([
                    'method' => $method,
                    'path' => $path,
                    'query' => $query,
                    'body' => is_array($body) ? $body : null,
                    'raw' => isset($options['raw_body']),
                ]),
                $this->summarizeResponse($response, $expectJson),
                $response->status(),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function buildUrl(string $base, string $path, array $query): string
    {
        $url = $base.'/'.ltrim($path, '/');
        if ($query === []) {
            return $url;
        }

        $people = null;
        if (isset($query['people']) && is_array($query['people'])) {
            $people = $query['people'];
            unset($query['people']);
        }

        $parts = [];
        if ($query !== []) {
            $parts[] = http_build_query($query);
        }
        if ($people !== null) {
            foreach ($people as $id) {
                if (is_numeric($id)) {
                    $parts[] = 'people='.rawurlencode((string) (int) $id);
                }
            }
        }

        if ($parts === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').implode('&', $parts);
    }

    protected function summarizeResponse(Response $response, bool $expectJson): ?array
    {
        if ($expectJson) {
            $j = $response->json();

            return RhidAuditLogger::summarizeJson(is_array($j) ? $j : null);
        }

        $body = $response->body();

        return [
            'binary_or_text' => true,
            'length' => strlen($body),
            'content_type' => $response->header('Content-Type'),
        ];
    }
}
