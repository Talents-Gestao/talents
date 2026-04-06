<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Models\Company;
use App\Models\User;

class RhidMonitoringService
{
    public function __construct(
        private RhidClient $client,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function ultimasMarcacoes(Company $company, ?User $user): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'util.svc/ultimasmarcacoes',
            ['auditAction' => 'rhid.ultimas_marcacoes'],
        );

        if ($r->failed()) {
            throw RhidApiException::fromResponse($r, 'ultimas_marcacoes');
        }

        $json = $r->json();

        return is_array($json) ? $json : [];
    }
}
