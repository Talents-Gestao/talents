<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Client\Response;

class RhidComplianceService
{
    public function __construct(
        private RhidClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listJustificationTypes(Company $company, ?User $user, array $query = []): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/justificationtype.svc/a',
            ['query' => $query, 'auditAction' => 'rhid.justification_types.list'],
        );

        return $this->decodeJson($r, 'justification_types');
    }

    /**
     * @return array<string, mixed>
     */
    public function listAlertTypes(Company $company, ?User $user, array $query = []): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/alerttype.svc/a',
            ['query' => $query, 'auditAction' => 'rhid.alert_types.list'],
        );

        return $this->decodeJson($r, 'alert_types');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function listJustifications(Company $company, ?User $user, array $payload): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/justification.svc/list',
            [
                'body' => $payload,
                'auditAction' => 'rhid.justifications.list',
            ],
        );

        return $this->decodeJson($r, 'justifications.list');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createJustification(Company $company, ?User $user, array $payload): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/justification.svc/a',
            [
                'body' => $payload,
                'auditAction' => 'rhid.justifications.create',
            ],
        );

        return $this->decodeJson($r, 'justifications.create');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function massJustification(Company $company, ?User $user, array $payload): array
    {
        if (isset($payload['inicio']) && ! isset($payload['ínicio'])) {
            $payload['ínicio'] = $payload['inicio'];
            unset($payload['inicio']);
        }

        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/justification.svc/justificativa_em_massa',
            [
                'body' => $payload,
                'auditAction' => 'rhid.justifications.mass',
            ],
        );

        return $this->decodeJson($r, 'justifications.mass');
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteJustification(Company $company, ?User $user, int $id): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'DELETE',
            'customerdb/justification.svc/a/'.$id,
            ['auditAction' => 'rhid.justifications.delete'],
        );

        return $this->decodeJson($r, 'justifications.delete');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function personBankHours(Company $company, ?User $user, array $query): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/person.svc/person_banco_horas',
            [
                'query' => $query,
                'auditAction' => 'rhid.person_banco_horas',
            ],
        );

        $json = $r->json();
        if (! is_array($json)) {
            throw RhidApiException::fromResponse($r, 'person_banco_horas');
        }

        return $json;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function massPersonShift(Company $company, ?User $user, array $items): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/personshift.svc/insertlist',
            [
                'body' => $items,
                'auditAction' => 'rhid.personshift.insertlist',
            ],
        );

        return $this->decodeJson($r, 'personshift.insertlist');
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Response $response, string $context): array
    {
        if ($response->failed()) {
            throw RhidApiException::fromResponse($response, $context);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RhidApiException('Resposta RHID invalida (nao JSON).', $response->status());
        }

        return $json;
    }
}
