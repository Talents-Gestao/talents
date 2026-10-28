<?php

declare(strict_types=1);

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Exceptions\RhidDomainChoiceRequiredException;
use App\Models\Company;

class RhidCompanyCredentialsService
{
    public function __construct(
        private readonly RhidAuthService $auth,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'rhid_base_url' => ['nullable', 'string', 'max:255'],
            'rhid_email' => ['nullable', 'string', 'email', 'max:255'],
            'rhid_password' => ['nullable', 'string', 'max:500'],
            'rhid_domain' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Payload seguro para a UI — nunca inclui a senha.
     *
     * @return array{rhid_base_url: ?string, rhid_email: ?string, rhid_domain: ?string, has_password: bool}
     */
    public function settingsPayload(Company $company): array
    {
        return [
            'rhid_base_url' => $company->rhid_base_url,
            'rhid_email' => $company->rhid_email,
            'rhid_domain' => $company->rhid_domain,
            'has_password' => filled($company->rhid_password),
        ];
    }

    /**
     * @param  array{rhid_base_url?: ?string, rhid_email?: ?string, rhid_password?: ?string, rhid_domain?: ?string}  $validated
     */
    public function update(Company $company, array $validated): void
    {
        if (array_key_exists('rhid_base_url', $validated)) {
            $company->rhid_base_url = $validated['rhid_base_url'] ?: null;
        }
        if (array_key_exists('rhid_email', $validated)) {
            $company->rhid_email = $validated['rhid_email'] ?: null;
        }
        if (array_key_exists('rhid_domain', $validated)) {
            $company->rhid_domain = $validated['rhid_domain'] ?: null;
        }
        if (! empty($validated['rhid_password'])) {
            $company->rhid_password = $validated['rhid_password'];
        }

        $company->save();
        $this->auth->forgetToken($company);
    }

    /**
     * @return array{ok: bool, message: string, needs_domain?: bool, domains?: list<array<string, mixed>>, payload?: mixed}
     */
    public function test(Company $company): array
    {
        if (! $company->rhidConfigured()) {
            return [
                'ok' => false,
                'message' => 'Informe e-mail e senha RHID antes de testar.',
            ];
        }

        $this->auth->forgetToken($company);

        try {
            $this->auth->getAccessToken($company, refresh: true);

            return ['ok' => true, 'message' => 'Conexão OK. Token obtido.'];
        } catch (RhidDomainChoiceRequiredException $e) {
            return [
                'ok' => false,
                'needs_domain' => true,
                'domains' => $e->listCustomer,
                'message' => $e->getMessage(),
            ];
        } catch (RhidApiException $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'payload' => $e->payload,
            ];
        }
    }
}
