<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Client\Response;

class RhidReportService
{
    public function __construct(
        private RhidClient $client,
    ) {}

    /**
     * Inicia geracao de Cartao ou Espelho (POST report.svc/ponto).
     *
     * @param  array<string, mixed>  $body
     * @return array{guid: string, numPeople?: int|null, error?: string|null}
     */
    public function startPontoReport(Company $company, ?User $user, array $body): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'report.svc/ponto',
            [
                'body' => $body,
                'timeout' => (int) config('rhid.report_timeout'),
                'auditAction' => 'rhid.report.ponto.start',
            ],
        );

        return $this->decodeStartResponse($r);
    }

    /**
     * @return array<string, mixed>
     */
    public function guidStatus(Company $company, ?User $user, string $guid): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/notify.svc/specificGuid/',
            [
                'query' => ['guid' => $guid],
                'auditAction' => 'rhid.report.guid_status',
            ],
        );

        if ($r->failed()) {
            throw RhidApiException::fromResponse($r, 'guid_status');
        }

        $json = $r->json();
        if (! is_array($json)) {
            throw new RhidApiException('Resposta invalida ao consultar GUID.', $r->status());
        }

        return $json;
    }

    /**
     * Baixa arquivo gerado (PDF/CSV/HTML).
     *
     * O save_file do RHID costuma exigir POST com corpo JSON (ex.: []) e cabecalhos de portal;
     * sem isso a resposta pode vir 200 com corpo vazio. Para HTML, tenta variantes de format e
     * pequenas esperas (corrida apos percent 100).
     */
    public function downloadSaveFile(Company $company, ?User $user, string $format, string $guid): Response
    {
        $formats = strtoupper($format) === 'HTML'
            ? ['HTML', 'Html', 'html']
            : [$format];

        $last = null;
        foreach ($formats as $fmt) {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $last = $this->saveFileRequest($company, $user, $fmt, $guid);
                if ($last->failed()) {
                    break;
                }
                if (trim((string) $last->body()) !== '') {
                    return $last;
                }
                if ($attempt < 2) {
                    usleep(500_000);
                }
            }
        }

        return $last ?? $this->saveFileRequest($company, $user, $format, $guid);
    }

    /**
     * Corpo do arquivo após save_file (HTML/PDF), com o mesmo desembrulho JSON que o painel usa ao baixar espelho.
     *
     * @throws RhidApiException
     */
    public function downloadSaveFileBody(Company $company, ?User $user, string $format, string $guid): string
    {
        $r = $this->downloadSaveFile($company, $user, $format, $guid);
        if ($r->failed()) {
            throw RhidApiException::fromResponse($r, 'save_file');
        }
        $raw = (string) $r->body();
        if (trim($raw) === '') {
            throw new RhidApiException('Arquivo vazio retornado pelo RHID (save_file).', $r->status());
        }

        return $this->unwrapSaveFilePayload($raw);
    }

    /**
     * Alguns tenants devolvem o arquivo dentro de um envelope JSON em vez de binário/HTML cru.
     */
    public function unwrapSaveFilePayload(string $body): string
    {
        $t = trim($body);
        if ($t === '' || $t[0] !== '{') {
            return $body;
        }
        $j = json_decode($t, true);
        if (! is_array($j)) {
            return $body;
        }
        foreach (['html', 'Html', 'HTML', 'content', 'Content', 'file', 'File'] as $k) {
            if (isset($j[$k]) && is_string($j[$k]) && trim($j[$k]) !== '') {
                return $j[$k];
            }
        }
        if (isset($j['d']) && is_string($j['d']) && trim($j['d']) !== '') {
            return $j['d'];
        }

        return $body;
    }

    /**
     * @return array{0: string, 1: string} [origin, referer]
     */
    private function rhidPortalHeaders(Company $company): array
    {
        $base = rtrim($company->rhid_base_url ?: config('rhid.base_url'), '/');
        $parts = parse_url($base) ?: [];
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'www.rhid.com.br';
        $origin = $scheme.'://'.$host;
        $referer = $base.'/';

        return [$origin, $referer];
    }

    private function saveFileRequest(Company $company, ?User $user, string $format, string $guid): Response
    {
        [$origin, $referer] = $this->rhidPortalHeaders($company);

        return $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/notify.svc/save_file/',
            [
                'query' => [
                    'format' => $format,
                    'guid' => $guid,
                ],
                'raw_body' => '[]',
                'content_type' => 'application/json',
                'as_json' => false,
                'expect_json' => false,
                'accept' => '*/*',
                'timeout' => (int) config('rhid.report_timeout'),
                'headers' => [
                    'Origin' => $origin,
                    'Referer' => $referer,
                ],
                'auditAction' => 'rhid.report.save_file',
            ],
        );
    }

    /**
     * Exporta AFD (portaria 1510 ou 671).
     *
     * @param  array<string, mixed>  $query  tipo, ini, fim, idCompany, etc.
     */
    public function exportAfd(Company $company, ?User $user, array $query, string $rawJsonBody = '[99000001]'): Response
    {
        return $this->client->request(
            $company,
            $user,
            'POST',
            'report.svc/exporta_arquivo/',
            [
                'query' => $query,
                'raw_body' => $rawJsonBody,
                'content_type' => 'application/json',
                'as_json' => false,
                'expect_json' => false,
                'accept' => '*/*',
                'timeout' => (int) config('rhid.report_timeout'),
                'headers' => [
                    'Host' => 'www.rhid.com.br',
                    'Origin' => 'https://www.rhid.com.br',
                    'Referer' => 'https://www.rhid.com.br/v2',
                ],
                'auditAction' => 'rhid.afd.export',
            ],
        );
    }

    /**
     * @return array{guid: string, numPeople?: int|null, error?: string|null}
     */
    protected function decodeStartResponse(Response $response): array
    {
        if ($response->failed()) {
            throw RhidApiException::fromResponse($response, 'report.ponto');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RhidApiException('Resposta invalida ao iniciar relatorio RHID (nao JSON).', $response->status());
        }

        $guid = $this->extractPontoStartGuid($json);
        if ($guid === null) {
            $apiErr = $json['error'] ?? $json['Error'] ?? null;
            if (is_string($apiErr) && $apiErr !== '') {
                throw new RhidApiException($apiErr, $response->status(), $json);
            }

            throw new RhidApiException('Resposta sem GUID ao iniciar relatorio RHID.', $response->status(), $json);
        }

        return [
            'guid' => $guid,
            'numPeople' => $json['numPeople'] ?? $json['NumPeople'] ?? null,
            'error' => $json['error'] ?? $json['Error'] ?? null,
        ];
    }

    /**
     * O endpoint report.svc/ponto pode serializar em camelCase (guid) ou PascalCase (Guid).
     *
     * @param  array<string, mixed>  $json
     */
    protected function extractPontoStartGuid(array $json): ?string
    {
        foreach (['guid', 'Guid', 'GUID'] as $key) {
            if (! isset($json[$key])) {
                continue;
            }
            $v = $json[$key];
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        if (isset($json['d']) && is_array($json['d'])) {
            return $this->extractPontoStartGuid($json['d']);
        }

        return null;
    }
}
