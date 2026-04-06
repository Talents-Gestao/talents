<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Exceptions\RhidDomainChoiceRequiredException;
use App\Models\Company;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RhidAuthService
{
    public function baseUrl(Company $company): string
    {
        $url = $company->rhid_base_url ?: config('rhid.base_url');

        return rtrim((string) $url, '/');
    }

    public function cacheKey(Company $company): string
    {
        return 'rhid:access_token:'.$company->id;
    }

    public function forgetToken(Company $company): void
    {
        Cache::forget($this->cacheKey($company));
    }

    public function getAccessToken(Company $company, bool $refresh = false): string
    {
        if (! $company->rhidConfigured()) {
            throw new RhidApiException('Configure email e senha RHID nas configuracoes da empresa.');
        }

        $key = $this->cacheKey($company);

        if (! $refresh && Cache::has($key)) {
            return (string) Cache::get($key);
        }

        $payload = [
            'email' => $company->rhid_email,
            'password' => $company->rhid_password,
        ];

        if (filled($company->rhid_domain)) {
            $payload['domain'] = $company->rhid_domain;
        }

        $response = Http::timeout(config('rhid.timeout'))
            ->asJson()
            ->acceptJson()
            ->post($this->baseUrl($company).'/login.svc/', $payload);

        $this->assertLoginOk($response);

        $token = $response->json('accessToken');
        if (! is_string($token) || $token === '') {
            throw new RhidApiException('Resposta de login RHID sem accessToken.');
        }

        Cache::put($key, $token, now()->addSeconds((int) config('rhid.token_cache_ttl')));

        return $token;
    }

    protected function assertLoginOk(Response $response): void
    {
        $json = $response->json();
        if (! is_array($json)) {
            throw RhidApiException::fromResponse($response, 'login');
        }

        if (isset($json['code']) && (int) $json['code'] === 400
            && isset($json['error']) && $json['error'] === 'Mais de um cliente/dominio'
            && ! empty($json['listCustomer']) && is_array($json['listCustomer'])) {
            /** @var list<array<string, mixed>> $list */
            $list = $json['listCustomer'];
            throw new RhidDomainChoiceRequiredException($list);
        }

        if ($response->failed() || empty($json['accessToken'])) {
            throw RhidApiException::fromResponse($response, 'login');
        }
    }
}
